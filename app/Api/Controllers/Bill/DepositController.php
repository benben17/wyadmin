<?php

namespace App\Api\Controllers\Bill;

use Exception;
use App\Enums\AppEnum;
use App\Enums\DepositEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Controllers\BaseController;
use App\Api\Services\Bill\ChargeService;
use App\Api\Services\Bill\DepositService;
use App\Api\Services\Bill\TenantBillService;

/**
 * @OA\Tag(
 * 	 name="押金管理",
 *  description="押金管理"
 * )
 */
class DepositController extends BaseController
{
	private $depositService;
	private $depositType = AppEnum::depositFeeType;
	private $chargeService;
	private $errorMsg;
	public function __construct()
	{
		parent::__construct();
		$this->depositService = new DepositService;
		$this->chargeService = new ChargeService;
		$this->errorMsg =  [
			'amount.required'      => '金额字段是必填的。',
			'fee_type.required'    => '费用类型字段是必填的。',
			'fee_type.gt'          => '费用类型必须大于0。',
			'charge_date.required' => '收费日期字段是必填的。',
			'charge_date.date'     => '收费日期必须是有效的日期。',
			'tenant_id.required'   => '租户ID字段是必填的。',
			'proj_id.required'     => '项目ID字段是必填的。',
		];
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
		// $pagesize = $this->setPagesize($request);
		$map = array();

		if ($request->bill_detail_id) {
			$map['bill_detail_id'] = $request->bill_detail_id;
		}
		$map['type'] = $this->depositType;

		DB::enableQueryLog();
		$subQuery = $this->depositService->depositBillModel()
			->where($map)
			->where(function ($q) use ($request) {
				$request->start_date && $q->where('charge_date', '>=', $request->start_date);
				$request->end_date && $q->where('charge_date', '<=', $request->end_date);
				$request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
				$request->year && $q->whereYear('charge_date', $request->year);
				if ($request->status) {
					$status = array_filter(str2Array($request->status), function ($v) {
						is_numeric($v);
					});
					if (!empty($status) > 0) {
						$q->whereIn('status', $status);
					}
				}
				$request->tenant_id && $q->where('tenant_id', $request->tenant_id);
				$request->fee_types && $q->whereIn('fee_type', $request->fee_types);
			})->with('depositRecord');

		$pageQuery = clone $subQuery;

		$data = $this->pageData($pageQuery->with('bankAccount'), $request);

		foreach ($data['result'] as $k => &$v1) {
			$record = $this->depositService->formatDepositRecord($v1['deposit_record']);
			$v1['bank_name'] = $v1['bank_account']['account_name'] ?? "";
			unset($v1['bank_account']);
			$v1 = array_merge($v1, $record);
		}

		// // 统计每种类型费用的应收/实收/ 退款/ 转收入
		$this->depositService->depositStat($subQuery, $data, $this->uid);
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/tenant/deposit/add",
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
		], $this->errorMsg);
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
			'id' => 'required|gt:0',
			'amount' => 'required',
			'fee_type' => 'required|gt:0',
			'charge_date' => 'required|date',
			'tenant_id' => 'required',
			'proj_id' => 'required',
		], $this->errorMsg);
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
			->with('billDetailLog')
			->find($request->id);
		if (!$data) {
			return $this->error((object)[]);
		}
		$data = $data->toArray();
		// return response()->json(DB::getQueryLog());
		$recordSum = $this->depositService->formatDepositRecord($data['deposit_record']);
		$data += $recordSum;
		// $data = array_merge($data + $info);
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/tenant/deposit/del",
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
			'remark' => 'required',
		], [
			'id.required' => '押金ID字段是必填的。',
			'amount.required' => '金额字段是必填的。',
			'amount.gt' => '金额必须大于0。',
		]);

		$DA = $request->toArray();
		$DA['type'] = DepositEnum::RecordToCharge;

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
					throw new Exception("未找到押金信息");
				}

				$record = $this->depositService->formatDepositRecord($deposit['deposit_record']);
				$availableAmt = $record['available_amt'];
				if ($DA['amount'] > $availableAmt) {
					throw new Exception("可使用金额不足");
				}
				$remark = $DA['remark'];
				if ($remark) {
					$remark = "押金转收入";
				}
				$DA['remark'] = $remark;
				$this->depositService->saveDepositRecord($deposit, $DA, $user);
				// 押金转收入 写入到charge  收支表
				$deposit['charge_date'] = $DA['common_date'] ?? nowYmd();
				$this->chargeService->depositToCharge($deposit, $DA, $user);
				if ($availableAmt == $DA['amount']) {
					$updateData['status'] = DepositEnum::Clear;
					$this->depositService->depositBillModel()->whereId($DA['id'])->update($updateData);
				}
			}, 2);
			return $this->success("押金转收入成功");
		} catch (Exception $e) {
			Log::error("押金转收入失败" . $e->getMessage());
			return $this->error("押金转收入失败!");
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
			'id' => 'required|gt:0',
			'amount' => 'required|gt:0',
			'common_date' => 'required|date',
		], [
			'id' => '押金应收id是必填的',
			'amount.required' => '金额字段是必填的。',
			'amount.gt' => '金额必须大于0。',
			'common_date.required' => '收款日期字段是必填的。',
			'common_date.date' => '收款日期必须是有效的日期。',
		]);
		$DA = $request->toArray();
		$DA['type'] = DepositEnum::RecordReceive;

		$depositFee = $this->depositService->depositBillModel()->find($request->id);
		$unreceiveAmt = bcsub($depositFee['amount'], $depositFee['receive_amount'], 2);
		$unreceiveAmt = bcsub($unreceiveAmt, $depositFee['discount_amount'], 2);
		if ($depositFee['status'] != 0 || $unreceiveAmt == 0) {
			return $this->error("此押金已经收款结清!");
		}
		// Log::alert($unreceiveAmt . "未收金额");
		try {


			$this->depositService->depositReceive($depositFee, $DA, $unreceiveAmt, $this->user);
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
			'id' => 'required|gt:0',
			'amount' => 'required|gt:0',
			'remark' => 'required|string',
		], [
			'id' => '押金应收id是必填的',
			'amount.required' => '金额字段是必填的。',
			'remark.string' => '必须是字符串，不允许空字符串。',
		]);
		$DA = $request->toArray();
		DB::enableQueryLog();
		$depositFee = $this->depositService->depositBillModel()
			->with('depositRecord')->find($request->id)->toArray();

		if ($depositFee['status'] == 3) {
			return $this->error("已结清");
		}
		$DA['type'] = DepositEnum::RecordRefund;

		try {
			$this->depositService->depositRefund($depositFee, $DA, $this->user);
			return $this->success("押金退款成功.");
		} catch (Exception $e) {

			return $this->error("押金退款失败！" . $e->getMessage());
		}
	}


	/**
	 * @OA\Post(
	 *     path="/api/operation/tenant/deposit/receive/del",
	 *     tags={"押金管理"},
	 *     summary="押金管理收款删除",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"receiveId"},
	 *       @OA\Property(property="receiveId",type="int",description="收款id")
	 *     ),
	 *       example={"receiveId":"1"}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function receiveRecordDel(Request $request)
	{
		$validatedData = $request->validate([
			'receiveId' => 'required|gt:0',
		]);
		$receiveId = $request->receiveId;

		try {
			$this->depositService->depositReceiveDel($receiveId);
			return $this->success("收款记录删除成功");
		} catch (Exception $e) {
			return $this->error($e->getMessage());
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
		$pagesize = $this->setPagesize($request);
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
				$request->start_date && $q->where('common_date', '>=', $request->start_date);
				$request->end_date && $q->where('common_date', '<=', $request->end_date);
				$request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
				$request->year && $q->whereYear('common_date', $request->year);
				$request->types && $q->whereIn('type', $request->types);
			})
			->whereHas('billDetail', function ($q) use ($request) {
				$request->tenant_id && $q->where('tenant_id', $request->tenant_id);
				$request->bill_detail_id && $q->where('id', $request->bill_detail_id);
				$request->fee_types && $q->whereIn('fee_type', $request->fee_types);
			})->with('billDetail:id,tenant_id,tenant_name,bill_date,fee_type')
			->orderBy($orderBy, $order)
			->paginate($pagesize)->toArray();

		// return response()->json(DB::getQueryLog());
		$data = $this->handleBackData($data);
		foreach ($data['result'] as $k => &$v1) {
			$v1['tenant_name']    = $v1['bill_detail']['tenant_name'] ?? "";
			$v1['bill_date']      = $v1['bill_detail']['bill_date'] ?? "";
			$v1['fee_type_label'] = $v1['bill_detail']['fee_type_label'] ?? "";
			$v1['fee_type']       = $v1['bill_detail']['fee_type'] ?? "";
			unset($v1['bill_detail']);
		}
		return $this->success($data);
	}
}
