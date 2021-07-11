<?php

namespace App\Api\Services\Tenant;


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Tenant\Tenant as TenantModel;
use App\Api\Models\Operation\Invoice as InvoiceModel;
use App\Api\Models\Tenant\Invoice;
use App\Api\Services\Company\VariableService;
use App\Enums\AppEnum;

/**
 *   租户服务
 */
class TenantService
{

  public function tenantModel()
  {
    $model = new TenantModel;
    return $model;
  }

  /** 合同审核后保存租户 或者新增 */
  public function saveTenant($DA, $user)
  {

    try {
      if (isset($DA['id']) && $DA['id'] > 0) {
        $tenant              = $this->tenantModel()->find($DA['id']);
      } else {
        $tenant              = $this->tenantModel();
        $tenant->c_uid       = $user['id'];
        $tenant->company_id  = $user['company_id'];
        $tenant->proj_id     = $DA['proj_id'];
        $tenant->tenant_no   = $this->getTenantNo($user['company_id']);
      }
      $tenant->u_uid         = $user['id'];
      $tenant->name          = $DA['name'];
      $tenant->parent_id     = isset($DA['parent_id']) ? $DA['parent_id'] : 0;
      $tenant->checkin_time  = isset($DA['checkin_time']) ? $DA['checkin_time'] : "";
      $tenant->heating_area  = isset($DA['heating_area']) ? $DA['heating_area'] : 0.00;
      $tenant->heating_amount = isset($DA['heating_amount']) ? $DA['heating_amount'] : 0.00;
      $tenant->business_id   = isset($DA['business_id']) ? $DA['business_id'] : 0;  // 工商信息id
      $tenant->industry      = isset($DA['industry']) ? $DA['industry'] : "";  // 行业
      $tenant->level         = isset($DA['level']) ? $DA['level'] : "";  // 租户级别
      $tenant->worker_num    = isset($DA['worker_num']) ? $DA['worker_num'] : 0;
      // $tenant->pay_method    = isset($DA['pay_method']) ? $DA['pay_method'] : 0;  // 收款方式
      $tenant->nature        = isset($DA['nature']) ? $DA['nature'] : "";
      $tenant->remark        = isset($DA['remark']) ? $DA['remark'] : "";
      $res = $tenant->save();
      if ($res) {
        return $tenant;
      }
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception("租户信息保存失败");
      return false;
    }
  }

  /** 检查租户是否重复 */
  public function tenantRepeat(array $map, $type = 'add')
  {
    if ($type == 'add') {
      $res = $this->tenantModel()->where($map)->first();
    } else {
      $res = $this->tenantModel()->where($map)
        ->where('id', '!=', $map['id'])
        ->first();
    }
    return $res;
  }

  /** 获取租户名称 */
  public function getTenantById($tenantId)
  {

    $tenant = $this->tenantModel()->find($tenantId);
    if ($tenant) {
      return $tenant->name;
    } else {
      return '公区';
    }
  }

  /**
   * 获取分摊租户的分摊信息
   * @Author   leezhua
   * @DateTime 2021-05-31
   * @param    [type]     $tenantId [description]
   * @return   [type]               [description]
   */
  // public function getShareByTenantId($tenantId)
  // {
  //   $share = $this->shareModel()->where('tenant_id',$tenantId)->get();
  //   if ($share) {
  //     foreach ($share as $k => &$v) {
  //       $v['name'] = $this->getTenantById($v['tenant_id']);
  //     }
  //   }
  //   return $share;
  // }

  /**
   * 新增或编辑客户发票信息
   * @Author   leezhua
   * @DateTime 2020-07-16
   * @param    [array]     $DA [description]
   * @return   [布尔]         [description]
   */
  public function saveInvoice($DA)
  {
    try {
      if (isset($DA['id']) && $DA['id'] > 0) {
        $invoice = Invoice::find($DA['id']);
      } else {
        $invoice = new Invoice;
        $invoice->tenant_id   = $DA['tenant_id'];
        $invoice->company_id  = $DA['company_id'];
        $invoice->proj_id     = $DA['proj_id'];
      }
      $invoice->title         = $DA['title'];
      $invoice->tax_number    = $DA['tax_number'];
      $invoice->bank_name     = isset($DA['bank_name']) ? $DA['bank_name'] : "";
      $invoice->account_name  = isset($DA['account_name']) ? $DA['account_name'] : "";
      $invoice->addr          = isset($DA['addr']) ? $DA['addr'] : "";
      $invoice->tel_number    = isset($DA['tel_number']) ? $DA['tel_number'] : "";
      $invoice->invoice_type  = isset($DA['invoice_type']) ? $DA['invoice_type'] : "";
      $res = $invoice->save();
      // Log::error($invoice);
      return $res;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception("发票保存失败");
      return false;
    }
  }



  private function formatFree($DA, $user, $contractId)
  {
    $BA = array();
    foreach ($DA as $k => $v) {
      $BA[$k]['company_id'] = $user['company_id'];
      $BA[$k]['c_uid']      = $user['id'];
      $BA[$k]['created_at'] = nowTime();
      $BA[$k]['tenant_id'] = $DA['tenant_id'];
      $BA[$k]['contract_id'] = $contractId;
      $BA[$k]['start_date'] = $DA['start_date'];
      $BA[$k]['end_date']   = $DA['end_date'];
      $BA[$k]['free_num']   = $DA['free_num'];
      $BA[$k]['remark']   = isset($DA['remark']) ? $DA['remark'] : "";
    }
    return $BA;
  }





  /** 根据客户ID 获取客户发票信息 */
  public function getInvoice($tenantId)
  {
    $invoice = InvoiceModel::where('tenant_id', $tenantId)->first();
    return $invoice;
  }


  /**
   * 通过Company id 获取客户编号
   *
   * @Author   leezhua
   * @DateTime 2020-06-06
   * @param    [type]     $companyId [description]
   * @return   [type]                [description]
   */
  public function getTenantNo($companyId)
  {
    $variableservice = new VariableService;
    return $variableservice->getTenantNo($companyId);
  }
}
