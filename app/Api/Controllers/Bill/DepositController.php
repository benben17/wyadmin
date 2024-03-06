<?php

namespace App\Api\Controllers\Bill;

use App\Api\Controllers\BaseController;
use App\Api\Services\Bill\DepositService;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Tenant\ChargeService;
use App\Enums\AppEnum;
use Exception;

class DepositController extends BaseController
{
  private $depositService;
  private $depositType = AppEnum::depositFeeType;
  private $chargeService;
  public function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->depositService = new DepositService;
    $this->chargeService = new ChargeService;
    $this->user = auth('api')->user();
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/deposit/list",
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

    if ($request->bill_detail_id) {
      $map['bill_detail_id'] = $request->bill_detail_id;
    }
    $map['type'] = $this->depositType;

    DB::enableQueryLog();
    $subQuery = $this->depositService->depositBillModel()
      ->where($map)
      ->where(function ($q) use ($request) {
        $request->start_date && $q->where('charge_date', '>=',  $request->start_date);
        $request->end_date && $q->where('charge_date', '<=',  $request->end_date);
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->year && $q->whereYear('charge_date', $request->year);
        $request->status && $q->whereIn('status', $request->status);
      })
      ->with('depositRecord');

    $data = $subQuery->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();

    // return response()->json(DB::getQueryLog());
    // 统计每种类型费用的应收/实收/ 退款/ 转收入
    $stat = ['total_amt' => 0.00, 'receive_amt' => 0.00, 'refund_amt' => 0.00, 'charge_amt' => 0.00];
    foreach ($data['data'] as $k => &$v) {
      $stat['total_amt'] += $v['amount'];
      $record = $this->depositService->formatDepositRecord($v['deposit_record']);
      $v = array_merge($v, $record);
      $stat['refund_amt'] += $record['refund_amt'];
      $stat['charge_amt'] += $record['charge_amt'];
      $stat['receive_amt'] += $v['receive_amount'];
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
    $tenantBillService = new TenantBillService;
    $res = $tenantBillService->saveBillDetail($DA, $this->user);
    if (!$res) {
      return $this->error("押金保存失败!");
    }
    return $this->success("押金保存成功.");
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/deposit/edit",
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
    $tenantBillService = new TenantBillService;
    $deposit = $this->depositService->depositBillModel()->find($request->id);
    if ($deposit->receive_amount > 0.00) {
      return $this->error("已有收款不允许编辑!");
    }
    $res = $tenantBillService->editBillDetail($DA, $this->user);
    if (!$res) {
      return $this->error("押金编辑失败!");
    }
    return $this->success("押金编辑成功.");
  }
  /**
   * @OA\Post(
   *     path="/api/operation/tenant/deposit/show",
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

    DB::enableQueryLog();
    $data = $this->depositService->depositBillModel()
      ->with('depositRecord')
      ->withCount('depositRecord')
      ->find($request->id)->toArray();
    // return response()->json(DB::getQueryLog());
    $recordSum = $this->depositService->formatDepositRecord($data['deposit_record']);
    $data = $data + $recordSum;
    // $data = array_merge($data + $info);
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
    $this->depositService->depositBillModel()->whereIn('id', $request->Ids)
      ->where('type', AppEnum::depositFeeType)
      ->where('receive_amount', '0.00')
      ->delete();
    return $this->success('押金删除成功');
  }


  /**
   * @OA\Post(
   *     path="/api/operation/tenant/deposit/tocharge",
   *     tags={"押金管理"},
   *     summary="押金管理 转收入/违约金",
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

    $deposit = $this->depositService->depositBillModel()
      ->where('type', AppEnum::depositFeeType)
      ->where(function ($q) {
        $q->whereIn('status', [1, 3]);
      })
      ->with('depositRecord')
      ->find($request->id)->toArray();
    if (!$deposit) {
      return $this->error("未找到押金信息");
    }

    $usedAmt = 0.00;
    if (empty($deposit['deposit_record'])) {
      foreach ($deposit['deposit_record'] as $v) {
        if ($deposit['deposit_record']['type'] != 1) {
          $usedAmt += $v['amount'];
        }
      }
    }

    $availableAmt = $deposit['receive_amount'] - $usedAmt;
    if ($request->amount > $availableAmt) {
      return $this->error("可使用金额不足");
    }
    $remark = $request->remark;
    if ($remark) {
      $remark = ($request->category == 2) ? "押金转收入" : "押金转违约金";
    }

    $DA = $request->toArray();
    $DA['type'] = AppEnum::depositRecordToCharge;
    $DA['remark'] = $remark;

    try {
      $user = $this->user;
      DB::transaction(function () use ($deposit, $DA, $user) {
        $this->depositService->saveDepositRecord($deposit, $DA, $user);
        // 押金转收入 写入到charge  收支表
        $this->chargeService->depositToCharge($deposit, $DA, $user);
      }, 2);
      return  $this->success("押金转收入成功");
    } catch (Exception $e) {
      Log::error("押金转收入失败" . $e->getMessage());
      return $this->error("押金转收入失败!");
    }
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/deposit/payee",
   *     tags={"押金管理"},
   *     summary="押金管理 收款",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"amount","remark","id"},
   *       @OA\Property(property="id",type="int",description="id"),
   *       @OA\Property(property="amount",type="float",description="金额")
   *     ),
   *       example={"id","amount":"1","remark":"押金收款"}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function payee(Request $request)
  {
    $validatedData = $request->validate([
      'id'      => 'required|gt:0',
      'amount' => 'required|gt:0',
      'remark' => 'string',
    ],  [
      'id' => '押金应收id是必填的',
      'amount.required' => '金额字段是必填的。',

    ]);
    $DA = $request->toArray();

    $depositBill = $this->depositService->depositBillModel()
      ->find($request->id);

    // 应收和实际收款 相等时
    if ($depositBill->receive_amount  === $depositBill['amount']) {
      return $this->error("此押金已经收款结清!");
    }
    $DA['type'] = AppEnum::depositRecordPayee;
    $res = $this->depositService->saveDepositRecord($depositBill, $DA, $this->user);

    // 已收款金额+ 本次收款金额
    $receiveAmt  = $depositBill['receive_amount'] + $DA['amount'];
    $updateData['receive_amount'] =  $receiveAmt;
    if ($receiveAmt === $DA['amount']) {
      $updateData['status'] =  1;
    }

    $depositBill = $this->depositService->depositBillModel()->whereId($DA['id'])->update($updateData);
    if (!$res) {
      return $this->error("押金收款" . $DA['amount'] . "元失败!");
    }
    return $this->success("押金收款" . $DA['amount'] . "元成功.");
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/deposit/refund",
   *     tags={"押金管理"},
   *     summary="押金管理 退款",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"amount","remark","id"},
   *       @OA\Property(property="id",type="int",description="id"),
   *       @OA\Property(property="amount",type="float",description="金额"),
   *       @OA\Property(property="remark",type="int",description="备注"),

   *     ),
   *       example={"id","amount":"1","remark":"1"}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function refund(Request $request)
  {
    $validatedData = $request->validate([
      'id'      => 'required|gt:0',
      'amount' => 'required|gt:0',
      'remark' => 'string',
    ],  [
      'id' => '押金应收id是必填的',
      'amount.required' => '金额字段是必填的。',

    ]);
    $DA = $request->toArray();

    $depositBill = $this->depositService->depositBillModel()
      ->with(['depositRecord' => function ($q) {
        $q->where('type', [2, 3]);
      }])->find($request->id);

    if ($depositBill['status'] == 2) {
      return $this->error("已全部退款");
    }
    $DA['type'] = AppEnum::depositRecordRefund;

    try {
      $user = $this->user;
      DB::transaction(function () use ($depositBill, $DA, $user) {
        $usedAmt = 0.00;
        if ($depositBill['deposit_record']) {
          foreach ($depositBill['deposit_record'] as $v) {
            $usedAmt += $v['amount'];
          }
        }

        $this->depositService->saveDepositRecord($depositBill, $DA, $user);
        $availableAmt = $depositBill->receive_amount - $usedAmt;

        if ($availableAmt < $DA['amount']) {
          throw ("此押金可用余额小于退款金额，不可操作!");
        }
        if ($availableAmt > $DA['amount']) {
          $updateData['status'] = 3; // 全部
        } else {
          $updateData['status'] = 2; // 部分
        }
        $this->depositService->depositBillModel()->where('id', $DA['id'])->update($updateData);
      }, 2);
      return $this->success("押金退款成功.");
    } catch (Exception $e) {

      return $this->error("押金退款失败！");
    }
  }
}
