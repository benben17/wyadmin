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

  /** 账单详情 */
  public function billDetailModel()
  {
    $model = new BillDetailModel;
    return $model;
  }
  // 账单
  public function billModel()
  {
    $model = new BillModel;
    return $model;
  }


  /**
   * 账单保存
   * @Author   leezhua
   * @DateTime 2021-05-31
   * @param    [type]     $DA [description]
   * @return   [type]         [description]
   */
  public function saveBillDetail($DA, $user)
  {
    try {
      $billDetail = $this->billDetailModel();
      $billDetail->company_id  = $user['company_id'];
      $billDetail->proj_id     = $DA['proj_id'];
      $billDetail->tenant_id   = $DA['tenant_id'];
      $billDetail->tenant_name = $DA['tenant_name'];
      $billDetail->type        = $DA['type']; // 1 收款 2 付款
      $billDetail->fee_type    = $DA['fee_type']; // 费用类型
      $billDetail->amount      = $DA['amount'];
      $billDetail->remark      = isset($DA['remark']) ? $DA['remark'] : "";
      $billDetail->c_uid       = $user['id'];
      $res = $billDetail->save();
      return $res;
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
   * 账单表头保存 
   */
  public function saveBill($DA)
  {
    try {
      $bill = $this->billModel();
      $bill->tenant_id   = $DA['tenant_id'];
      $bill->tenant_name = $DA['tenant_name'];
      $bill->type        = $DA['type']; // 1 收款 2 付款
      $bill->fee_type    = $DA['fee_type']; // 费用类型
      $bill->bank_id      = $DA['bank_id'];

      $bill->charge_amount = isset($DA['charge_amount']) ? $DA['charge_amount'] : 0.00;;
      $bill->charge_date = isset($DA['charge_date']) ? $DA['charge_date'] : ""; //收款日期
      $bill->remark      = isset($DA['remark']) ? $DA['remark'] : "";
      $res = $bill->save();
      return $res;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception("账单保存失败");
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
   * [创建账单]
   * @Author   leezhua
   * @DateTime 2020-08-10
   * @param    integer    $tenantId [租户ID]
   * @param    string     $month    [账单月份]
   * @return   [type]               [description]
   */
  public function createBill($tenantId = 0, $month = "")
  {

    return false;
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
        }
      }
      return $data;
    } catch (Exception $th) {
      Log::error("格式化，租户账单失败" . $th->getMessage());
      throw $th;
    }
  }

  /**
   * 账单核销
   *
   * @Author leezhua
   * @DateTime 2021-07-14
   * @param [type] $detailBill
   * @param [type] $chargeBill
   * @param [type] $verifyDate
   *
   * @return void
   */
  public  function detailBillVerify($detailBill, $chargeBill, $verifyDate)
  {
  }
}
