<?php

namespace App\Api\Controllers\Bill;

use JWTAuth;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Services\Contract\ContractService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Template\TemplateService;
use App\Enums\AppEnum;
use Exception;

/**
 * 租户账单
 */

class BillController extends BaseController
{

  function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->user = auth('api')->user();
    $this->parent_type = AppEnum::Tenant;
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
    $billService = new TenantBillService;
    $data = $billService->billModel()
      ->where($map)
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
      })
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());

    $data = $this->handleBackData($data);
    foreach ($data['result'] as $k => &$v) {
      $billCount = $billService->billDetailModel()
        ->selectRaw('sum(amount) totalAmt,sum(discount_amount) disAmt,sum(receive_amount) receiveAmt')
        ->where('bill_id', $v['id'])->first();
      $v['total_amount'] = $billCount['totalAmt'];
      $v['discount_amount'] = $billCount['disAmt'];
      $v['receive_amount'] = $billCount['receiveAmt'];
      $v['unreceive_amount'] = numFormat($billCount['totalAmt'] - $billCount['receiveAmt']);
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
   *          required={"tenantIds","bill_month","charge_date","fee_types"},
   *       @OA\Property(property="tenantIds",type="List",description="客户Id集合"),
   *      @OA\Property(property="bill_month",type="String",description="账单年月例如：2021-07"),
   *      @OA\Property(property="charge_date",type="String",description="应收日"),
   *      @OA\Property(property="fee_types",type="List",description="费用类型Id列表"),
   *     ),
   *       example={"tenantIds":"[]","bill_month":"2021-07","charge_date":"2021-07-05","fee_types":"[101]"}
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
      'tenantIds' => 'required|array',
      'bill_month' => 'required|String',
      'charge_date' => 'required',
      'fee_types' => 'required|array',
      'proj_ids' => 'required|array',
    ]);
    try {
      // DB::transaction(function () use ($request) {

      $contractService = new ContractService;
      $billService = new TenantBillService;
      $contracts = $contractService->model()->select('id', 'tenant_id')
        ->where(function ($q) use ($request) {
          $request->tenantIds && $q->whereIn('tenant_id', $request->tenantIds);
        })
        ->where('contract_state', AppEnum::contractExecute) // 执行状态
        ->whereIn('proj_id', $request->proj_ids)->get();
      $startDate = date('Y-m-01', strtotime($request->bill_month));
      $endDate = date('Y-m-t', strtotime($request->bill_month));
      foreach ($contracts as $k => $v) {
        $bill = $billService->billModel()->where('contract_id', $v['id'])->whereBetween('charge_date', [$startDate, $endDate])->count();
        if ($bill > 0) {
          Log::error("已有账单，合同Id" . $v['id']);
          continue;
        }
        $res = $billService->createBill($v['id'], $request->bill_month, $request->fee_types, $request->charge_date, $this->user);
        if (!$res) {
          Log::error("生成账单日志" . $res);
        }
      }
      // }, 3);
      return $this->success("账单生成成功。");
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
    $billService = new TenantBillService;

    return $this->success($billService->showBill($request->id));
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

    $billService  = new TenantBillService;
    $bill = $billService->billModel()->find($request->bill_id);
    $parm['templateFile'] = public_path('template/') . md5($tem['name']) . ".docx";
    try {
      if (!is_dir(public_path('template/'))) {
        mkdir(public_path('template/'), 0755, true);
      }
      // 合同模版本地不存在则从OSS下载，OSS没有则报错
      if (!$parm['templateFile'] || !file_exists($parm['templateFile'])) {
        $fileUrl = getOssUrl($tem['file_path']);
        $downloadTemlate = copy(trim($fileUrl), $parm['templateFile']);
        if (!$downloadTemlate) {
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
