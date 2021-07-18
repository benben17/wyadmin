<?php

namespace App\Api\Services\Bill;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Tenant\Tenant as TenantModel;
use App\Api\Models\Bill\TenantBill as BillModel;
use App\Api\Models\Bill\TenantBillDetail as BillDetailModel;
use App\Api\Models\Company\BankAccount;
use App\Api\Models\Contract\Contract;
use App\Api\Models\Contract\ContractBill;

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
    $model = new BillDetailModel;
    return $model;
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
        $bill->proj_id   = $DA['proj_id'];
        $bill->c_uid     = $user['id'];
      }
      $bill->tenant_id   = $DA['tenant_id'];
      $bill->tenant_name = $DA['tenant_name'];
      $bill->bill_no     = isset($DA['bill_no']) ? $DA['bill_no'] : "";
      $bill->bill_title  = isset($DA['bill_title']) ? $DA['bill_title'] : "";
      $bill->bank_id     = isset($DA['bank_id']) ? $DA['bank_id'] : 0;
      $bill->amount       = isset($DA['amount']) ? $DA['amount'] : 0.00;
      $bill->charge_date = isset($DA['charge_date']) ? $DA['charge_date'] : ""; //收款日期
      $bill->remark      = isset($DA['remark']) ? $DA['remark'] : "";
      $res = $bill->save();
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
      if (isset($DA['id']) && $DA['id'] > 0) {
        $billDetail = $this->billDetailModel()->find($DA['id']);
        $billDetail->u_uid       = $user['id'];
      } else {
        $billDetail = $this->billDetailModel();
        $billDetail->c_uid       = $user['id'];
      }
      $billDetail->company_id  = $user['company_id'];
      $billDetail->proj_id     = $DA['proj_id'];
      $billDetail->contract_id = isset($DA['contract_id']) ? $DA['contract_id'] : 0;
      $billDetail->tenant_id   = isset($DA['tenant_id']) ? $DA['tenant_id'] : 0;
      $billDetail->tenant_name = isset($DA['tenant_name']) ? $DA['tenant_name'] : "";
      $billDetail->type        = isset($DA['type']) ? $DA['type'] : 1; // 1 收款 2 付款
      $billDetail->fee_type    = $DA['fee_type']; // 费用类型
      $billDetail->amount      = $DA['amount'];
      $billDetail->bank_id     = $this->getBankIdByContractId($DA['contract_id'], $DA['fee_type']);
      if (isset($DA['charge_date'])) {
        $billDetail->charge_date = $DA['charge_date']; //账单日期
      }
      $billDetail->receive_amount = isset($DA['receive_amount']) ? $DA['receive_amount'] : 0.00;
      $billDetail->discount_amount = isset($DA['discount_amount']) ? $DA['discount_amount'] : 0.00;
      if (isset($DA['receive_date'])) {
        $billDetail->receive_date = $DA['receive_date'];
      }
      $billDetail->bill_date = isset($DA['bill_date']) ? $DA['bill_date'] : "";
      $billDetail->status     = isset($DA['status']) ? $DA['status'] : 0;
      $billDetail->create_type = isset($DA['create_type']) ? $DA['create_type'] : 1;
      $billDetail->remark      = isset($DA['remark']) ? $DA['remark'] : "";
      return $billDetail->save();
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception("账单详细保存失败");
      return false;
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
        $depositBills = ContractBill::where('contract_id', $contractId)->where('type', 2)->get()->toArray();
        $data = $this->formatBillDetail($depositBills, $user, $projId);
        $this->billDetailModel()->addAll($data);
        // 保存后同步更新状态
        ContractBill::where('contract_id', $contractId)->where('type', 2)->update(['is_sync' => 1]);

        $startDate = date('Y-m-01', strtotime(nowYmd()));
        $endDate = date('Y-m-t', strtotime(nowYmd()));

        $bills = ContractBill::where('contract_id', $contractId)
          ->whereBetween('charge_date', [$startDate, $endDate])
          ->where('type', 1)->get()->toArray();
        $billData = $this->formatBillDetail($bills, $user, $projId);
        $this->billDetailModel()->addAll($billData);
        ContractBill::where('contract_id', $contractId)
          ->whereBetween('charge_date', [$startDate, $endDate])
          ->where('type', 1)->update(['is_sync' => 1]);
        // Log::error('格式化账单成功');
      }, 3);
      Log::error('账单保存成功');
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
      $bill->audit_user   = $DA['audit_user'];
      $bill->audit_uid    = $DA['audit_uid'];
      $bill->remark       = isset($DA['remark']) ? $DA['remark'] : "";
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
  public function createBill($contract, String $month = "", $feeType, $chargeDate, $user)
  {
    try {
      // DB::enableQueryLog();
      DB::transaction(function () use ($contract,  $month, $feeType, $chargeDate, $user) {
        // $startDate = date('Y-m-01', strtotime($month));
        // $endDate = date('Y-m-t', strtotime($month));
        $subQuery = $this->billDetailModel()->where('contract_id', $contract['id'])
          // ->whereBetween('charge_date', [$startDate, $endDate])
          ->where('status', 0)
          ->whereIn('fee_type', $feeType);
        $billSum = $subQuery->selectRaw('sum(amount) totalAmt,sum(discount_amount) discountAmt')
          ->groupBy('tenant_id')->first();
        Log::error("amount" . $billSum['totalAmt'] . "aa" . $billSum['discountAmt']);
        Log::error(response()->json(DB::getQueryLog()));
        $tenant = TenantModel::find($contract['tenant_id']);
        $billData['tenant_id'] = $contract['tenant_id'];
        $billData['amount'] = $billSum['totalAmt'] - $billSum['discountAmt'];
        $billData['charge_date'] = $chargeDate;
        $billData['proj_id'] = $tenant['proj_id'];
        $billData['tenant_name'] = $tenant['name'];
        $billData['bill_no']    = $this->billNo($month);
        $billData['bill_title'] = $tenant['name'];
        $bill = $this->saveBill($billData, $user);
        Log::error("账单ID------" . $bill['id']);
        $update['bill_id'] = $bill['id'];
        $this->billDetailModel()->where('contract_id', $contract['id'])
          // ->whereBetween('charge_date', [$startDate, $endDate])
          ->where('status', 0)
          ->whereIn('fee_type', $feeType)->update($update);
      }, 3);
      return true;
    } catch (Exception $e) {
      Log::error("生成账单失败" . $e);
      return false;
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
  private function formatBillDetail(array $DA, $user, int $projId)
  {
    $data = array();
    try {
      if ($DA && $user) {
        foreach ($DA as $k => &$v) {
          $data[$k]['company_id']  = $user['company_id'];
          $data[$k]['proj_id']     = $projId;
          $data[$k]['contract_id'] = $v['contract_id'];
          $data[$k]['tenant_id']   = $v['tenant_id'];
          if (!isset($v['tenant_name']) || !$v['tenant_name']) {
            $tenant = TenantModel::select('name')->find($v['tenant_id']);
            $tenantName = $tenant['name'];
          } else {
            $tenantName = $v['tenant_name'];
          }
          $data[$k]['bank_id']         = $this->getBankIdByContractId($v['contract_id'], $v['fee_type']);
          $data[$k]['tenant_name'] = $tenantName;
          $data[$k]['type']        = $v['type']; // 1 费用 2 押金
          $data[$k]['fee_type']    = $v['fee_type']; // 费用类型
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
      Log::error("格式化，租户账单失败" . $th->getMessage());
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
    $contract = Contract::select('rental_bank_id', 'manager_bank_id')->find($contractId);
    if ($feeType == 101 || $feeType == 107) {
      return $contract['rental_bank_id'];
    } else {
      return $contract['manager_bank_id'];
    }
  }
  /**
   * 生成账单编号
   *
   * @Author leezhua
   * @DateTime 2021-07-17
   * @param [type] $month
   *
   * @return void
   */
  private function billNo($month)
  {
    $no = dateFormat("ym", $month);
    return  $no . "-" . mt_rand(1000, 9999);
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
    $data = $this->billModel()->find($billId);
    if (!$data) {
      return "";
    }
    $billGroups = $this->billDetailModel()
      ->selectRaw('sum(amount) amount,sum(discount_amount) discount_amount,sum(receive_amount) receive_amount,bank_id')
      ->where('bill_id', $billId)->groupBy('bank_id')->get();
    foreach ($billGroups as $k => $v) {
      $v['bank_info']   = BankAccount::find($v['bank_id']);
      // $v['receivable_amount'] = numFormat($v['total_amount'] - $v['discount_amount']);
      $v['bill_detail'] = $this->billDetailModel()->where('bill_id', $billId)->where('bank_id', $v['bank_id'])->get();
    }
    $data['bills'] = $billGroups;
    $billCount = $this->billDetailModel()
      ->selectRaw('sum(amount) total_amount,sum(amount) discount_amount,sum(receive_amount) receive_amount')
      ->where('bill_id', $billId)->first();
    // $billCount['receivable_amount'] = "aaa";
    // numFormat($billCount['total_amount'] - $billCount['discount_amount']);
    $data['bill_count'] = $billCount;
    return $data;
  }
}
