<?php

namespace App\Api\Controllers\Bill;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Bill\RefundService;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Tenant\ChargeService;
use App\Enums\AppEnum;
use Exception;

/**
 * 退款
 *
 * @Author leezhua
 * @DateTime 2024-03-05
 */
class RefundController extends BaseController
{
  private $refundService;
  public function __construct()
  {
    $this->user = auth('api')->user();
    $this->uid  = $this->user->id;
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->refundService = new RefundService;
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/refund/list",
   *     tags={"退款"},
   *     summary="退款列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"tenant_id"},
   *       @OA\Property(property="tenant_id",type="int",description="租户id"),
   *       @OA\Property(property="pagesize",type="int",description="行数"),
   *       @OA\Property(property="tenant_name",type="String",description="租户名称"),
   *       @OA\Property(property="start_date",type="date",description="退款开始时间"),
   *       @OA\Property(property="end_date",type="date",description="退款结束时间"),
   *        @OA\Property(property="proj_ids",type="list",description="")
   *     ),
   *       example={"tenant_id":"1","tenant_name":"","start_date":"","end_date":""}
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
    // $validatedData = $request->validate([
    //     'order_type' => 'required|numeric',
    // ]);
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
    if ($request->type) {
      $map['type'] = $request->type;
    }
    if ($request->bill_detail_id) {
      $map['bill_detail_id'] = $request->bill_detail_id;
    }

    DB::enableQueryLog();
    $data = $this->refundService->model()
      ->where($map)
      ->where(function ($q) use ($request) {
        $request->start_date && $q->where('refund_date', '>=',  $request->start_date);
        $request->end_date && $q->where('refund_date', '<=',  $request->end_date);
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
      })
      ->whereHas('billDetail', function ($q) use ($request) {
        $request->tenant_id && $q->whereIn('tenant_id', $request->tenant_id);
        $request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
      })
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());
    $data = $this->handleBackData($data);
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/refund/add",
   *     tags={"退款"},
   *     summary="退款新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"bill_detail_id,amount,refund_date","proj_id"},
   *       @OA\Property(property="bill_detail_id",type="int",description="费用ID"),
   *       @OA\Property(property="amount",type="double",description="退款金额"),
   *       @OA\Property(property="refund_date",type="date",description="退款日期"),
   *       @OA\Property(property="proj_id",type="int",description="项目id")
   *     ),
   *       example={"bill_detail_id":1,"amount":"2","refund_date":"","charge_date":""}
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
      'bill_detail_id' => 'required|numeric|gt:0',
      'amount'    => 'required',
      'refund_date' => 'required|date',
      'bank_id'    => 'required|numeric|gt:0',
    ]);

    $billService = new TenantBillService;
    $billDetail = $billService->billDetailModel()->find($request->bill_detail_id);
    if (!$billDetail) {
      return $this->error("未查询到费用记录。");
    }
    $refund = $this->refundService->model()->selectRaw('sum(amount) amount')->where('bill_detail_id', $request->bill_detail_id)->first();

    $availableRefundAmt = numFormat($billDetail->receive_amount - $refund['amount']);
    if ($availableRefundAmt < $request->amount) {
      return $this->error("已收金额小于退款金额！");
    }

    try {
      $res =  $this->refundService->refund($billDetail, $request, $refund['amount'], $this->user);
      if ($res) {
        return $this->success("退款成功。");
      }
      return $this->error("退款失败！");
    } catch (Exception $th) {
      Log::error("退款失败." . $th);
      return $this->error("退款失败！");
    }
  }

  /**
   * @OA\Post(
   *     path="/api/operation/bill/refund/show",
   *     tags={"退款"},
   *     summary="退款详细",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="id")
   *     ),
   *       example={"ids":"1"}
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
      'id' => 'required',
    ]);

    $data = $this->refundService->model()
      ->with(['chargeBillRecord' => function ($q) {
        $q->with('billDetail:id,bill_date,charge_date,amount,receive_amount');
      }])
      ->find($request->id);
    return $this->success($data);
  }
}
