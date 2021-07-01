<?php
namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

use App\Api\Models\Tenant\TenantShare as TenantShareModel;
use App\Api\Models\Tenant\Tenant as TenantModel;
use App\Api\Models\Tenant\TenantBill as BillModel;
use App\Api\Models\Tenant\TenantBillDetail as BillDetailModel;
use App\Api\Models\Contract\Contract as ContractModel;
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
  public function saveBillDetail($DA){
    try{
      $billDetail = $this->BillDetailModel();
      $billDetail->company_id   = $DA['company_id'];
      $billDetail->proj_id      = $DA['proj_id'];
      $billDetail->tenant_id   = $DA['tenant_id'];
      $billDetail->tenant_name = $DA['tenant_name'];
      $billDetail->type        = $DA['type']; // 1 收款 2 付款
      $billDetail->fee_type    = $DA['fee_type']; // 费用类型
      $billDetail->amount      = $DA['amount'];
      $billDetail->remark      = isset($DA['remark']) ? $DA['remark']:"";
      $res = $billDetail->save();
      return $res;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception("账单详细保存失败");
      return false;
    }
  }

  /** 账单表头保存 */
  public function saveBill($DA){
    try{
      $billDetail = $this->BillModel();
      $billDetail->tenant_id   = $DA['tenant_id'];
      $billDetail->tenant_name = $DA['tenant_name'];
      $billDetail->type        = $DA['type']; // 1 收款 2 付款
      $billDetail->fee_type    = $DA['fee_type']; // 费用类型
      $billDetail->charge_amount= $DA['charge_amount'];
      $billDetail->charge_date = $DA['charge_date']; //收款日期
      $billDetail->remark      = isset($DA['remark']) ? $DA['remark']:"";
      $res = $billDetail->save();
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
  public function billAudit($DA){
    try{
      $bill = $this->BillModel()->find($DA['id']);
      $bill->tenant_id   = $DA['audit_user'];
      $bill->tenant_name = $DA['audit_uid'];
      $bill->type        = $DA['type']; // 1 收款 2 付款
      $bill->fee_type    = $DA['fee_type']; // 费用类型
      $bill->charge_amount = $DA['charge_amount'];
      $bill->charge_date = $DA['charge_date']; //收款日期
      $bill->remark      = isset($DA['remark']) ? $DA['remark']:"";
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
  public function createBill($tenantId=0,$month="")
  {

    $tenants = TenantShareModel::whereHas('contract',function ($q){
      $q->where('contract_state' ,2);
    })
    ->where(function ($q) use($tenantId,$month){
      if ($tenantId > 0) {
        $q->where('tenant_id',$tenantId);
      }
      if ($month) {
        $q->whereMonth('bill_month',dateFormat('m',$month)); // changed
        $q->whereYear('bill_month',dateFormat('Y',$month)); //
      }
    })
    ->with('contract')->get();
    // return $tenants;
    foreach ($tenants as $k => $tenant) {
      $contract = $tenant->contract;
      if($contract['rental_price_type'] == 1){
        $month_rental_amount = numFormat(($tenant->rental_num * $contract['rental_price']*365)/12);
      }else{
        $month_rental_amount = numFormat($tenant->rental_num * $contract['rental_price']);
      }
      $month_manager_amount = numFormat(($tenant->rental_num * $contract['management_price']*365)/12);

      // 水费
      $energy = new EnergyService;
      $water = $energy->getMeterRecord($tenantId,$month);
      return $water;
    }
  }

}