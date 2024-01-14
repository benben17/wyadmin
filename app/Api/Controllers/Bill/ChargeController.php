<?php

namespace App\Api\Controllers\Bill;

use Illuminate\Validation\ValidationException;
use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Api\Services\Tenant\ChargeService;
use App\Enums\AppEnum;
use App\Api\Services\Bill\TenantBillService;

class ChargeController extends BaseController
{
  private $chargeService;

  public function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->chargeService = new ChargeService;
    $this->user = auth('api')->user();
  }

  /**
   * @OA\Post(
   *     path="/api/operation/charge/list",
   *     tags={"预充值"},
   *     summary="预充值列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"tenant_id"},
   *       @OA\Property(property="tenant_id",type="int",description="租户id"),
   *       @OA\Property(property="pagesize",type="int",description="行数"),
   *       @OA\Property(property="tenant_name",type="String",description="租户名称"),
   *       @OA\Property(property="start_date",type="date",description="开始时间"),
   *       @OA\Property(property="end_date",type="date",description="结束时间"),
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
    if (isset($request->status) && $request->status != "") {
      $map['status'] = $request->status;
    }
    DB::enableQueryLog();
    $data = $this->chargeService->model()
      ->where($map)
      ->where(function ($q) use ($request) {
        $request->tenant_id && $q->whereIn('tenant_id', $request->tenant_id);
        $request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
        $request->start_date && $q->where('charge_date', '>=',  $request->start_date);
        $request->end_date && $q->where('charge_date', '<=',  $request->end_date);
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
      })
      ->withCount('chargeBillRecord')
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());
    $data = $this->handleBackData($data);
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/charge/add",
   *     tags={"预充值"},
   *     summary="预充值新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"tenant_id,amount,charge_date","charge_type"},
   *       @OA\Property(property="tenant_id",type="int",description="租户id"),
   *       @OA\Property(property="amount",type="double",description="收款金额"),
   *       @OA\Property(property="charge_date",type="date",description="充值日期"),
   *       @OA\Property(property="proj_id",type="int",description="项目id")
   *     ),
   *       example={"tenant_id":1,"tenant_name":"2","amount":"","charge_date":""}
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
      'tenant_id' => 'required|numeric|gt:0',
      'amount'    => 'required',
      'type'      => 'required|in:1,2', // 1 收入 2 支出
      'proj_id'    => 'required|numeric|gt:0',
      'charge_date'    => 'required|date',
    ]);

    $res = $this->chargeService->save($request->toArray(), $this->user);
    if (!$res) {
      return $this->error("收款失败！");
    }
    return $this->success("收款成功。");
  }

  /**
   * @OA\Post(
   *     path="/api/operation/charge/edit",
   *     tags={"收款"},
   *     summary="收款编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"tenant_id,amount,charge_date","id","charge_type"},
   *       @OA\Property(property="id",type="int",description="id"),
   *       @OA\Property(property="tenant_id",type="int",description="租户id"),
   *       @OA\Property(property="amount",type="double",description="充值金额"),
   *       @OA\Property(property="charge_type",type="int",description="费用类型"),
   *       @OA\Property(property="charge_date",type="date",description="充值日期"),
   *       @OA\Property(property="type",type="string",description="1收入、2 支出"),
   *      @OA\Property(property="proj_id",type="int",description="项目id")
   *     ),
   *       example={"tenant_id":1,"tenant_name":"2","amount":"","charge_date":"","proj_id":""}
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
      'id'        => 'required|numeric|gt:0',
      'tenant_id' => 'required|numeric|gt:0',
      'type'      => 'required|in:1,2', // 1 收入 2 支出
      'amount'    => 'required',
      'proj_id'    => 'required|numeric|gt:0',
      'charge_date' => 'required|date',
    ]);

    $count = $this->chargeService->model()->whereHas('chargeBillRecord')
      ->where('id', $request->id)->count();
    if (!$count) {
      return $this->error("不允许修改！");
    }
    $res = $this->chargeService->save($request->toArray(), $this->user);
    if (!$res) {
      return $this->error("更新失败！");
    }
    return $this->success("更新成功。");
  }


  /**
   * @OA\Post(
   *     path="/api/operation/charge/cancel",
   *     tags={"预充值"},
   *     summary="预充值删除",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"ids"},
   *       @OA\Property(property="ids",type="List",description="id集合")
   *     ),
   *       example={"ids":"[1,2]"}
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
      'ids' => 'required|array',
    ]);
    DB::enableQueryLog();
    $res = $this->chargeService->model()
      ->whereDoesntHave('chargeBillRecord')
      ->whereIn('id', $request->ids)->update(["status" => 3]);
    // return response()->json(DB::getQueryLog());
    if (!$res) {
      return $this->error("有核销记录不允许取消！");
    }
    return $this->success("收支取消成功。");
  }
  /**
   * @OA\Post(
   *     path="/api/operation/charge/show",
   *     tags={"预充值"},
   *     summary="预充值详细",
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

    $data = $this->chargeService->model()
      ->with(['chargeBillRecord' => function ($q) {
        $q->with('billDetail:id,bill_date,charge_date,amount,receive_amount');
      }])
      ->find($request->id);
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/charge/writeOff",
   *     tags={"核销"},
   *     summary="充值核销",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","bill_detail_ids"},
   *       @OA\Property(property="id",type="int",description="id"),
   *       @OA\Property(property="bill_detail_ids",type="list",description="应收费用ids 数组"),
   *     ),
   *       example={"id":"1","bill_detail_ids":[]}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function chargeWriteOff(Request $request)
  {
    try {
      $validatedData = $request->validate([
        'id' => 'required',
        'bill_detail_ids' => 'array',
      ]);

      $verifyDate = $request->verify_date ?? nowYmd();

      $charge = $this->chargeService->model()
        ->where('id', $request->id)
        ->where('status', AppEnum::chargeUnVerify)
        ->firstOrFail();

      $billDetailService = new TenantBillService;
      $billDetailIds = $request->bill_detail_ids;

      $billDetailList = $billDetailService->billDetailModel()
        ->whereIn('id', $billDetailIds)
        ->where('status', AppEnum::chargeUnVerify)
        ->get();

      if ($billDetailList->isEmpty()) {
        return $this->error("未找到应收记录");
      }

      $totalVerifyAmt = $billDetailList->sum(function ($billDetail) {
        return $billDetail['amount'] - $billDetail['receive_amount'] - $billDetail['discount_amount'];
      });

      if ($charge['unverify_amount'] < $totalVerifyAmt) {
        return $this->error("充值金额不足，请重新选择应收款项");
      }

      $writeOffRes = $this->chargeService->detailBillListWriteOff($billDetailList, $charge, $verifyDate, $this->user);

      return $writeOffRes
        ? $this->success("核销成功")
        : $this->error("核销失败");
    } catch (ValidationException $e) {
      return $this->error($e->validator->errors()->first());
    } catch (\Exception $e) {
      return $this->error("发生错误：" . $e->getMessage());
    }
  }
}
