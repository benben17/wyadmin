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
 * @OA\Tag(
 *     name="账单",
 *     description="账单管理"
 * )
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

    // 排序字段
    if (!$request->orderBy) {
      $request->orderBy = 'charge_date';
    }
    if ($request->order) {
      $request->order = 'desc';
    }

    DB::enableQueryLog();

    $subQuery = $this->billService->billModel()
      ->where(function ($q) use ($request) {
        $request->tenant_id && $q->where('tenant_id', $request->tenant_id);
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->year && $q->whereYear('charge_date', $request->year);
        $request->month && $q->whereMonth('charge_date', $request->month);
        $request->start_date && $q->whereBetween('charge_date', [$request->start_date, $request->end_date]);
        isset($request->status) && $q->where('status', $request->status);
        isset($request->is_print) && $q->where('is_print', $request->is_print);
      })
      ->with('billDetail:id,bill_id,amount,discount_amount,receive_amount,charge_date')
      ->with('tenant:id,name');

    $data = $this->getData($subQuery, $request);
    foreach ($data->items() as $k => &$item) {
      $item['tenant_name'] = $item['tenant']['name'];
      unset($item['tenant']);
      $timestamp = strtotime($item['charge_date']);
      $billMonth = [date('Y-m-01', $timestamp), date('Y-m-t', $timestamp)];

      $thisBillDetail = $item->billDetail->whereBetween('charge_date', $billMonth);

      $item['amount']           = $thisBillDetail->sum('amount');
      $item['discount_amount']  = $thisBillDetail->sum('discount_amount');
      $item['receive_amount']   = $thisBillDetail->sum('receive_amount');
      $item['unreceive_amount'] = $item['amount'] - $item['discount_amount'] - $item['receive_amount'];
      $item['bill_label']       = $item['unreceive_amount'] == 0 ? '已收清' : '未收清';

      $unreceive = $item->billDetail->where('charge_date', '<=', date('Y-m-t', strtotime($item['charge_date'])))
        ->sum('unreceive_amount');
      // Log::info($unreceive);
      $item['bill_reminder'] = $unreceive > 0 ? '1' : '0';
      unset($item['billDetail']);
    }
    return $this->success($this->handleBackData($data));
  }

  private function getData($query, Request $request)
  {
    // 分页
    $pagesize = $this->setPagesize($request);
    // 排序
    $order = $request->orderBy ?? 'created_at';
    // 排序方式
    $sort = $request->order ?? 'desc';
    if (!in_array($sort, $this->sortType)) {
      $sort = 'desc';
    }
    return $query->orderBy($order, $sort)->paginate($pagesize);
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
    # 通过年月获取此月开始日期和结束日期
    // DB::enableQueryLog();
    $isExistsMeterRecordAudit = MeterRecord::whereBetween('record_date', $billDate)
      ->whereHas('meter', function ($q) use ($DA) {
        $q->where('proj_id', $DA['proj_id']);
      })
      ->where(function ($q) use ($DA) {
        $DA['tenant_ids'] && $q->whereIn('tenant_id', $DA['tenant_ids']);
      })
      ->where('audit_status', 0)
      ->where('status', 0)->exists();
    if ($isExistsMeterRecordAudit) {
      return $this->error($DA['bill_month'] . "有未审核的水费电费信息，请先审核后生成账单！");
    }
    $existsBill = $this->billService->billModel()
      ->selectRaw('group_concat(tenant_id) as tenant_ids')
      ->whereBetween('charge_date', $billDate)
      ->where(function ($q) use ($DA) {
        $DA['tenant_ids'] && $q->whereIn('tenant_id', $DA['tenant_ids']);
      })->where('proj_id', $DA['proj_id'])

      ->first();
    if ($existsBill && $existsBill['tenant_ids'] != "") {
      $tenantIds = str2Array($existsBill['tenant_ids']);
      $tenantNames = Tenant::whereIn('id', $tenantIds)->pluck('name')->toArray();
      return $this->error("租户【" . implode(",", $tenantNames) . "】已生成账单，请勿重复生成！");
    }
    // 查询租户
    try {
      $tenants = Tenant::where(function ($q) use ($DA) {
        $DA['tenant_ids'] && $q->whereIn('id', $DA['tenant_ids']);
      })
        ->where('proj_id', $DA['proj_id'])
        ->get();

      // 如果账单日大于28号，则取28号
      $billDay = $request->bill_month . '-' . ($request->bill_day > 28 ? 28 : $request->bill_day);

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

    // DB::enableQueryLog();
    $data = $this->billService->showBill($request->id);
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/reminder",
   *     tags={"账单"},
   *     summary="催缴账单",
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
  public function billReminder(Request $request)
  {
    $request->validate([
      'id' => 'required'
    ], [
      'id.required' => '账单ID不能为空',
    ]);

    DB::enableQueryLog();
    $data = $this->billService->showReminderBill($request->id);
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
   *       @OA\Property(property="ids",type="list",description="账单ids")
   *     ),
   *       example={"id":"[1,2]"}
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
      'Ids' => 'required|array'
    ], [
      'Ids.required' => '账单ID不能为空',
      'Ids.array' => '账单ID格式错误',
    ]);
    try {
      DB::transaction(function () use ($request) {

        $this->billService->billModel()->whereIn('id', $request->Ids)->delete();
        $this->billService->billDetailModel()->whereIn('bill_id', $request->Ids)->update(['bill_id' => 0]);
      }, 2);
      return $this->success("账单删除成功。");
    } catch (Exception $e) {
      Log::error("账单删除失败！" . $e);
      return $this->error("账单删除失败！");
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

  /**
   * @OA\Post(
   *    path="/api/operation/tenant/bill/audit",
   *   tags={"账单"},
   *  summary="账单审核",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="账单id"),
   *       @OA\Property(property="audit_status",type="int",description="1 审核通过 2 审核不通过")
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
  public function billAudit(Request $request)
  {
    $validatedData = $request->validate([
      'billIds' => 'required|array',
      'audit_status' => 'required|in:1,2', // 1 审核通过 2 审核不通过
    ], [
      'audit_status.in' => '审核状态错误',
      'billIds.array' => '账单ID格式错误',
      'billIds.required' => '账单ID不能为空',
    ]);
    $status = $request->audit_status == 1 ? AppEnum::statusAudit : AppEnum::statusUnAudit;
    $updateData = array(
      'status' => $status,
      'remark' => $request->remark ?? "",
    );

    try {
      $this->billService->billModel()
        ->where('status', AppEnum::statusUnAudit)
        ->whereIn('id', $request->billIds)->update($updateData);
      return $this->success("账单审核成功");
    } catch (Exception $e) {
      Log::error("账单审核失败" . $e);
      return $this->error("账单审核失败");
    }
  }


  /**
   * @OA\Post(
   *    path="/api/operation/tenant/bill/print",
   *   tags={"账单"},
   *  summary="账单打印",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","receive_amount"},
   *       @OA\Property(property="billIds",type="list",description="账单ids"),
   *     ),
   *       example={"billIds":"[1]"}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function billPrint(Request $request)
  {
    $validatedData = $request->validate([
      'billIds' => 'required|array',
    ], [
      'billIds.array' => '账单格式错误',
      'billIds.required' => '账单id不能为空',
    ]);

    $res = $this->billService->billModel()
      ->whereIn('id', $request->billIds)
      ->where('status', AppEnum::statusAudit)
      ->update(['is_print' => 1]);
    return $this->success($res ? "账单打印成功" : "账单打印失败");
  }

  /**
   * @OA\Post(
   *    path="/api/operation/tenant/bill/printView",
   *   tags={"账单"},
   *  summary="账单打印预览",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","receive_amount"},
   *       @OA\Property(property="billIds",type="list",description="账单ids"),
   *     ),
   *       example={"billIds":"[1]"}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function billView(Request $request)
  {
    $validatedData = $request->validate([
      'billIds' => 'required|array',
    ], [
      'billIds.array' => '账单格式错误',
      'billIds.required' => '账单id不能为空',
    ]);

    $billIds = $request->billIds;
    $bills = array();
    if (sizeof($billIds) == 0) {
      return $this->error("账单ID不能为空");
    }
    $auditBillCount = $this->billService->billModel()->whereIn('id', $billIds)
      ->where('status', AppEnum::statusAudit)
      ->whereHas('billDetail')->count();

    if (sizeof($billIds) > 0 && $auditBillCount < sizeof($billIds)) {
      return $this->error("所选账单有未审核的账单或者账单内无应收！");
    }
    foreach ($billIds as $billId) {
      $billExists = $this->billService->billModel()->where('id', $billId)
        ->where('status', AppEnum::statusAudit)
        ->whereHas('billDetail')->exists();
      if (!$billExists) {
        continue;
      }
      $bill = $this->billService->showBill($billId);
      $bills[] = $bill;
    }
    return $this->success($bills);
  }
}
