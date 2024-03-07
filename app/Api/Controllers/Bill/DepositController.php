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
        $request->tenant_id && $q->where('tenant_id', $request->tenant_id);
        $request->fee_types && $q->whereIn('fee_type', $request->fee_types);
      })
      ->with('depositRecord');

    $data = $subQuery->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();

    $list = $subQuery->get()->toArray();
    // return response()->json(DB::getQueryLog());
    // 统计每种类型费用的应收/实收/ 退款/ 转收入

    $data = $this->handleBackData($data);
    foreach ($data['result'] as $k => &$v1) {
      $record = $this->depositService->formatDepositRecord($v1['deposit_record']);
      $v1 = $v1 + $record;
    }
    $data['stat'] = $this->depositService->depositStat($list);
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
      'remark' => 'required'
    ], [
      'id.required' => 'ID字段是必填的。',
      'amount.required' => '金额字段是必填的。',
      'amount.gt' => '金额必须大于0。',
    ]);



    $DA = $request->toArray();
    $DA['type'] = AppEnum::depositRecordToCharge;


    try {
      $user = $this->user;
      DB::transaction(function () use ($DA, $user) {
        $deposit = $this->depositService->depositBillModel()
          ->where('type', AppEnum::depositFeeType)
          ->where(function ($q) {
            $q->whereIn('status', [0, 1, 2]);
          })
          ->with('depositRecord')
          ->find($DA['id'])->toArray();
        if (!$deposit) {
          return $this->error("未找到押金信息");
        }

        $record = $this->depositService->formatDepositRecord($deposit['deposit_record']);
        $availableAmt = $record['available_amt'];
        if ($DA['amount'] > $availableAmt) {
          throw new Exception("可使用金额不足");
        }
        $remark = $DA['remark'];
        if ($remark) {
          $remark =  "押金转收入";
        }
        $DA['remark'] = $remark;
        $this->depositService->saveDepositRecord($deposit, $DA, $user);
        // 押金转收入 写入到charge  收支表
        $this->chargeService->depositToCharge($deposit, $DA, $user);
        if ($availableAmt == $DA['amount']) {
          $updateData['status'] = 3;
          $this->depositService->depositBillModel()->whereId($DA['id'])->update($updateData);
        }
      }, 2);
      return  $this->success("押金转收入成功");
    } catch (Exception $e) {
      Log::error("押金转收入失败" . $e->getMessage());
      return $this->error("押金转收入失败!" . $e->getMessage());
    }
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/deposit/receive",
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
  public function receive(Request $request)
  {
    $validatedData = $request->validate([
      'id'      => 'required|gt:0',
      'amount' => 'required|gt:0',
    ],  [
      'id' => '押金应收id是必填的',
      'amount.required' => '金额字段是必填的。',

    ]);
    $DA = $request->toArray();
    $DA['type'] = AppEnum::depositRecordReceive;

    $depositBill = $this->depositService->depositBillModel()->find($request->id);
    if ($depositBill['status'] != 0) {
      return $this->error("此押金已经收款结清!");
    }

    try {
      $user = $this->user;
      DB::transaction(function () use ($depositBill, $DA, $user) {
        // 已收款金额+ 本次收款金额 
        $totalReceiveAmt = $depositBill['receive_amount'] + $DA['amount'];
        $unreceiveAmt  = $depositBill['unreceive_amount'];
        $updateData['receive_amount'] =  $totalReceiveAmt;
        if ($DA['amount'] > $unreceiveAmt) {
          throw new Exception("收款金额不允许大于未收金额!");
        }
        // 应收和实际收款 相等时
        if ($DA['amount'] === $unreceiveAmt) {
          $updateData['status'] =  1;
        }
        // 保存押金流水记录
        $this->depositService->saveDepositRecord($depositBill, $DA, $user);
        // 更新 押金信息 【状态，收款金额】
        $this->depositService->depositBillModel()->whereId($DA['id'])->update($updateData);
      }, 2);

      return $this->success("押金收款【" . $DA['amount'] . "元】 成功.");
    } catch (Exception $e) {
      return $this->error("押金收款【" . $DA['amount'] . "元 】失败!" . $e->getMessage());
    }
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
   *       @OA\Property(property="amount",type="float",description="金额,保留小数点后2位"),
   *       @OA\Property(property="remark",type="int",description="备注"),

   *     ),
   *       example={"id":"1","amount":"1","remark":"备注"}
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
      'remark' => 'required|string',
    ],  [
      'id' => '押金应收id是必填的',
      'amount.required' => '金额字段是必填的。',
      'remark.string'  => '必须是字符串，不允许空字符串。'
    ]);
    $DA = $request->toArray();
    DB::enableQueryLog();
    $depositBill = $this->depositService->depositBillModel()
      ->with('depositRecord')->find($request->id)->toArray();

    if ($depositBill['status'] == 3) {
      return $this->error("已结清");
    }
    $DA['type'] = AppEnum::depositRecordRefund;

    try {
      $user = $this->user;
      DB::transaction(function () use ($depositBill, $DA, $user) {
        $record = $this->depositService->formatDepositRecord($depositBill['deposit_record']);

        $availableAmt = $record['available_amt'];
        Log::error($availableAmt);

        if ($availableAmt < $DA['amount']) {
          throw new Exception("此押金可用余额小于退款金额，不可操作!");
        }
        if ($availableAmt > $DA['amount']) {
          $updateData['status'] = 2; // 部分退款
        } else {
          $updateData['status'] = 3; // 已结清
        }
        // 插入记录
        $this->depositService->saveDepositRecord($depositBill, $DA, $user);
        // 更新押金信息
        $this->depositService->depositBillModel()->where('id', $DA['id'])->update($updateData);
      }, 2);
      return $this->success("押金退款成功.");
    } catch (Exception $e) {

      return $this->error("押金退款失败！" . $e->getMessage());
    }
  }


  /**
   * @OA\Post(
   *     path="/api/operation/tenant/deposit/record/list",
   *     tags={"押金管理"},
   *     summary="押金流水列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"tenant_id","proj_ids"},
   *       @OA\Property(property="tenant_id",type="int",description="租户id"),
   *       @OA\Property(property="pagesize",type="int",description="行数"),
   *       @OA\Property(property="tenant_name",type="String",description="租户名称"),
   *       @OA\Property(property="start_date",type="date",description="开始时间"),
   *       @OA\Property(property="end_date",type="date",description="结束时间"),
   *        @OA\Property(property="proj_ids",type="list",description="")
   *     ),
   *       example={"tenant_id":"1","tenant_name":"","start_date":"","end_date":"","types":"[]","proj_ids":"[]"}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function recordList(Request $request)
  {
    $validatedData = $request->validate([
      'proj_ids' => 'required|array',
    ]);
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
    DB::enableQueryLog();
    $data = $this->depositService->recordModel()
      ->where(function ($q) use ($request) {
        $request->start_date && $q->where('operate_date', '>=',  $request->start_date);
        $request->end_date && $q->where('operate_date', '<=',  $request->end_date);
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->year && $q->whereYear('operate_date', $request->year);
        $request->types && $q->whereIn('type', $request->fee_types);
      })
      ->whereHas('billDetail', function ($q) use ($request) {
        $request->tenant_id && $q->where('tenant_id', $request->tenant_id);
      })
      ->orderBy($orderBy, $order)
      ->paginate($pagesize);

    // $list = $subQuery->get()->toArray();
    // return response()->json(DB::getQueryLog());
    // // 统计每种类型费用的应收/实收/ 退款/ 转收入

    $data = $this->handleBackData($data);
    // foreach ( as $k => &$v1) {
    // }
    return $this->success($data);
  }
}
