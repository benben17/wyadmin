<?php

namespace App\Api\Controllers\Bill;

use App\Enums\AppEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Controllers\BaseController;
use App\Api\Models\Company\BankAccount;
use App\Api\Services\Tenant\ChargeService;
use App\Api\Services\Bill\TenantBillService;
use Illuminate\Validation\ValidationException;

/**
 * 
 */
/** 
 * @OA/Tag(
 * 	 name="收支管理",
 * 	 description="收支管理,以及核销"
 * )
 */
class ChargeController extends BaseController
{
	private $chargeService;
	public function __construct()
	{
		parent::__construct();
		$this->chargeService = new ChargeService;
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/charge/list",
	 *     tags={"收支"},
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
	 *       example={"tenant_id":"1","tenant_name":"","start_date":"","end_date":"","proj_ids":"[]"}
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
		$request->validate([
			'source' => 'required|in:1,2',
		], [
			'source.required' => '类别字段是必填的。',
			'source.in' => '类别字段必须是1或2。',
		]);

		$pagesize = $request->input('pagesize');
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
		if ($request->type) {
			$map['type'] = $request->type;
		}
		if (isset($request->status) && $request->status != "") {
			$map['status'] = $request->status;
		}
		$map['source'] = $request->source;
		DB::enableQueryLog();
		$data = $this->chargeService->model()
			->where($map)
			->where(function ($q) use ($request) {
				$request->tenant_id && $q->where('tenant_id', $request->tenant_id);
				$request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
				$request->start_date && $q->where('charge_date', '>=', $request->start_date);
				$request->end_date && $q->where('charge_date', '<=', $request->end_date);
				$request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
				$request->category && $q->where('category', $request->category);
			})
			->withCount('chargeBillRecord')
			->orderBy($orderBy, $order)
			->paginate($pagesize)->toArray();
		// return response()->json(DB::getQueryLog());
		$data = $this->handleBackData($data);
		foreach ($data['result'] as &$v) {
			$v['refund_amt'] = $this->chargeService->model()->where('charge_id', $v['id'])->sum('amount');
		}
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/charge/add",
	 *     tags={"收支"},
	 *     summary="充值新增",
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
			'amount' => 'required',
			'type' => 'required|in:1,2', // 1 收入 2 支出
			'proj_id' => 'required|numeric|gt:0',
			'charge_date' => 'required|date',
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
	 *     tags={"收支"},
	 *     summary="充值收款编辑",
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
			'id' => 'required|numeric|gt:0',
			'tenant_id' => 'required|numeric|gt:0',
			'type' => 'required|in:1,2', // 1 收入 2 支出
			'amount' => 'required',
			'proj_id' => 'required|numeric|gt:0',
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
	 *     tags={"收支"},
	 *     summary="预充值 删除-只能删除没有核销的收支",
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
			->whereIn('id', $request->ids)->delete();
		// return response()->json(DB::getQueryLog());

		return $res ? $this->success("收支取消成功。") : $this->error("有核销记录不允许取消！");
	}
	/**
	 * @OA\Post(
	 *     path="/api/operation/charge/show",
	 *     tags={"收支"},
	 *     summary="收支详细",
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
		$data['bank'] = BankAccount::find($data->bank_id);
		$data['refund_list'] = $this->chargeService->model()->where('charge_id', $request->id)->get();
		$data['refund_amt'] = $this->chargeService->model()->where('charge_id', $data['id'])->sum('amount');
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/charge/writeOff",
	 *     tags={"收支"},
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
	 *       example={"id":"1","bill_detail_ids":"[]"}
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
		$request->validate([
			'id' => 'required',
			'bill_detail_ids' => 'required|array',
		], [
			'id.required' => '收支字段是必填的。',
			'bill_detail_ids.required' => '账单明细IDS字段是必填的。',
			'bill_detail_ids.array' => '账单明细IDS必须是一个数组。',
		]);

		try {

			$verifyDate = $request->verify_date ?? nowYmd();
			$whereMap['id'] = $request->id;
			$whereMap['status'] = AppEnum::chargeUnVerify;

			$charge = $this->chargeService->model()
				->where($whereMap)
				->firstOrFail();

			$billDetailService = new TenantBillService;
			$billDetailIds = $request->bill_detail_ids;

			$billDetailList = $billDetailService->billDetailModel()
				->whereIn('id', $billDetailIds)
				->where('status', AppEnum::chargeUnVerify)
				->where('bill_id', '>', 0)
				->get();
			// Check if all selected bill details are found
			if ($billDetailList->count() < count($billDetailIds)) {
				return $this->error("所选应收 包含未生成账单的应收");
			}

			$totalVerifyAmt = $billDetailList->sum(function ($billDetail) {
				return $billDetail['amount'] - $billDetail['receive_amount'] - $billDetail['discount_amount'];
			});

			if ($charge['unverify_amount'] < $totalVerifyAmt) {
				return $this->error("充值金额不足，请重新选择应收款项!");
			}

			$writeOffRes = $this->chargeService->detailBillListWriteOff($billDetailList->toArray(), $charge, $verifyDate, $this->user);

			return $writeOffRes ? $this->success("核销成功") : $this->error("核销失败");
		} catch (\Exception $e) {
			return $this->error("发生错误：" . $e->getMessage());
		}
	}

	/**
	 * 单个核销
	 *
	 * @Author leezhua
	 * @DateTime 2024-03-05
	 * @param Request $request
	 *
	 * @return void
	 */
	public function chargeWriteOffOne(Request $request)
	{
		try {
			$validatedData = $request->validate([
				'id' => 'required',
				'bill_detail_id' => 'required|gt:0',
			]);

			$verifyDate = $request->verify_date ?? nowYmd();

			$charge = $this->chargeService->model()
				->where('id', $request->id)
				->where('status', AppEnum::chargeUnVerify)
				->firstOrFail();

			$billDetailService = new TenantBillService;
			$billDetail = $billDetailService->billDetailModel()
				->where('id', $request->bill_detail_id)
				->where('status', AppEnum::chargeUnVerify)
				->first();

			if ($billDetail->isEmpty()) {
				return $this->error("未找到应收记录");
			}

			$writeOffRes = $this->chargeService->detailBillListWriteOffOne($billDetail, $charge, $verifyDate, $this->user);

			return $writeOffRes
				? $this->success("核销成功")
				: $this->error("核销失败");
		} catch (ValidationException $e) {
			return $this->error($e->validator->errors()->first());
		} catch (\Exception $e) {
			return $this->error("发生错误：" . $e->getMessage());
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/charge/record/list",
	 *     tags={"收支"},
	 *     summary="收支-核销记录",
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
	public function recordList(Request $request)
	{
		$msg = ['proj_ids.required' => '项目id是必传'];
		$request->validate([
			'proj_ids' => 'required|array',
		], $msg);

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
		if ($request->type) {
			$map['type'] = $request->type;
		}

		DB::enableQueryLog();
		$data = $this->chargeService->chargeRecord()
			->where($map)
			->where(function ($q) use ($request) {
				$request->start_date && $q->where('verify_date', '>=', $request->start_date);
				$request->end_date && $q->where('verify_date', '<=', $request->end_date);
				$request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
				$request->c_username && $q->where('c_username', $request->c_username);
				$request->fee_types && $q->whereIn('fee_type', $request->fee_types);
				$request->year && $q->whereYear('verify_date', $request->year);
			})
			->with(['billDetail' => function ($query) use ($request) {
				$query->select('tenant_name', 'tenant_id', 'id', 'status');
				$request->tenant_id && $query->whereIn('tenant_id', $request->tenant_id);
				$request->tenant_name && $query->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
			}])
			->orderBy($orderBy, $order)
			->paginate($pagesize)->toArray();
		// return response()->json(DB::getQueryLog());
		$totalAmt = 0.00;
		$data = $this->handleBackData($data);
		foreach ($data['result'] as &$v) {
			$v['tenant_name'] = getTenantNameById($v['tenant_id']);
			if ($v['type'] == 1) {
				$totalAmt += $v['amount'];
			} else {
				$totalAmt -= $v['amount'];
			}
		}
		$data['total_amount'] = $totalAmt;
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/charge/delete",
	 *     tags={"收支"},
	 *     summary="收支删除",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"id"},
	 *       @OA\Property(property="id",type="int",description="收款id")
	 *     ),
	 *       example={"id":"1"}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function deleteCharge(Request $request)
	{
		$msg = ['id.required' => '收款id必须'];
		$request->validate([
			'id' => 'required|numeric|gt:0',
		], $msg);

		$res = $this->chargeService->deleteCharge(($request->id));
		return $res ? $this->success("收款删除成功") : $this->error("收款删除失败");
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/charge/record/delete",
	 *     tags={"收支"},
	 *     summary="收支核销记录删除",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"id"},
	 *       @OA\Property(property="id",type="int",description="收款核销id")
	 *     ),
	 *       example={"id":"1"}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function deleteRecord(Request $request)
	{
		$msg = ['id.required' => '核销id必须'];
		$request->validate([
			'id' => 'required|numeric|gt:0',
		], $msg);

		$res = $this->chargeService->deleteChargeRecord(($request->id));
		return $res ? $this->success("核销删除成功") : $this->error("删除失败");
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/charge/refund",
	 *     tags={"收支"},
	 *     summary="收支退款",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"id","refund_amt"},
	 *       @OA\Property(property="id",type="int",description="id"),
	 *       @OA\Property(property="refund_amt",type="decimal",description="退款金额"),
	 *      @OA\Property(property="remark",type="String",description="退款备注"),
	 *     ),
	 *       example={"id":"1","refund_amt":"0.00","remark":""}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function chargeRefund(Request $request)
	{
		$msg = [
			'id.required' => '收款id必须',
			'refund_amt.required' => '退款金额必须',
		];
		$request->validate([
			'id' => 'required|numeric|gt:0',
			'refund_amt' => 'required',
			'remark' => 'String',
		], $msg);

		$charge = $this->chargeService->model()->findOrFail($request->id);
		if ($charge->type == AppEnum::chargeRefund) {
			return $this->error("支出不允许退款");
		}
		$unusedAmt = $charge->amount - $charge->verify_amount;
		if ($unusedAmt < $request->refund_amt) {
			return $this->error("退款金额不能大于可用金额");
		}
		$charge->remark = $request->remark ?? $charge->remark;
		$res = $this->chargeService->chargeRefund($charge->id, $request->refund_amt, $this->user);
		return $res ? $this->success("退款成功") : $this->error("退款失败");
	}
}
