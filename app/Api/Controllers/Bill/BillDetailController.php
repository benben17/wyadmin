<?php

namespace App\Api\Controllers\Bill;

use App\Enums\AppEnum;
use App\Enums\ChargeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Api\Models\Company\FeeType;
use App\Api\Controllers\BaseController;
use App\Api\Services\Bill\ChargeService;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Contract\ContractService;

/**
 * @OA\Tag(
 *     name="应收",
 *     description="应收，以及账单明细"
 * )
 */

class BillDetailController extends BaseController
{

	private $billService;
	public function __construct()
	{
		parent::__construct();
		$this->billService = new TenantBillService;
	}
	/**
	 * @OA\Post(
	 *     path="/api/operation/tenant/bill/fee/list",
	 *     tags={"费用"},
	 *     summary="费用详细列表",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"pagesize","orderBy","order"},
	 *       @OA\Property(property="name",type="String",description="客户名称")
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

	public function list(Request $request)
	{

		$map = array();
		if ($request->tenant_id) {
			$map['tenant_id'] = $request->tenant_id;
		}
		// 排序字段
		if (!$request->orderBy) {
			$request->orderBy = 'charge_date';
		}
		// 排序方式desc 倒叙 asc 正序
		if (!$request->order) {
			$request->order = 'desc';
		}

		if (isset($request->status) && $request->status != "") {
			$map['status'] = $request->status;
		}

		DB::enableQueryLog();
		// $map['type'] =  AppEnum::feeType;
		$subQuery = $this->billService->billDetailModel()
			->where($map)
			->where(function ($q) use ($request) {
				$request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
				$request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
				$request->year && $q->whereYear('charge_date', $request->year);
				// if (!$request->start_date && !$request->end_date) {
				// 	$startDate = dateFormat('Y-01-01', nowYmd());
				// 	$endDate = dateFormat('Y-m-t', nowYmd());
				// 	$q->whereBetween('charge_date', [$startDate, $endDate]);
				// }
				$request->start_date && $q->where('charge_date', '>=', $request->start_date);
				$request->end_date && $q->where('charge_date', '<=', $request->end_date);
				$q->whereIn('type', [AppEnum::feeType, AppEnum::dailyFeeType]);
				$request->fee_types && $q->whereIn('fee_type', $request->fee_types);
				if (!empty($request->is_bill)) {
					$request->is_bill ? $q->where('bill_id', '>', 0) : $q->where('bill_id', 0);
				}
				$request->bank_id && $q->where('bank_id', $request->bank_id);
			});
		$pageQuery = clone $subQuery;
		$pageQuery->with('tenant:id,name');
		$data = $this->pageData($pageQuery, $request);
		foreach ($data['result'] as $k => &$v) {
			$v['tenant_name'] = $v['tenant']['name'];
			unset($v['tenant']);
		}
		$this->billService->detailListStat($subQuery, $data, $this->uid);

		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/tenant/bill/fee/show",
	 *     tags={"费用"},
	 *     summary="费用收款",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"id"},
	 *       @OA\Property(property="id",type="int",description="费用 id")
	 *     ),
	 *       example={"id":1}
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
			'id' => 'required|numeric|gt:0',
		], [
			'id.gt' => '费用ID必须大于0',
			'id.required' => '费用ID不能为空',
		]);
		DB::enableQueryLog();

		$data = $this->billService->billDetailModel()
			->with(['chargeBillRecord' => function ($q) {
				$q->with('charge:id,charge_date,flow_no,amount,c_uid,status');
			}])
			->with('billDetailLog')
			->with('contract:id,contract_no')
			->find($request->id);

		// $invoiceService = new InvoiceService;
		// $invoiceRecord = $invoiceService->invoiceRecordModel()->find($request->id);
		// if (!$invoiceRecord) {
		//   $invoiceRecord =   (object)array();
		// }
		// $data['invoice_record'] = $invoiceRecord;
		// return response()->json(DB::getQueryLog());
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/tenant/bill/fee/verify",
	 *     tags={"费用"},
	 *     summary="费用收款",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"bill_detail_id","charge_id"},
	 *       @OA\Property(property="bill_detail_id",type="int",description="账单费用id"),
	 *       @OA\Property(property="charge_id",type="float",description="收款单ID"),
	 *
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
	public function verify(Request $request)
	{
		$validatedData = $request->validate([
			'bill_detail_id' => 'required|numeric|gt:0',
			'charge_id' => 'required|gt:0',
			'verify_amount' => 'required',
		]);

		$billDetail = $this->billService->billDetailModel()
			->where('status', AppEnum::feeStatusUnReceive)
			// ->where('type', $feeTypes)
			->findOrFail($request->bill_detail_id);

		if (!$billDetail) {
			return $this->error("未发账单现数据！");
		}
		// 未生成账单不允许核销
		if ($billDetail->bill_id > 0) {
			return $this->error("此费用未生成账单，不能核销！");
		}

		$chargeService = new ChargeService;
		$chargeBill = $chargeService->model()
			->where('status', ChargeEnum::chargeUnVerify)
			->findOrFail($request->charge_id);
		if (!$chargeBill) {
			return $this->error("未发现充值数据！");
		}

		if ($chargeBill->bank_id != $billDetail->bank_id) {
			return $this->error("收款账户不一致！");
		}

		$unreceiveAmt = bcsub(bcsub($billDetail['amount'], $billDetail['receive_amount'], 2), $billDetail['discount_amount'], 2);
		if ($unreceiveAmt < $request->verify_amount) {
			return $this->error("核销金额大于未收款金额！");
		}
		$verifyDate = $request->verify_date ?? nowYmd();
		$chargeService = new ChargeService;
		$res = $chargeService->detailBillVerify($billDetail->toArray(), $chargeBill->toArray(), $request->verify_amount, $verifyDate, $this->user);
		if ($res) {
			return $this->success("核销成功");
		} else {
			return $this->error("核销失败");
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/tenant/bill/fee/add",
	 *     tags={"费用"},
	 *     summary="新增费用",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"charge_date","amount","tenant_id","fee_type","proj_id"},
	 *       @OA\Property(property="charge_date",type="int",description="收款日期"),
	 *       @OA\Property(property="amount",type="float",description="收款金额"),
	 *       @OA\Property(property="tenant_id",type="int",description="租户id"),
	 *       @OA\Property(property="tenant_name",type="string",description="租户名字"),
	 *       @OA\Property(property="fee_type",type="int",description="费用类型"),
	 *       @OA\Property(property="proj_id",type="int",description="项目ID"),
	 *
	 *     ),
	 *       example={"bill_detail":""}
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
		$request->validate([
			'charge_date' => 'required|date',
			'tenant_id' => 'required',
			'proj_id' => 'required',
			'amount' => 'required',
			'fee_type' => 'required',
		]);

		if (!$request->ignore) {
			$map['tenant_id'] = $request->tenant_id;
			$map['fee_type'] = $request->fee_type;
			$billDetail = $this->billService->billDetailModel()->where($map)
				->whereYear('charge_date', dateFormat("Y", $request->charge_date))
				->whereMonth('charge_date', dateFormat("m", $request->charge_date))->first();
			if ($billDetail) {
				return $this->error("此费用类型已存在，是否继续添加？");
			}
		}

		$DA = $request->toArray();
		if (!isset($DA['contract_id'])) {
			$contractService = new ContractService;
			$contract = $contractService->model()->where('tenant_id', $request->tenant_id)->first();
			if ($contract) {
				$DA['contract_id'] = $contract->id;
			}
		}

		if (!isset($DA['bank_id']) || !$DA['bank_id']) {
			$DA['bank_id'] = getBankIdByFeeType($DA['fee_type'], $DA['proj_id']);
			if ($DA['bank_id'] == 0) {
				return $this->error("费用未配置收款账户，请联系管理员配置银行账户！");
			}
		}

		$res = $this->billService->saveBillDetail($DA, $this->user);
		if (!$res) {
			return $this->error("新增费用失败!");
		}
		return $this->success("新增费用成功");
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/tenant/bill/fee/edit",
	 *     tags={"费用"},
	 *     summary="费用编辑",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"charge_date","amount","tenant_id","fee_type","proj_id"},
	 *       @OA\Property(property="id",type="int",description="费用ID"),
	 *       @OA\Property(property="charge_date",type="int",description="收款日期"),
	 *       @OA\Property(property="amount",type="float",description="收款金额"),
	 *       @OA\Property(property="tenant_id",type="int",description="租户id"),
	 *       @OA\Property(property="tenant_name",type="string",description="租户名字"),
	 *       @OA\Property(property="fee_type",type="int",description="费用类型"),
	 *       @OA\Property(property="proj_id",type="int",description="项目ID"),
	 *
	 *     ),
	 *       example={"bill_detail":""}
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
			'amount' => 'required|gt:0',
			'edit_reason' => 'required',
		]);

		$res = $this->billService->editBillDetail($request->toArray(), $this->user);
		if (!$res) {
			return $this->error("编辑费用失败!");
		}
		return $this->success("编辑费用成功");
	}

	/**
	 * @OA\Post(
	 *     path="/api/operation/tenant/bill/fee/del",
	 *     tags={"费用"},
	 *     summary="费用删除",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"Ids"},
	 *       @OA\Property(property="Ids",type="int",description="费用ID"),
	 *
	 *
	 *     ),
	 *       example={"Ids":"[]"}
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
			'ids' => 'required|array',
		]);
		$billService = new TenantBillService();
		$billDetailList = $billService->billDetailModel()
			->whereIn('id', $request->ids)->get()->toArray();

		$res = $billService->deleteDetail($billDetailList, $this->user);
		if (!$res) {
			return $this->error("删除费用失败!");
		}
		return $this->success("删除费用成功");
	}
}
