<?php

namespace App\Api\Controllers\Bill;

use JWTAuth;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Models\Bill\TenantBill;
use App\Api\Models\Tenant\Tenant;
use App\Api\Services\Contract\ContractService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Bill\TenantBillService;
use App\Exports\UserExport;
use App\Api\Services\Template\TemplateService;
use App\Enums\AppEnum;
use Exception;
use Excel;

/**
 * 租户账单
 */

class BillController extends BaseController
{
  private $parent_type;
  private $billService;
  function __construct()
  {
    parent::__construct();
    $this->parent_type = AppEnum::Tenant;
    $this->billService = new TenantBillService;
  }


  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/list",
   *     tags={"账单"},
   *     summary="账单列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"pagesize","orderBy","order"},
   *       @OA\Property(property="name",type="String",description="客户名称")
   *        @OA\Property(property="start_date",type="String",description="开始日期")
   *     ),
   *       example={}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function list(Request $request)
  {

    $pagesize = $request->input('pagesize');
    if (!$pagesize || $pagesize < 1) {
      $pagesize = config('per_size');
    }
    if ($pagesize == '-1') {
      $pagesize = config('export_rows');
    }
    $map = array();

    // 排序字段
    if ($request->input('orderBy')) {
      $orderBy = $request->input('orderBy');
    } else {
      $orderBy = 'created_at';
    }
    // 排序方式desc 倒叙 asc 正序
    if ($request->input('order')) {
      $order = $request->input('order');
    } else {
      $order = 'desc';
    }
    if ($request->tenant_id) {
      $map['tenant_id'] = $request->tenant_id;
    }
    DB::enableQueryLog();

    $data = $this->billService->billModel()
      ->where($map)
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->year && $q->whereYear('charge_date', $request->year);
        $request->start_date && $q->whereBetween('charge_date', [$request->start_date, $request->end_date]);
      })
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());

    $data = $this->handleBackData($data);
    foreach ($data['result'] as $k => &$v) {
      $billCount = $this->billService->billDetailModel()
        ->selectRaw('sum(amount) totalAmt,sum(discount_amount) disAmt,sum(receive_amount) receiveAmt')
        ->where('bill_id', $v['id'])->first();
      $v['total_amount'] = $billCount['totalAmt'];
      $v['discount_amount'] = $billCount['disAmt'];
      $v['receive_amount'] = $billCount['receiveAmt'];
      $v['unreceive_amount'] = $v['total_amount'] - $v['discount_amount'];
    }
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/create",
   *     tags={"账单"},
   *     summary="租户列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"create_type","tenant_ids","bill_month","bill_day","fee_types"},
   *      @OA\Property(property="create_type",type="int",description="0 所有 1 根据租户id"),
   *      @OA\Property(property="tenant_ids",type="list",description="客户Id集合"),
   *      @OA\Property(property="bill_month",type="string",description="账单年月例如：2021-07"),
   *      @OA\Property(property="bill_day",type="string",description="应收日"),
   *      @OA\Property(property="fee_types",type="list",description="费用类型Id列表"),
   *     ),
   *       example={"tenant_ids":"","bill_month":"2021-07","bill_day":"05","fee_types":"[101]"}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function createBill(Request $request)
  {
    $validatedData = $request->validate([
      'create_type' => 'required|in:0,1', // 0 所有 1 根据租户id生成
      'tenant_ids' => 'array',
      'bill_month' => 'required|String',
      'bill_day' => 'required|between:1,31',
      'fee_types' => 'required|array',
      'proj_id' => 'required|gt:0',
    ]);
    Log::info($request->tenant_ids);
    try {
      // DB::transaction(function () use ($request) {

      $contractService = new ContractService;

      DB::enableQueryLog();
      // $contracts = $contractService->model()->select('id', 'tenant_id', 'contract_no')
      //   ->where(function ($q) use ($request) {
      //     if ($request->create_type == 1) {
      //       $request->tenant_ids && $q->whereIn('tenant_id', $request->tenant_ids);
      //     }
      //   })
      //   ->where('contract_state', AppEnum::contractExecute) // 执行状态
      //   ->where('proj_id', $request->proj_id)->get();
      $tenants = Tenant::where('on_rent', 1) // 是否在租
        ->where('proj_id', $request->proj_id)
        ->when($request->create_type == 1 && sizeof($request->tenant_ids) > 0, function ($q) use ($request) {
          return $q->whereIn('id', $request->tenant_ids);
        })->get();
      return response()->json(DB::getQueryLog());
      if (!$tenants) {
        return $this->error("请选择租户！");
      }
      $startDate = date('Y-m-01', strtotime($request->bill_month));
      $endDate = date('Y-m-t', strtotime($request->bill_month));
      $billDay = $request->bill_month . '-' . $request->bill_day;

      $billCount = 0;

      foreach ($tenants as $k => $v) {
        Log::info("账单生成合同：" . $v['id'] . $billDay);

        $billDetail = $this->billService->billDetailModel()->selectRaw('group_concat(id) as billDetailIds')
          ->whereBetween('charge_date', [$startDate, $endDate])
          ->where('contract_id', $v['id'])
          ->where('status', 0)
          ->whereIn('fee_type', $request->fee_types)
          ->where('bill_id', 0)
          ->where('tenant_id', $v['tenant_id'])
          ->first();
        if (!$billDetail) {
          log::info("租户" . $v->tenant_no . "无费用信息");
          continue;
        }
        $billCount++;
        $res = $this->billService->createBill($v, $request->bill_month, $request->fee_types, $billDay, $this->user);
        if (!$res) {
          Log::error("生成账单错误" . $v->contract_no);
        }
      }
      // }, 3);
      $billMsg = "共计生成" . $billCount . "份账单";

      return $this->success($billMsg);
    } catch (Exception $th) {
      Log::error($th);
      return $this->error("账单生成失败！");
    }
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/show",
   *     tags={"账单"},
   *     summary="租户列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="账单id")
   *     ),
   *       example={"id":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function show(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required'
    ]);

    DB::enableQueryLog();
    $data = $this->billService->showBill($request->id);
    return $this->success($data);
  }


  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/del",
   *     tags={"账单"},
   *     summary="账单删除",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="账单id")
   *     ),
   *       example={"id":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function del(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required'
    ]);
    try {
      DB::transaction(function () use ($request) {

        $this->billService->billModel()->whereId($request->id)->delete();
        $this->billService->billDetailModel()->where('bill_id', $request->id)->update(['bill_id' => 0]);
      }, 2);
      return $this->success("账单删除成功。");
    } catch (Exception $e) {
      Log::error("账单删除失败！" . $e);
      return $this->success("账单删除失败！");
    }
  }

  public function billToWord(Request $request)
  {
    $validatedData = $request->validate([
      'template_id' => 'required|gt:0',
      'bill_id' => 'required|gt:0',
    ]);
    $bankId = 1;
    $template = new TemplateService;
    $tem = $template->getTemplate($request->template_id);

    $bill = $this->billService->billModel()->find($request->bill_id);
    if (!$bill) {
      return $this->error("无账单可创建");
    }
    $parm['templateFile'] = public_path('template/') . md5($tem['name']) . ".docx";
    try {
      if (!is_dir(public_path('template/'))) {
        mkdir(public_path('template/'), 0755, true);
      }
      // 合同模版本地不存在则从OSS下载，OSS没有则报错
      if (!$parm['templateFile'] || !file_exists($parm['templateFile'])) {
        $fileUrl = getOssUrl($tem['file_path']);
        $downloadTemplate = copy(trim($fileUrl), $parm['templateFile']);
        if (!$downloadTemplate) {
          return $this->error('合同模版不存在');
        }
      }
    } catch (Exception $e) {
      Log::error("模版错误，请重新上传模版" . $e);
      return $this->error('模版错误，请重新上传模版');
    }
    $tenantName = getTenantNameById($bill['tenant_id']);
    $parm['fileName'] = $tenantName . date('Ymd', time()) . ".docx";
    $filePath = "/uploads/" . nowYmd() . "/" . $this->user['company_id'] . "/";
    $parm['savePath'] = public_path() . $filePath;

    $res = $template->createBillToWord($parm, [101, 102, 106], $bill, $bankId);

    if ($res) {
      return $this->success($filePath . $parm['fileName']);
    } else {
      return $this->error("生成账单失败");
    }
  }
}
