<?php

namespace App\Api\Controllers\Bill;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Services\Bill\InvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Bill\TenantBillService;
use App\Enums\AppEnum;
use Exception;

/**
 * 租户账单
 */

class InvoiceController extends BaseController
{

  function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->user = auth('api')->user();
    $this->invoiceService = new InvoiceService;
  }


  /**
   * @OA\Post(
   *     path="/api/operation/tenant/invoice/list",
   *     tags={"发票"},
   *     summary="发票列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"pagesize","orderBy","order"},
   *       @OA\Property(property="tenant_id",type="int",description="租户id"),
   *       @OA\Property(property="start_date",type="date",description="开票开始时间"),
   *        @OA\Property(property="end_date",type="date",description="开票结束时间"),
   *        @OA\Property(property="proj_ids",type="list",description="项目id集合"),
   *     ),
   *       example={"tenant_id":"0","start_date":"","end_date":"","proj_ids":"[]"}
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
    if (isset($request->status) && $request->status != "") {
      $map['status'] = $request->status;
    }
    DB::enableQueryLog();
    $subQuery = $this->invoiceService->invoiceRecordModel()
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->start_date && $q->where('invoice_date', '>=', $request->start_date);
        $request->end_date && $q->where('invoice_date', '<=', $request->end_date);
      })
      ->where($map);
    $data = $subQuery->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());

    $data = $this->handleBackData($data);
    return $this->success($data);
  }
  /**
   * @OA\Post(
   *     path="/api/operation/tenant/invoice/add",
   *     tags={"发票"},
   *     summary="发票新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"invoce_id","amount","invoice_no","status","bill_detail_id"},
   *       @OA\Property(property="invoce_id",type="int",description="发票titleID"),
   *       @OA\Property(property="amount",type="float",description="开票金额"),
   *       @OA\Property(property="invoice_no",type="String",description="发票no"),
   *       @OA\Property(property="status",type="int",description="0未开 1 已开"),
   *       @OA\Property(property="bill_detail_id",type="String",description="费用id集合多个id逗号隔开"),
   *       @OA\Property(property="invoice_type",type="String",description="发票类型")
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

  public function store(Request $request)
  {
    $validatedData = $request->validate([
      // 'invoice_no' => 'required',
      'status'      => 'required|in:1,2,3',
      'amount'    => 'required',
      'proj_id'    => 'required|numeric|gt:0',
      'bill_detail_id' => 'required|String',
    ]);
    try {
      $DA = $request->toArray();
      DB::transaction(function () use ($DA) {
        $invoiceRecord = $this->invoiceService->invoiceRecordSave($DA, $this->user);
        $billService = new TenantBillService;
        $billService->billDetailModel()
          ->whereIn('id', str2Array($DA['bill_detail_id']))
          ->update(['invoice_id' => $invoiceRecord->id]);
      });
      return $this->success("发票保存成功.");
    } catch (Exception $th) {
      Log::error("发票保存失败" . $th);
      return $this->error("发票保存失败");
    }
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/invoice/edit",
   *     tags={"发票"},
   *     summary="发票更新",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","invoce_id","amount","invoice_no","status","bill_detail_id"},
   *        @OA\Property(property="id",type="int",description="发票记录id"),
   *       @OA\Property(property="invoce_id",type="int",description="发票titleID"),
   *       @OA\Property(property="amount",type="float",description="开票金额"),
   *       @OA\Property(property="invoice_no",type="String",description="发票no"),
   *       @OA\Property(property="status",type="int",description="0未开 1 已开"),
   *       @OA\Property(property="bill_detail_id",type="String",description="费用id集合多个id逗号隔开"),
   *       @OA\Property(property="invoice_type",type="String",description="发票类型")
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

  public function edit(Request $request)
  {
    $validatedData = $request->validate([
      'id'         => 'required|numeric',
      'invoice_no' => 'required|numeric|gt:0',
      'status'     => 'required|in:1,2,3',
      'amount'     => 'required',
      'proj_id'    => 'required|numeric|gt:0',
      'bill_detail_id' => 'required|String',
    ]);
    try {
      $DA = $request->toArray();

      $res = $this->invoiceService->invoiceModel()->find($DA['id']);
      if ($res['status'] == 3) {
        return $this->error("已取消，不可编辑!");
      }
      $this->invoiceService->invoiceRecordSave($DA, $this->user);

      return $this->success("发票保存成功.");
    } catch (Exception $th) {
      Log::error("发票保存失败" . $th);
      return $this->error("发票保存失败");
    }
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/invoice/show",
   *     tags={"发票"},
   *     summary="发票查看",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *        @OA\Property(property="id",type="int",description="发票记录id")
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

  public function show(Request $request)
  {
    $validatedData = $request->validate([
      'id'         => 'required|numeric',
    ]);
    try {
      // $DA = $request->toArray();
      $data = $this->invoiceService->invoiceRecordModel()->find($request->id);
      if ($data) {
        $billService = new TenantBillService;
        $billDetail = $billService->billDetailModel()->whereIn('id', str2Array($data['bill_detail_id']))->get();
        $data['bill_detail'] = $billDetail;
      }
      return $this->success($data);
    } catch (Exception $th) {
      Log::error("查看失败" . $th);
      return $this->error("查看失败");
    }
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/invoice/cancel",
   *     tags={"发票"},
   *     summary="发票作废",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *        @OA\Property(property="id",type="int",description="发票记录id")
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
  public function cancel(Request $request)
  {
    $validatedData = $request->validate([
      'id'         => 'required|numeric'
    ]);
    $res = $this->invoiceService->cancelInvoice($request->id);
    if ($res) {
      return $this->success("发票取消成功");
    } else {
      return $this->error("发票取消失败!");
    }
  }
}
