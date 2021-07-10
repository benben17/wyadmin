<?php

namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

use App\Api\Models\Tenant\TenantShare as TenantShareModel;
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
      $billDetail = $this->BillDetailModel();
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
  // 批量保存账单信息
  public function saveAllBillDetail(array $DA, $user)
  {
    $data = $this->formatBillDetail($DA, $user);
    return $this->BillDetailModel()->addAll($data);
  }

  /** 
   * 账单表头保存 
   */
  public function saveBill($DA)
  {
    try {
      $bill = $this->BillModel();
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
      $bill = $this->BillModel()->find($DA['id']);
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
    $tenants = TenantShareModel::whereHas('contract', function ($q) {
      $q->where('contract_state', 2);
    })
      ->where(function ($q) use ($tenantId, $month) {
        if ($tenantId > 0) {
          $q->where('tenant_id', $tenantId);
        }
        if ($month) {
          $q->whereMonth('bill_month', dateFormat('m', $month)); // changed
          $q->whereYear('bill_month', dateFormat('Y', $month)); //
        }
      })
      ->with('contract')->get();
    // return $tenants;
    foreach ($tenants as $k => $tenant) {
      $contract = $tenant->contract;
      if ($contract['rental_price_type'] == 1) {
        $month_rental_amount = numFormat(($tenant->rental_num * $contract['rental_price'] * 365) / 12);
      } else {
        $month_rental_amount = numFormat($tenant->rental_num * $contract['rental_price']);
      }
      $month_manager_amount = numFormat(($tenant->rental_num * $contract['management_price'] * 365) / 12);

      // 水费
      $energy = new EnergyService;
      $water = $energy->getMeterRecord($tenantId, $month);
      return $water;
    }
  }

  public function formatBillDetail(array $DA, $user)
  {
    if ($DA && $user) {
      foreach ($DA as $k => &$v) {
        $v['company_id']  = $user['company_id'];
        $v['proj_id']     = $v['proj_id'];
        $v['tenant_id']   = $v['tenant_id'];
        $v['tenant_name'] = $v['tenant_name'];
        $v['type']        = $v['type']; // 1 收款 2 付款
        $v['fee_type']    = $v['fee_type']; // 费用类型
        $v['amount']      = $v['amount'];
        $v['remark']      = isset($DA['remark']) ? $DA['remark'] : "";
        $v['c_uid']       = $user['id'];
      }
      return $DA;
    } else {
      return false;
    }
  }
}
