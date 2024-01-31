<?php

namespace App\Api\Controllers\Bill;

use App\Api\Controllers\BaseController;
use App\Api\Services\Bill\RefundService;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Tenant\ChargeService;
use App\Enums\AppEnum;

class DepositController extends BaseController
{
  private $depositService;
  private $depositType = AppEnum::depositFeeType;
  public function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->depositService = new TenantBillService;
    $this->user = auth('api')->user();
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/deposit/list",
   *     tags={"押金管理"},
   *     summary="押金管理列表",
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
    if ($request->bill_detail_id) {
      $map['bill_detail_id'] = $request->bill_detail_id;
    }
    $map['type'] = $this->depositType;
    DB::enableQueryLog();
    $subQuery = $this->depositService->billDetailModel()
      ->where($map)
      ->where(function ($q) use ($request) {
        $request->start_date && $q->where('charge_date', '>=',  $request->start_date);
        $request->end_date && $q->where('charge_date', '<=',  $request->end_date);
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->year && $q->whereYear('charge_date', $request->year);
      })
      ->withCount(['refundRecord as refund_amount' => function ($q) {
        $q->selectRaw('FORMAT(sum(amount),2)');
      }]);

    $data = $subQuery->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    $list = $subQuery->get();
    // return response()->json(DB::getQueryLog());
    // 统计每种类型费用的应收/实收/未收
    $stat = ['total_amt' => 0.00, 'receive_amt' => 0.00, 'unreceive_amt' => 0.00];
    foreach ($list as $k => $v) {
      $totalAmt = $v['amount']  ?? 0.00;
      $receiveAmt = $v['receive_amt'] ?? 0.00;
      $unreceiveAmt = $totalAmt - $receiveAmt;

      $stat['total_amt'] +=  $totalAmt;
      $stat['receive_amt'] += $receiveAmt;
      $stat['unreceive_amt'] += $unreceiveAmt;
    }

    $data = $this->handleBackData($data);
    $data['stat'] = $stat;
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/deposit/add",
   *     tags={"押金管理"},
   *     summary="押金管理新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"amount","tenant_id","charge_date","fee_type","type"},
   *       @OA\Property(property="amount",type="float",description="金额"),
   *       @OA\Property(property="tenant_id",type="int",description="租户id"),
   *       @OA\Property(property="proj_id",type="int",description="项目id"),
   *       @OA\Property(property="charge_date",type="date",description="收款日期"),
   *       @OA\Property(property="fee_type",type="int",description="费用类型")
   *     ),
   *       example={"amount":"1","tenant_id":"1","charge_date":"","fee_type":"107"}
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
      'amount' => 'required',
      'fee_type' => 'required|gt:0',
      'charge_date' => 'required|date',
      'tenant_id' => 'required',
      'proj_id' => 'required',
    ], [
      'amount.required' => '金额字段是必填的。',
      'fee_type.required' => '费用类型字段是必填的。',
      'fee_type.gt' => '费用类型必须大于0。',
      'charge_date.required' => '收费日期字段是必填的。',
      'charge_date.date' => '收费日期必须是有效的日期。',
      'tenant_id.required' => '租户ID字段是必填的。',
      'proj_id.required' => '项目ID字段是必填的。',
    ]);
    $DA = $request->toArray();
    $DA['type'] = $this->depositType;
    // $billDetail = new TenantBillService;
    $res = $this->depositService->saveBillDetail($DA, $this->user);
    if (!$res) {
      return $this->error("押金保存失败!");
    }
    return $this->success("押金保存成功.");
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/deposit/edit",
   *     tags={"押金管理"},
   *     summary="押金管理编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"amount","tenant_id","charge_date","fee_type","id"},
   *       @OA\Property(property="id",type="int",description="id"),
   *       @OA\Property(property="amount",type="float",description="金额"),
   *       @OA\Property(property="tenant_id",type="int",description="租户id"),
   *       @OA\Property(property="proj_id",type="int",description="项目id"),
   *       @OA\Property(property="charge_date",type="date",description="收款日期"),
   *       @OA\Property(property="fee_type",type="int",description="费用类型")
   *     ),
   *       example={"id","amount":"1","tenant_id":"1","charge_date":"","fee_type":"107"}
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
      'id'      => 'required|gt:0',
      'amount' => 'required',
      'fee_type' => 'required|gt:0',
      'charge_date' => 'required|date',
      'tenant_id' => 'required',
      'proj_id' => 'required',
    ],  [
      'amount.required' => '金额字段是必填的。',
      'fee_type.required' => '费用类型字段是必填的。',
      'fee_type.gt' => '费用类型必须大于0。',
      'charge_date.required' => '收费日期字段是必填的。',
      'charge_date.date' => '收费日期必须是有效的日期。',
      'tenant_id.required' => '租户ID字段是必填的。',
      'proj_id.required' => '项目ID字段是必填的。',
    ]);
    $DA = $request->toArray();
    $DA['type'] = $this->depositType;
    $deposit = $this->depositService->billDetailModel()->find($request->id);
    if ($deposit->receive_amount > 0.00) {
      return $this->error("已有收款不允许编辑!");
    }
    $res = $this->depositService->editBillDetail($DA, $this->user);
    if (!$res) {
      return $this->error("押金编辑失败!");
    }
    return $this->success("押金编辑成功.");
  }
  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/deposit/show",
   *     tags={"押金管理"},
   *     summary="押金管理详细",
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

    $data = $this->depositService->billDetailModel()
      ->with(['chargeBillRecord' => function ($q) {
        $q->with('billDetail:id,bill_date,charge_date,amount,receive_amount');
      }])
      ->with('refundRecord')
      ->withCount(['refundRecord as refund_amount' => function ($q) {
        $q->selectRaw('FORMAT(sum(amount),2)');
      }])
      ->find($request->id);

    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/deposit/del",
   *     tags={"押金管理"},
   *     summary="押金管理删除",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"Ids"},
   *       @OA\Property(property="Ids",type="list",description="id集合")
   *     ),
   *       example={"Ids":"1"}
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
      'Ids' => 'required|array',
    ]);
    $this->depositService->billDetailModel()->whereIn('id', $request->Ids)
      ->where('type', AppEnum::depositFeeType)
      ->where('receive_amount', '0.00')
      ->delete();
    return $this->success('押金删除成功');
  }


  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/deposit/tocharge",
   *     tags={"押金转收入/违约金"},
   *     summary="押金转收入/违约金",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="id")
   *     ),
   *       example={"id":"1","amount":"0.00","category": "2 3"}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function toCharge(Request $request)
  {
    $request->validate([
      'id' => 'required',
      'amount' => 'required|gt:0',
      'category' => 'required',
    ], [
      'id.required' => 'ID字段是必填的。',
      'amount.required' => '金额字段是必填的。',
      'amount.gt' => '金额必须大于0。',
      'category.required' => '类别字段是必填的。',
    ]);

    $deposit = $this->depositService->billDetailModel()
      ->with(['chargeBillRecord' => function ($q) {
        // $q->with('billDetail:id,bill_date,charge_date,amount,receive_amount');
      }])
      ->with('refundRecord')
      ->withCount(['refundRecord as refund_amount' => function ($q) {
        $q->selectRaw('FORMAT(sum(amount),2)');
      }])
      ->find($request->id);
    if (!$deposit) {
      return $this->error("未找到押金信息");
    }
    $usedAmt = $deposit['receive_amount']  - $deposit['refund_amount'];
    if ($usedAmt <= 0 || $request->amount > $usedAmt) {
      return $this->error("可使用金额不足");
    }
    $data['category']  = $request->category;
    $this->depositService->billDetailModel()->where('id', $request->id)->update($data);
    return $this->success($deposit);
  }
}
