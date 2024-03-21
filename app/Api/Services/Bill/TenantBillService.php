<?php

namespace App\Api\Services\Bill;

use App\Api\Models\Bill\ChargeBill;
use App\Api\Models\Bill\ChargeBillRecord;
use Exception;
use App\Enums\AppEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Contract\Contract;
use App\Api\Models\Company\BankAccount;
use Illuminate\Database\QueryException;
use App\Api\Models\Contract\ContractBill;
use App\Api\Models\Bill\TenantBillDetailLog;
use App\Api\Models\Bill\TenantBill as BillModel;
use App\Api\Models\Tenant\Tenant as TenantModel;
use App\Api\Models\Bill\TenantBillDetail as BillDetailModel;
use App\Api\Services\Contract\ContractService;

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
        $bill->proj_id   = $DA['proj_id'];
        $bill->c_uid     = $user['id'];
      }
      $bill->tenant_id   = $DA['tenant_id'];
      $bill->tenant_name = $DA['tenant_name'];
      $bill->contract_id = isset($DA['contract_id']) ? $DA['contract_id'] : 0;
      $bill->bill_no     = isset($DA['bill_no']) ? $DA['bill_no'] : "";
      $bill->bill_title  = isset($DA['bill_title']) ? $DA['bill_title'] : "";
      $bill->bank_id     = isset($DA['bank_id']) ? $DA['bank_id'] : 0;
      $bill->amount      = isset($DA['amount']) ? $DA['amount'] : 0.00;
      $bill->charge_date = isset($DA['charge_date']) ? $DA['charge_date'] : ""; //收款日期
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
          $billDetail->u_uid       = $user['id'];
        } else {
          $billDetail = $this->billDetailModel();
          $billDetail->c_uid       = $user['id'];
        }
        $billDetail->company_id  = $user['company_id'];
        $billDetail->proj_id     = $DA['proj_id'];
        $billDetail->contract_id = $DA['contract_id'] ?? 0;
        $billDetail->tenant_id   = isset($DA['tenant_id']) ? $DA['tenant_id'] : 0;
        $billDetail->tenant_name = getTenantNameById($billDetail->tenant_id);
        $billDetail->type        = isset($DA['type']) ? $DA['type'] : 1;
        $billDetail->bill_type   = isset($DA['bill_type']) ? $DA['bill_type'] : 1;
        $billDetail->fee_type    = $DA['fee_type']; // 费用类型
        $billDetail->amount      = $DA['amount'];
        $billDetail->bank_id     = $DA['bank_id'] ?? 0;
        if (isset($DA['contract_id']) && $billDetail->bank_id == 0) {
          $billDetail->bank_id     = $this->getBankIdByContractId($DA['contract_id'], $DA['fee_type']);
        }
        if ($billDetail->bank_id == 0) {
          $billDetail->bank_id = $this->getBankId($DA['proj_id'], $billDetail->fee_type);
        }
        if (isset($DA['charge_date'])) {
          $billDetail->charge_date = $DA['charge_date']; //账单日期
        }
        $billDetail->receive_amount = isset($DA['receive_amount']) ? $DA['receive_amount'] : 0.00;
        $billDetail->discount_amount = isset($DA['discount_amount']) ? $DA['discount_amount'] : 0.00;
        if (isset($DA['receive_date'])) {
          $billDetail->receive_date = $DA['receive_date'];
        }
        $billDetail->contract_bill_id = $DA['contract_bill_id'] ?? 0;
        $billDetail->bill_date  =  $DA['bill_date'] ?? $DA['charge_date'];
        $billDetail->status     = isset($DA['status']) ? $DA['status'] : 0;
        $billDetail->create_type = isset($DA['create_type']) ? $DA['create_type'] : 1;
        $billDetail->remark      = $DA['remark'] ?? "";
        // if ($DA['amount'] == 0 || $DA['amount'] == 0.00) {
        // }
        $billDetail->save();  // 费用等于0，写入数据
      }, 3);
      return true;
    } catch (Exception $e) {
      Log::error("租户账单费用详细保存失败" . $e);
      throw new Exception("租户账单费用详细保存失败" . $e);
      return false;
    }
  }


  /**
   * 通过费用id获取银行id
   *
   * @Author leezhua
   * @DateTime 2024-03-20
   * @param [type] $projId
   * @param [type] $feeId
   *
   * @return void
   */
  public function getBankId($feeType, int $projId)
  {
    $bank = BankAccount::whereRaw("FIND_IN_SET(?, fee_type_id)", [$feeType])->where('proj_id', $projId)->first();
    if ($bank) {
      return $bank->id;
    }
    return 0;
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
      $billDetail = $this->billDetailModel()->where('id', $detailId)->update($updateData);
    } catch (Exception $e) {
      Log::error("分享后账单更新失败!" . $e->getMessage());
      throw new Exception("分享后账单更新失败");
    }
  }

  public function editBillDetail($DA, $user)
  {
    try {
      DB::transaction(function () use ($DA, $user) {
        $billDetail = $this->billDetailModel()->find($DA['id']);
        if ($billDetail->receive_amount > 0 && $billDetail->receive_date) {
          throw new Exception("已有收款不允许编辑");
        }
        if ($billDetail->amount <= $billDetail->discount_amount) {
          throw new Exception("收款金额不允许小于等于优惠金额");
        }
        $billDetail->u_uid       = $user['id'];

        $this->saveBillDetailLog($billDetail, $DA, $user);
        $billDetail->amount = $DA['amount'];
        $billDetail->discount_amount = $DA['discount_amount'] ?? 0;
        $billDetail->fee_type = $billDetail['fee_type'];
        $billDetail->bank_id = $this->getBankId($billDetail->proj_id, $billDetail->fee_type);
        $billDetail->save();
      }, 2);
      return true;
    } catch (Exception $th) {
      Log::error("费用保存失败" . $th);
      throw new Exception("费用保存失败" . $th);
    }
  }
  public function saveBillDetailLog($billDetail, $DA, $user)
  {
    try {
      $detailLogModel = new TenantBillDetailLog;
      $detailLogModel->company_id     = $user['company_id'];
      $detailLogModel->amount         = $billDetail->amount;
      $detailLogModel->edit_amount    = $DA['amount'];
      $detailLogModel->discount_amount = $billDetail['discount_amount'] ?? 0;
      $detailLogModel->edit_discount_amount = $DA['discount_amount'] ?? 0;
      $detailLogModel->edit_reason    = isset($DA['edit_reason']) ? $DA['edit_reason'] : $DA['remark'];
      $detailLogModel->bill_detail_id = $DA['id'];
      $detailLogModel->edit_user      = $user->realname;
      $detailLogModel->c_uid          = $user->id;
      $detailLogModel->save();
    } catch (Exception $e) {
      Log::error("费用修改失败:" . $e);
      throw new Exception($e);
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
  public function createBill(array $tenant,  array $billDetails, $month, $billDay, $user): array
  {
    try {

      DB::transaction(function () use ($tenant, $billDetails, $month, $billDay, $user) {

        foreach ($billDetails as $k => $v) {
          // $tenantName = $v['tenant_name'] ?? getTenantNameById($v['tenant_id']);

          $billData = [
            // 'contract_id' => $contract['id'],
            'tenant_id' => $v['tenant_id'],
            'amount'    => $v['totalAmt'] - $v['discountAmt'],
            'charge_date' => $billDay,
            'proj_id' => $tenant['proj_id'],
            'tenant_name' => $tenant['name'],
            'bill_no' => date('Ymd', strtotime($billDay)) . mt_rand(1000, 9999),
            'bill_title' => $tenant['name'] . $month . "月账单",
          ];

          $bill = $this->saveBill($billData, $user);
          Log::error("bill_id" . $bill['id']);
          $billId = $bill['id'];

          $idArray = str2Array($v['billDetailIds']);
          $this->billDetailModel()->whereIn('id', $idArray)
            ->update(['bill_id' => $billId]);
        }
      }, 3);

      return ['flag' => true, 'message' => ''];
    } catch (QueryException $e) {
      Log::error("生成账单失败: " . $e->getMessage());
      // throw new Exception("生成账单失败: " . $e->getMessage());
      return ['flag' => false, 'message' => "生成账单失败: " . $e->getMessage()];
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
          $data[$k]['company_id']  = $user['company_id'];
          $data[$k]['proj_id']     = $projId === 0 ? $v['proj_id'] : $projId;
          $data[$k]['contract_id'] = $v['contract_id'];
          $data[$k]['tenant_id']   = $v['tenant_id'];
          if (!isset($v['tenant_name']) || !$v['tenant_name']) {
            $tenant = TenantModel::select('name')->find($v['tenant_id']);
            $tenantName = $tenant['name'];
          } else {
            $tenantName = $v['tenant_name'];
          }
          $data[$k]['contract_bill_id'] = $v['id'];
          $data[$k]['bank_id']     = $this->getBankIdByContractId($v['contract_id'], $v['fee_type']);
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
    $data = $this->billModel()->with('tenant:id,shop_name,tenant_no,name')->find($billId);
    if (!$data) {
      return (object) [];
    }
    $contractService = new ContractService;
    $data['room'] = $contractService->getContractRoom($data['contract_id']);
    $data['project'] = getProjById($data['proj_id']);

    $billGroups = $this->billDetailModel()
      ->selectRaw('sum(amount) amount,sum(discount_amount) discount_amount,sum(receive_amount) receive_amount,bank_id')
      ->where('bill_id', $billId)
      ->groupBy('bank_id')->get();

    $totalAmt = 0;
    $discountAmt = 0;
    $receiveAmt = 0;
    foreach ($billGroups as $k => &$v) {
      $v['bank_info']   = BankAccount::find($v['bank_id']);
      // $v['receivable_amount'] = numFormat($v['total_amount'] - $v['discount_amount']);
      $v['bill_detail'] = $this->billDetailModel()
        ->where('bill_id', $billId)
        ->where('bank_id', $v['bank_id'])->get();
      $totalAmt += $v['amount'];
      $discountAmt += $v['discount_amount'];
      $receiveAmt += $v['receive_amount'];
    }
    $data['bills'] = $billGroups;

    $billTotal['amount'] = numFormat($totalAmt - $discountAmt - $receiveAmt);
    $billTotal['discount_amount'] = $discountAmt;
    $billTotal['receive_amount']  = $receiveAmt;

    $billTotal['total_amount'] = $totalAmt;
    $data['bill_count'] = $billTotal;
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
            $chargeRecordList =  ChargeBillRecord::where('bill_detail_id', $detail['id'])->get();
            foreach ($chargeRecordList as $record) {
              // 收入增加金额
              $charge = ChargeBill::find($record['charge_id']);
              $charge->amount = $charge->amount + $record['amount'];
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
}
