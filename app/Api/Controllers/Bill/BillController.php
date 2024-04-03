<?php

namespace App\Api\Controllers\Bill;

use Excel;
use JWTAuth;
use Exception;
use App\Enums\AppEnum;
use Illuminate\Http\Request;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Energy\MeterRecord;
use App\Api\Controllers\BaseController;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Template\TemplateService;


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
   *       @OA\Property(property="name",type="String",description="客户名称"),
   *        @OA\Property(property="start_date",type="String",description="开始日期")
   *     ),
   *       example={"name":"","start_date":"2021-07-01",
   *        "end_date":"2021-07-31","orderBy":"created_at","order":"desc","pagesize":10}
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

    $pagesize = $this->setPagesize($request);
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
      ->with('tenant:id,name')
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());

    $data = $this->handleBackData($data);
    foreach ($data['result'] as $k => &$v) {
      $v['tenant_name'] = $v['tenant']['name'];
      unset($v['tenant']);
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
    $DA = $request->toArray();
    if ($DA['create_type'] == 1 && sizeof($DA['tenant_ids']) == 0) {
      return $this->error("未选择生成账单方式,或者未选择租户！");
    }

    $billDate = getMonthRange($DA['bill_month']);
    $meterCount = MeterRecord::whereBetween('record_date', $billDate)->where('audit_status', 0)->where('status', 0)->count();
    if ($meterCount > 0) {
      return $this->error($DA['bill_month'] . "有未审核的水费电费信息，请先审核后生成账单！");
    }

    try {
      $tenants = Tenant::where('on_rent', 1)->where(function ($q) use ($request) {
        $request->tenant_ids && $q->whereIn('id', $request->tenant_ids);
      })->get();

      $billDay = $request->bill_month . '-' . $request->bill_day;
      $billCount = 0;
      $msg = "";

      $feeTypes = str2Array($DA['fee_types']);
      foreach ($tenants as $k => $tenant) {
        // Log::info("账单生成合同：" . $contract['tenant_id']);
        DB::enableQueryLog();
        $map = [
          'status' => 0,
          'bill_id' => 0,
          'tenant_id' => $tenant->id,
          // 'contract_id' => $contract['id'],
        ];

        $billDetails = $this->billService->billDetailModel()
          ->selectRaw('GROUP_CONCAT(id) as billDetailIds,sum(amount) totalAmt, sum(discount_amount) discountAmt,tenant_id,tenant_name')
          ->where($map)
          ->whereBetween('charge_date', $billDate)
          ->whereIn('fee_type', $feeTypes)
          ->groupBy('tenant_id')
          ->get()->toArray();

        // return $billDetails;
        // return response()->json(DB::getQueryLog());
        if (sizeof($billDetails) == 0) {
          $message = "租户-" . $tenant->name . "无费用信息";
          Log::warning($message);
          $msg .= $message;
          continue;
        }

        $res = $this->billService->createBill($tenant->toArray(), $billDetails, $DA['bill_month'], $billDay, $this->user);
        if (!$res['flag']) {
          Log::error("生成账单错误" . $tenant->name . $res['message']);
        } else {
          $billCount++;
        }
      }
      // }, 3);
      $msg = "共计生成【" . $billCount . "】份账单;" . $msg;
      return $this->success($msg);
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
