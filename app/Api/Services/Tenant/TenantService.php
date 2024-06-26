<?php

namespace App\Api\Services\Tenant;


use Exception;
use App\Enums\AppEnum;
use App\Api\Models\Tenant\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Tenant\TenantLog;
use App\Api\Services\Company\VariableService;
use App\Api\Models\Tenant\Tenant as TenantModel;

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


  public function tenantLogModel()
  {
    return new TenantLog;
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
        $tenant->state = $DA['state'];
        $tenant->type = $DA['type'];
        $tenant->tenant_no   = $this->getTenantNo($user['company_id']);
      }
      $tenant->u_uid         = $user['id'];
      $tenant->name          = $DA['name'];
      $tenant->parent_id    = isset($DA['parent_id']) ? $DA['parent_id'] : 0;         // 上级租户
      $tenant->checkin_date = isset($DA['checkin_date']) ? $DA['checkin_date'] : "";  // 入驻日期
      $tenant->business_id  = isset($DA['business_id']) ? $DA['business_id'] : 0;     // 工商信息id
      $tenant->industry     = isset($DA['industry']) ? $DA['industry'] : "";          // 行业
      $tenant->level        = isset($DA['level']) ? $DA['level'] : "";                // 租户级别
      $tenant->worker_num   = isset($DA['worker_num']) ? $DA['worker_num'] : 0;
      $tenant->nature       = isset($DA['nature']) ? $DA['nature'] : "";
      $tenant->remark       = isset($DA['remark']) ? $DA['remark'] : "";
      $tenant->shop_name    = $DA['shop_name'] ?? "";
      $tenant->cpc_number   = $DA['cpc_number'] ?? 0;
      $tenant->tags         = isset($DA['tags']) ? $DA['tags'] : "";
      $tenant->addr         = isset($DA['addr']) ? $DA['addr'] : "";
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
  public function saveInvoice($DA, $user)
  {
    try {

      if (isset($DA['id']) && $DA['id'] > 0) {
        $invoice = Invoice::find($DA['id']);
      } else {
        $invoice = new Invoice;
        $invoice->tenant_id   = $DA['tenant_id'];
        $invoice->company_id  = $user['company_id'];
        $invoice->proj_id     = $DA['proj_id'];
      }
      $invoice->title         = isset($DA['title']) ? $DA['title'] : "";
      $invoice->tax_number    = isset($DA['tax_number']) ? $DA['tax_number'] : "";
      $invoice->bank_name     = isset($DA['bank_name']) ? $DA['bank_name'] : "";
      $invoice->account_name  = isset($DA['account_name']) ? $DA['account_name'] : "";
      $invoice->addr          = isset($DA['addr']) ? $DA['addr'] : "";
      $invoice->tel_number    = isset($DA['tel_number']) ? $DA['tel_number'] : "";
      $invoice->invoice_type  = isset($DA['invoice_type']) ? $DA['invoice_type'] : "";
      $res = $invoice->save();
      // Log::error(response()->json(DB::getQueryLog()));
      return $res;
    } catch (Exception $e) {
      Log::error("发票保存失败" . $e->getMessage());
      throw new Exception("发票保存失败");
      return false;
    }
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
    $variableService = new VariableService;
    return $variableService->getTenantNo($companyId);
  }


  /**
   * 保存租户日志
   *
   * @Author leezhua
   * @DateTime 2024-03-13
   * @param [type] $DA
   * @param [type] $user
   *
   * @return void
   */
  public function saveTenantLog($DA, $user)
  {
    try {
      $cusLog = $this->tenantLogModel();
      $cusLog->content    = $DA['content'];
      $cusLog->tenant_id  = $DA['tenant_id'];
      $cusLog->c_uid      = $user->id;
      $cusLog->c_username = $user->realname;
      $cusLog->company_id = $user->company_id;
      return $cusLog->save();
    } catch (Exception $th) {
      Log::error("保存客户log失败:" . $th->getMessage());
      throw $th;
      return false;
    }
  }


  public function deleteTenantById(int $tenantId)
  {
    try {
      DB::transaction(function () use ($tenantId) {

        $this->tenantLogModel()->where('tenant_id', $tenantId)->delete();
        $this->tenantModel()->where('id', $tenantId)->delete();
      });
    } catch (Exception $e) {
      Log::error("删除租户失败" . $e->getMessage());
      throw new Exception("删除租户失败");
      return false;
    }
  }
}
