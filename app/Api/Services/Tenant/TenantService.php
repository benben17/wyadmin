<?php

namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

use App\Api\Models\Tenant\TenantShare as TenantShareModel;
use App\Api\Models\Tenant\Tenant as TenantModel;
use App\Api\Models\Operation\Invoice as InvoiceModel;
use App\Api\Models\Contract\ContractFreePeriod;
use App\Api\Services\Company\VariableService;
use App\Api\Models\Contract\Contract;
use App\Api\Models\Common\Contact as ContactModel;
use App\Api\Models\Customer\Customer as CustomerModel;
use App\Api\Services\Tenant\TenantContractService;
use App\Api\Models\Tenant\TenantLeaseback;
use App\Enums\AppEnum;

/**
 *   租户服务
 */
class TenantService
{

  public function leasebakcModel()
  {
    $model = new TenantLeaseback;
    return $model;
  }
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
      $tenant->pay_method    = isset($DA['pay_method']) ? $DA['pay_method'] : 0;  // 收款方式
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
        $invoice = InvoiceModel::find($DA['id']);
      } else {
        $invoice = new InvoiceModel;
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



  public function tenantSync($contractId, $user)
  {
    $contract = Contract::find($contractId);
    if (!$contract) {
      return false;
    }
    try {
      DB::transaction(function () use ($contract, $user) {
        $customer = CustomerModel::find($contract['customer_id']);
        $map['name'] = $customer['cus_name'];
        $map['company_id'] = $customer['company_id'];
        $checkTenant = $this->tenantRepeat($map);
        if (!$checkTenant) {
          $tenantModel = $this->tenantModel();
          $tenantModel->tenant_no   = $this->getTenantNo($user['company_id']);
          $tenantModel->company_id  = $user['company_id'];
          $tenantModel->c_uid       = $user['id'];
          $tenantModel->name        = $customer['cus_name'];
          $tenantModel->proj_id     = $customer['proj_id'];
          $tenantModel->business_id = $customer['business_info_id'];
          $tenantModel->industry    = $customer['cus_industry'];
          $tenantModel->level       = $customer['cus_level'];
          $tenantModel->worker_num  = $customer['cus_worker_num'];
          $tenantModel->on_rent     = 1; // 是否在租 1在租 0 其他
          $res = $tenantModel->save();
          $tenantId = $tenantModel->id;
        } else {
          $tenantId = $checkTenant->id;
        }

        //同步联系人
        Log::error($customer->id . "customerId");
        $user['parent_type']  = AppEnum::Tenant;
        $contacts = ContactModel::where('parent_type', AppEnum::Customer)->where('parent_id', $customer->id)->get();
        if ($contacts) {
          Log::error($contacts);
          $contactData = formatContact($contacts, $tenantId, $user, 1);
          $contact = new ContactModel;
          $contact->addAll($contactData);
        }
        // 同步合同
        $tenantContract = new TenantContractService;
        $contract['tenant_id'] = $tenantId;
        $tenantContract->save($contract, $user, "add");
        // 同步房间


        // 同步免租
        $cusFreePeriod = ContractFreePeriod::where('contract_id', $contract['id'])->get();
        // foreach($cusFreePeriod :)
        Contract::find($contract['id'])->update('is_sync', 1);
      });
      return true;
    } catch (Exception $e) {

      Log::error($e->getMessage());
      return false;
    }
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
