<?php

namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Tenant\Tenant as TenantModel;
use App\Api\Models\Bill\TenantBill as BillModel;
use App\Api\Models\Bill\TenantBillDetail as BillDetailModel;
use App\Api\Services\Energy\EnergyService;

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
      $bill->amount = isset($DA['amount']) ? $DA['amount'] : 0.00;;
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
      $billDetail->tenant_id   = isset($DA['tenant_id']) ? $DA['tenant_id'] : 0;
      $billDetail->tenant_name = isset($DA['tenant_name']) ? $DA['tenant_name'] : "";
      $billDetail->type        = isset($DA['type']) ? $DA['type'] : 1; // 1 收款 2 付款
      $billDetail->fee_type    = $DA['fee_type']; // 费用类型
      $billDetail->amount      = $DA['amount'];
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
      $billDetail->remark      = isset($DA['remark']) ? $DA['remark'] : "";
      return $billDetail->save();
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception("账单详细保存失败");
      return false;
    }
  }
  /**
   * 批量保存账单信息
   *
   * @Author leezhua
   * @DateTime 2021-07-11
   * @param array $DA
   * @param [type] $user
   * @param [type] $projId
   *
   * @return void
   */
  public function batchSaveBillDetail($DA, $user, int $projId)
  {
    if (!is_array($DA)) {
      $DA = $DA->toArray();
    }
    $data = $this->formatBillDetail($DA, $user, $projId);
    Log::error('格式化账单成功');
    return $this->billDetailModel()->addAll($data);
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
  public function createBill(int $tenantId, String $month = "", $feeType, $chargeDate, $user)
  {
    try {
      // DB::enableQueryLog();
      DB::transaction(function () use ($tenantId,  $month, $feeType, $chargeDate, $user) {
        $startDate = date('Y-m-01', strtotime($month));
        $endDate = date('Y-m-t', strtotime($month));
        $subQuery = $this->billDetailModel()->where('tenant_id', $tenantId)
          ->whereBetween('charge_date', [$startDate, $endDate])
          ->where('status', 0)
          ->whereIn('fee_type', $feeType);
        $billSum = $subQuery->selectRaw('sum(amount) totalAmt,sum(discount_amount) discountAmt')
          ->groupBy('tenant_id')->first();
        Log::error("amount" . $billSum['totalAmt'] . "aa" . $billSum['discountAmt']);
        Log::error(response()->json(DB::getQueryLog()));
        $tenant = TenantModel::find($tenantId);
        $billData['tenant_id'] = $tenantId;
        $billData['amount'] = $billSum['totalAmt'] - $billSum['discountAmt'];
        $billData['charge_date'] = $chargeDate;
        $billData['proj_id'] = $tenant['proj_id'];
        $billData['tenant_name'] = $tenant['name'];
        $billData['bill_no']    = $tenant['tenant_no'] . "-" . dateFormat("Ym", $month);
        $billData['bill_title'] = $tenant['name'];
        $bill = $this->saveBill($billData, $user);
        Log::error("账单ID------" . $bill['id']);
        $update['bill_id'] = $bill['id'];
        $this->billDetailModel()->where('tenant_id', $tenantId)
          ->whereBetween('charge_date', [$startDate, $endDate])
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
          $data[$k]['tenant_name'] = $tenantName;
          $data[$k]['type']        = $v['type']; // 1 费用 2 押金
          $data[$k]['fee_type']    = $v['fee_type']; // 费用类型
          $data[$k]['amount']      = $v['amount'];
          $data[$k]['charge_date'] = $v['charge_date'];
          $data[$k]['c_uid']       = $user['id'];
          $data[$k]['bill_date']   = isset($DA['bill_date']) ? $DA['bill_date'] : "";  // 收款区间
          $data[$k]['remark']      = isset($DA['remark']) ? $DA['remark'] : "";
          $data[$k]['created_at']  = nowTime();
        }
      }
      return $data;
    } catch (Exception $th) {
      Log::error("格式化，租户账单失败" . $th->getMessage());
      throw $th;
    }
  }
}
