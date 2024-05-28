<?php

namespace App\Api\Services\Bill;

use Exception;
use App\Enums\AppEnum;
use App\Enums\ChargeEnum;
use Illuminate\Support\Facades\DB;
use App\Api\Models\Bill\ChargeBill;
use App\Api\Models\Company\FeeType;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Contract\Contract;
use App\Api\Models\Company\BankAccount;
use App\Api\Models\Bill\ChargeBillRecord;
use App\Api\Models\Contract\ContractBill;
use App\Api\Models\Bill\TenantBillDetailLog;
use App\Api\Services\Contract\ContractService;
use App\Api\Models\Bill\TenantBill as BillModel;
use App\Api\Models\Bill\TenantBillDetail as BillDetailModel;

/**
 *   租户账单服务
 */
class TenantBillService
{

	// 账单
	public function billModel()
	{
		return new BillModel;
	}
	/** 账单详情 */
	public function billDetailModel()
	{
		return new BillDetailModel;
	}

	public function chargeBillModel()
	{
		return new ChargeBill;
	}

	/**
	 * 账单表头保存
	 *
	 * @Author leezhua
	 * @DateTime 2021-07-17
	 * @param [type] $DA
	 *
	 * @return array
	 */
	public function saveBill($DA, $user)
	{
		try {
			if (isset($DA['id']) && $DA['id'] > 0) {
				$bill = $this->billModel()->find($DA['id']);
				$bill->u_uid = $user['id'];
			} else {
				$bill = $this->billModel();
				$bill->company_id = $user['company_id'];
				$bill->proj_id = $DA['proj_id'];
				$bill->c_uid = $user['id'];
			}
			$bill->tenant_id   = $DA['tenant_id'];
			$bill->tenant_name = $DA['tenant_name'];
			$bill->contract_id = isset($DA['contract_id']) ? $DA['contract_id'] : 0;
			$bill->bill_no     = isset($DA['bill_no']) ? $DA['bill_no'] : "";
			$bill->bill_title  = isset($DA['bill_title']) ? $DA['bill_title'] : "";
			$bill->bank_id     = isset($DA['bank_id']) ? $DA['bank_id'] : 0;
			$bill->amount      = isset($DA['amount']) ? $DA['amount'] : 0.00;
			$bill->charge_date = isset($DA['charge_date']) ? $DA['charge_date'] : "";  //收款日期
			$bill->remark      = isset($DA['remark']) ? $DA['remark'] : "";
			$bill->save();
			return $bill;
		} catch (Exception $e) {
			Log::error("账单保存失败" . $e);
			throw new Exception("账单保存失败" . $e);
			return false;
		}
	}

	/**
	 * 费用详细保存
	 * @Author   leezhua
	 * @DateTime 2021-05-31
	 * @param    [type]     $DA [description]
	 * @return   [type]         [description]
	 */
	public function saveBillDetail($DA, $user)
	{
		try {
			DB::transaction(function () use ($DA, $user) {
				if (isset($DA['id']) && $DA['id'] > 0) {
					$billDetail = $this->billDetailModel()->find($DA['id']);
					$billDetail->u_uid = $user['id'];
				} else {
					$billDetail             = $this->billDetailModel();
					$billDetail->c_uid      = $user['id'];
					$billDetail->fee_amount = $DA['amount'];
				}
				$billDetail->company_id  = $user['company_id'];
				$billDetail->proj_id     = $DA['proj_id'];
				$billDetail->contract_id = $DA['contract_id'] ?? 0;
				$billDetail->tenant_id   = $DA['tenant_id'];
				$billDetail->tenant_name = getTenantNameById($billDetail->tenant_id);
				$billDetail->type        = isset($DA['type']) ? $DA['type'] : 1;
				$billDetail->bill_type   = isset($DA['bill_type']) ? $DA['bill_type'] : 1;
				$billDetail->fee_type    = $DA['fee_type'];                                 // 费用类型
				$billDetail->amount      = $DA['amount'];
				// $billDetail->bank_id = $DA['bank_id'] ?? 0;
				// if ($billDetail->bank_id == 0) {
				$billDetail->bank_id = getBankIdByFeeType($DA['fee_type'], $DA['proj_id']);
				// }

				$billDetail->charge_date = $DA['charge_date'] ?? "";  //账单日期

				$billDetail->receive_amount   = isset($DA['receive_amount']) ? $DA['receive_amount'] : 0.00;
				$billDetail->discount_amount  = isset($DA['discount_amount']) ? $DA['discount_amount'] : 0.00;
				$billDetail->receive_date     = $DA['receive_date'] ?? "";
				$billDetail->contract_bill_id = $DA['contract_bill_id'] ?? 0;
				$billDetail->bill_date        = $DA['bill_date'] ?? $DA['charge_date'] . $DA['remark'] ?? "";
				$billDetail->status           = isset($DA['status']) ? $DA['status'] : 0;
				$billDetail->create_type      = isset($DA['create_type']) ? $DA['create_type'] : 1;
				$billDetail->remark           = $DA['remark'] ?? "";
				$billDetail->status						= $DA['status'] ?? 0;
				$billDetail->save(); // 费用等于0，写入数据
			}, 3);
			return true;
		} catch (Exception $e) {
			Log::error("租户账单费用详细保存失败" . $e);
			throw new Exception("租户账单费用详细保存失败" . $e);
			return false;
		}
	}

	/**
	 * 分享账单主表更新
	 *
	 * @Author leezhua
	 * @DateTime 2024-03-09
	 * @param integer $detailId
	 * @param [type] $updateData
	 *
	 * @return void
	 */
	public function updateShareBillDetail(int $detailId, $updateData)
	{
		try {
			$this->billDetailModel()->where('id', $detailId)->update($updateData);
		} catch (Exception $e) {
			Log::error("分享后账单更新失败!" . $e->getMessage());
			throw new Exception("分享后账单更新失败");
		}
	}

	/**
	 * 编辑账单明细
	 *
	 * @Author leezhua
	 * @DateTime 2024-03-27
	 * @param [type] $DA
	 * @param [type] $user
	 *
	 * @return void
	 */
	public function editBillDetail($DA, $user)
	{
		try {
			DB::transaction(function () use ($DA, $user) {
				$billDetail = $this->billDetailModel()->find($DA['id']);
				if (!$billDetail) {
					throw new Exception("费用不存在");
				}
				// 原有的应收金额
				$DA['old_amount']          = $billDetail->amount;
				$DA['old_discount_amount'] = $billDetail->discount_amount;
				// 已有收款不允许编辑
				if ($billDetail->receive_amount > 0 && $billDetail->receive_date) {
					throw new Exception("已有收款不允许编辑!");
				}
				// 收款区间
				if (isset($DA['bill_date']) && $DA['bill_date']) {
					$billDates = str2Array($DA['bill_date'], "至");
					if (sizeof($billDates) != 2) {
						throw new Exception("收款区间格式错误!");
						if (strtotime($billDates[0]) >= strtotime($billDates[1])) {
							throw new Exception("收款区间开始时间不能大于结束时间!");
						}
					}
					$billDetail->bill_date = $DA['bill_date'];
				}
				// 优惠金额不能大于应收金额
				$DA['discount_amount'] = $DA['discount_amount'] ?? $billDetail->discount_amount;
				if ($DA['discount_amount'] > $DA['amount']) {
					throw new Exception("优惠金额不能大于收款金额!");
				}

				$billDetail->u_uid           = $user['id'];
				$billDetail->charge_date     = $DA['charge_date'] ?? $billDetail->charge_date;
				$billDetail->amount          = $DA['amount'];
				$billDetail->discount_amount = $DA['discount_amount'] ?? $billDetail->discount_amount;
				if ($billDetail->bank_id == 0) {
					$billDetail->bank_id = getBankIdByFeeType($billDetail->fee_type, $billDetail->proj_id);
				}

				$billDetail->save();

				// 保存日志
				$this->saveBillDetailLog($DA, $user);
			}, 2);
			return true;
		} catch (Exception $th) {
			Log::error("费用保存失败" . $th);
			throw new Exception("费用保存失败" . $th->getMessage());
		}
	}

	public function saveBillDetailLog($DA, $user)
	{
		try {
			$detailLogModel                       = new TenantBillDetailLog;
			$detailLogModel->company_id           = $user['company_id'];
			$detailLogModel->amount               = $DA['old_amount'];
			$detailLogModel->edit_amount          = $DA['amount'];
			$detailLogModel->discount_amount      = $DA['old_discount_amount'] ?? 0;
			$detailLogModel->edit_discount_amount = $DA['discount_amount'] ?? 0;
			$detailLogModel->edit_reason          = isset($DA['edit_reason']) ? $DA['edit_reason'] : $DA['remark'];
			$detailLogModel->bill_detail_id       = $DA['id'];
			$detailLogModel->edit_user            = $user->realname;
			$detailLogModel->c_uid                = $user->id;
			$detailLogModel->save();
		} catch (Exception $e) {
			Log::error("费用调整日志保存失败:" . $e);
			throw new Exception("费用调整日志保存失败");
		}
	}
	/**
	 * 批量保存账单信息,合同审核同步账单
	 *
	 * @Author leezhua
	 * @DateTime 2021-07-11
	 * @param array $DA
	 * @param [type] $user
	 * @param [type] $projId
	 *
	 * @return void
	 */
	public function batchSaveBillDetail($contractId, $user, int $projId)
	{
		try {
			DB::transaction(function () use ($contractId, $user, $projId) {
				$feeList = ContractBill::where('contract_id', $contractId)->get()->toArray();
				$data = $this->formatBillDetail($feeList, $user, $projId);
				$this->billDetailModel()->addAll($data);
				// 保存后同步更新状态
				ContractBill::where('contract_id', $contractId)->update(['is_sync' => 1]);

				// $startDate = date('Y-m-01', strtotime(nowYmd()));
				// $endDate = date('Y-m-t', strtotime(nowYmd()));

				// $bills = ContractBill::where('contract_id', $contractId)
				//   ->whereBetween('charge_date', [$startDate, $endDate])
				//   ->where('type', 1)->get()->toArray();
				// $billData = $this->formatBillDetail($bills, $user, $projId);
				// $this->billDetailModel()->addAll($billData);
				// ContractBill::where('contract_id', $contractId)
				//   ->whereBetween('charge_date', [$startDate, $endDate])
				//   ->where('type', 1)->update(['is_sync' => 1]);
				// Log::error('格式化账单成功');
			}, 3);
			Log::info('租户应收账单保存成功');
			return true;
		} catch (Exception $th) {
			throw new Exception("账单保存失败" . $th);
			return false;
		}
	}

	/**
	 * 账单审核
	 * @Author   leezhua
	 * @DateTime 2021-05-31
	 * @param    [type]     $DA [description]
	 * @return   [type]         [description]
	 */
	public function billAudit($DA)
	{
		try {
			$bill = $this->billModel()->find($DA['id']);
			if (!$bill->audit_uid) {
				Log::error("已审核的账单不允许重复审核");
				return false;
			}
			$bill->audit_user = $DA['audit_user'];
			$bill->audit_uid = $DA['audit_uid'];
			$bill->remark = isset($DA['remark']) ? $DA['remark'] : "";
			$res = $bill->save();
			return $res;
		} catch (Exception $e) {
			Log::error($e->getMessage());
			throw new Exception("账单保存失败");
			return false;
		}
	}

	/**
	 * 创建账单
	 *
	 * @Author leezhua
	 * @DateTime 2021-07-17
	 * @param integer $tenantId
	 * @param String $month
	 * @param [type] $feeType
	 * @param [type] $chargeDate
	 * @param [type] $user
	 *
	 * @return void
	 */
	public function createBill(array $tenant, array $billDetails, $month, $billDay, $user): array
	{
		try {

			DB::transaction(function () use ($tenant, $billDetails, $month, $billDay, $user) {

				foreach ($billDetails as $k => $v) {
					// $tenantName = $v['tenant_name'] ?? getTenantNameById($v['tenant_id']);

					$billData = [
						// 'contract_id' => ,
						'tenant_id'   => $v['tenant_id'],
						'amount'      => $v['totalAmt'] - $v['discountAmt'],
						'charge_date' => $billDay,
						'proj_id'     => $tenant['proj_id'],
						'tenant_name' => $tenant['name'],
						'bill_no'     => date('Ymd', strtotime($billDay)) . mt_rand(1000, 9999),
						'bill_title'  => $tenant['name'] . $month . "月账单",
					];

					$bill = $this->saveBill($billData, $user);
					// Log::error("bill_id" . $bill['id']);
					$billId = $bill['id'];

					$idArray = str2Array($v['billDetailIds']);
					$this->billDetailModel()->whereIn('id', $idArray)
						->update(['bill_id' => $billId]);
				}
			}, 3);

			return ['flag' => true, 'message' => ''];
		} catch (Exception $e) {
			Log::error("生成账单失败: " . $e->getMessage());
			// throw $e;
			return ['flag' => false, 'message' => "生成账单失败: " . $e->getMessage()];
		}
	}

	/**
	 * 格式化账单明细
	 *
	 * @Author leezhua
	 * @DateTime 2021-07-11
	 * @param array $DA
	 * @param [type] $user
	 *
	 * @return void
	 */
	public function formatBillDetail(array $DA, $user, int $projId = 0)
	{
		$data = array();
		try {
			if ($DA && $user) {
				foreach ($DA as $k => $v) {
					if ($v['amount'] == 0) {
						continue;
					}
					$data[$k]['company_id']       = $user['company_id'];
					$data[$k]['proj_id']          = $projId === 0 ? $v['proj_id'] : $projId;
					$data[$k]['contract_id']      = $v['contract_id'];
					$data[$k]['tenant_id']        = $v['tenant_id'];
					$data[$k]['contract_bill_id'] = $v['id'];
					$data[$k]['bank_id']          = getBankIdByFeeType($v['fee_type'], $v['proj_id']);
					if (!isset($v['tenant_name']) || !$v['tenant_name']) {
						$tenantName = getTenantNameById($v['tenant_id']);
					} else {
						$tenantName = $v['tenant_name'];
					}
					$data[$k]['tenant_name'] = $tenantName;
					$data[$k]['type']        = $v['type'];             // 1 费用 2 押金
					$data[$k]['fee_type']    = $v['fee_type'];         // 费用类型
					$data[$k]['fee_amount']  = $v['amount']; // 费用金额只做展示
					$data[$k]['amount']      = $v['amount'];
					$data[$k]['charge_date'] = $v['charge_date'];
					$data[$k]['c_uid']       = $user['id'];
					$data[$k]['bill_date']   = isset($v['bill_date']) ? $v['bill_date'] : "";  // 收款区间
					$data[$k]['remark']      = isset($v['remark']) ? $v['remark'] : "";
					$data[$k]['created_at']  = nowTime();
				}
			}
			return $data;
		} catch (Exception $th) {
			Log::error("格式化，分享租户账单失败" . $th->getMessage());
			throw $th;
		}
	}

	/**
	 * 获取费用ID
	 *
	 * @Author leezhua
	 * @DateTime 2021-07-18
	 * @param [type] $contractId
	 * @param [type] $feeType
	 *
	 * @return integer
	 */
	private function getBankIdByContractId($contractId, $feeType): int
	{
		if (!$contractId || !$feeType) {
			return 0;
		}
		$contract = Contract::select('rental_bank_id', 'manager_bank_id')->find($contractId);
		if ($feeType == 101) {
			return $contract['rental_bank_id'];
		} else {
			return $contract['manager_bank_id'];
		}
	}

	/**
	 * 查看账单服务
	 *
	 * @Author leezhua
	 * @DateTime 2021-07-18
	 * @param [type] $billId
	 *
	 * @return void
	 */
	public function showBill($billId)
	{
		$data = $this->billModel()
			->select('id', 'tenant_id', 'bill_title', 'audit_user', 'bill_no', 'audit_date', 'is_print', 'status', 'proj_id', 'charge_date')
			->with('tenant:id,shop_name,tenant_no,name')->find($billId);
		if (!$data) {
			return (object) [];
		}
		$contractService = new ContractService;
		$data['room'] = $contractService->getContractRoomByTenantId($data['tenant_id']);
		$project = getProjById($data['proj_id']);
		$data['project'] = [
			'bill_title'          => $project['bill_title'],
			'bill_instruction'    => $project['bill_instruction'],
			'operate_entity'      => $project['operate_entity'],
			'bill_project' 				=> $project['bill_project'],
		];

		$billGroups = $this->billDetailModel()
			->selectRaw('bank_id,sum(amount) amount,status,
									sum(discount_amount) discount_amount,
									sum(receive_amount) receive_amount,
									sum(amount - receive_amount-discount_amount) unreceive_amount')
			->where('bill_id', $billId)
			->groupBy('bank_id')->get();

		foreach ($billGroups as $k => &$v) {
			$v['bank_info'] = BankAccount::select('account_name', 'account_number', 'bank_name')->find($v['bank_id']);
			$v['bill_detail'] = $this->billDetailModel()
				->select('amount', 'discount_amount', 'receive_amount', 'fee_type', 'charge_date', 'remark', 'bill_date')
				->where('bill_id', $billId)
				->where('bank_id', $v['bank_id'])->get();
		}
		$data['bills'] = $billGroups;
		return $data;
	}

	/**
	 * 删除应收
	 *
	 * @Author leezhua
	 * @DateTime 2024-03-14
	 * @param array $detailList
	 *
	 * @return boolean
	 */
	public function deleteDetail(array $detailList, $user): bool
	{
		try {
			DB::transaction(function () use ($detailList, $user) {
				foreach ($detailList as $detail) {
					// 如果是未收款 直接删除 如果已收款先处理收款信息
					if ($detail['receive_amount'] > 0) {
						$chargeRecordList = ChargeBillRecord::where('bill_detail_id', $detail['id'])->get();
						foreach ($chargeRecordList as $record) {
							// 收入增加金额
							$charge = ChargeBill::find($record['charge_id']);
							$charge->verify_amount = bcsub($$charge->verify_amount, $record['amount'], 2);
							if ($charge->status == ChargeEnum::chargeVerify) {
								$charge->status = ChargeEnum::chargeUnVerify;
							}
							$charge->save();
							// 删除 核销记录
							ChargeBillRecord::find($record['id'])->delete();
						}
					}
					$this->billDetailModel()->find($detail['id'])->delete();
				}
			}, 2);
			return true;
		} catch (Exception $e) {
			Log::error("删除应收失败" . $e->getMessage());
			return false;
		}
	}


	/**
	 * @Desc: 催交单查看 查询当前账单应收日之前未收款的所有费用信息
	 * @Author leezhua
	 * @Date 2024-05-17
	 * @param [type] $billId
	 * @return void
	 */
	public function showReminderBill($billId)

	{
		$data = $this->billModel()
			->select('id', 'tenant_id', 'bill_title', 'audit_user', 'bill_no', 'audit_date', 'is_print', 'status', 'proj_id', 'charge_date')
			->with('tenant:id,shop_name,tenant_no,name')->find($billId);
		if (!$data) {
			return (object) [];
		}
		$contractService = new ContractService;
		$data['room'] = $contractService->getContractRoomByTenantId($data['tenant_id']);
		$project = getProjById($data['proj_id']);
		$data['project'] = [
			'bill_title'          => $project['bill_title'],
			'bill_instruction'    => $project['bill_instruction'],
			'operate_entity'      => $project['operate_entity'],
			'bill_project' 				=> $project['bill_project'],
		];
		$where['tenant_id'] = $data['tenant_id'];
		$where['status'] = 0;
		$billGroups = $this->billDetailModel()
			->selectRaw('bank_id,sum(amount) amount,status,
										sum(discount_amount) discount_amount,
										sum(receive_amount) receive_amount,
										sum(amount - receive_amount-discount_amount) unreceive_amount')
			->where($where)
			->where('charge_date', '<=', date('Y-m-t', strtotime($data['charge_date'])))
			->groupBy('bank_id')->get();

		if ($billGroups->isEmpty()) {
			return $data;
		}

		foreach ($billGroups as $k => &$v) {
			$v['bank_info'] = BankAccount::select('account_name', 'account_number', 'bank_name')->find($v['bank_id']);
			$v['bill_detail'] = $this->billDetailModel()
				->select('amount', 'discount_amount', 'receive_amount', 'fee_type', 'charge_date', 'remark', 'bill_date')
				->where($where)
				->where('charge_date', '<=', date('Y-m-t', strtotime($data['charge_date'])))
				->where('bank_id', $v['bank_id'])->get();
		}
		$data['bills'] = $billGroups;
		return $data;
	}

	/**
	 * 处理退租的应收
	 * @Author   leezhua
	 * @DateTime 2020-06-27
	 * @param    [array]     	$feeList [费用列表]
	 * @param    [date]     	$leasebackDate [退租日期]
	 * @return   [	`]      [处理后的费用列表]
	 */
	public function processLeaseBackFee($feeList, $leasebackDate)
	{
		if (!$leasebackDate) {
			return $feeList;
		}
		$feeList = $feeList->toArray();
		$leasebackDate = strtotime($leasebackDate);
		foreach ($feeList as &$bill) {
			$is_valid = 1;
			// $is_valid_label = "有效";
			$billDate = str2Array($bill['bill_date'], "至");
			if (count($billDate) != 2) {
				continue;
			}
			$billStartTime = strtotime($billDate[0]);
			$billEndTime = strtotime($billDate[1]);
			// 判断  退租的日期 小于账单开始时间
			if ($leasebackDate < $billStartTime) {
				$is_valid = 0;
				// $is_valid_label = "系统删除";
			}
			// 退租日期大于账单开始时间时间 并且小于账单结束时间
			// else if ($leasebackDate > $billStartTime && $leasebackDate < $billEndTime) {
			// 	$is_valid = 1;
			// }
			$bill['is_valid'] = $is_valid;
			// $bill['is_valid_label'] = $is_valid_label;
		}
		return $feeList;
	}


	/**
	 * 应收list 统计
	 * @Author leezhua
	 * @Date 2024-04-18
	 * @param mixed $subQuery 
	 * @param mixed $data 
	 * @param mixed $uid 
	 * @return void 
	 */
	public function detailListStat($subQuery, &$data, $uid)
	{
		$feeStat = FeeType::selectRaw('fee_name,id,type')
			->where('type', AppEnum::feeType)
			->whereIn('company_id', getCompanyIds($uid))->get()->toArray();

		$feeCount = $subQuery
			->selectRaw('ifnull(sum(fee_amount),0.00) fee_amt,
        ifnull(sum(amount),0.00) total_amt,ifnull(sum(receive_amount),0.00) receive_amt,
        ifnull(sum(discount_amount),0.00) as discount_amt ,fee_type')
			->groupBy('fee_type')->get()
			->keyBy('fee_type');

		$emptyFee = ["total_amt" => 0.00, "receive_amt" => 0.00, "discount_amt" => 0.00, "unreceive_amt" => 0.00, "fee_amt" => 0.00, "fee_type" => 0];
		$statData = $emptyFee;

		foreach ($feeStat as $k => &$fee) {
			$fee = array_merge($fee, $emptyFee);
			if (isset($feeCount[$fee['id']])) {
				$v1 = $feeCount[$fee['id']];
				$fee['fee_amt']        = $v1->fee_amt;
				$fee['total_amt']      = $v1->total_amt;
				$fee['receive_amt']    = $v1->receive_amt;
				$fee['discount_amt']   = $v1->discount_amt;
				$fee['unreceive_amt']  = bcsub(bcsub($fee['total_amt'], $fee['receive_amt'], 2), $fee['discount_amt'], 2);
			}
			$statData['fee_amt']       = $statData['fee_amt'] + $fee['fee_amt'];
			$statData['total_amt']     = $statData['total_amt'] + $fee['total_amt'];
			$statData['receive_amt']   = $statData['receive_amt'] + $fee['receive_amt'];
			$statData['discount_amt']  = $statData['discount_amt'] + $fee['discount_amt'];
			$statData['unreceive_amt'] = $statData['unreceive_amt'] + $fee['unreceive_amt'];
		}
		$data['total'] = num_format($statData);
		$data['stat']  = num_format($feeStat);
	}
}
