<?php

namespace App\Api\Services\Company;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

use App\Api\Models\Customer\Customer as CustomerModel;
use App\Api\Models\Company\CompanyVariable;
use App\Api\Models\Customer\CustomerLeaseback as  CustomerLeasebackModel;
use App\Api\Models\Customer\CustomerFollow as CustomerFollowModel;
use App\Api\Models\Customer\CustomerRemind;
use App\Api\Models\Project as ProjectModel;

/**
 *
 */
class VariableService
{


  public function variableModel()
  {
    try {
      $model = new CompanyVariable;
      return $model;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception("模型获取失败！");
    }
  }

  /**
   * 通过company_id 获取客户编号
   *
   * @Author   leezhua
   * @DateTime 2020-06-06
   * @param    [type]     $companyId [description]
   * @return   [type]                [description]
   */
  public function getCustomerNo($companyId)
  {

    $res  = CompanyVariable::find($companyId);
    if (!$res) {
      $cus_prefix = 'CUS';
      $res = $this->saveVariable($companyId, $cus_prefix);
      $no = $res['cus_no'];
    } else {
      $data['cus_no'] = $res['cus_no'] + 1;
      $res->cus_no    = $res['cus_no'] + 1;
      $res->save();
      $no = $res['cus_no'];
    }
    $customerNo = $res['cus_prefix'] . $companyId . str_pad($no, 5, 0, STR_PAD_LEFT);
    return $customerNo;
  }


  /**
   * 客户编号前缀编辑
   * @Author   leezhua
   * @DateTime 2020-06-06
   * @param    [type]     $DA [description]
   * @return   [type]         [description]
   */
  public function saveCusNo($DA)
  {
    if (!isset($DA['company_id'])) {
      return false;
    }
    $customerVariables  = CompanyVariable::find($DA['company_id']);
    if (!$customerVariables) {
      $customerVariables = $this->saveVariable($DA['company_id'], $DA['cus_prefix']);
    } else {
      $customerVariables->cus_prefix = $DA['cus_prefix'];
      $res = $customerVariables->save();
    }
    return $customerVariables;
  }

  /** 保存公司使用的变量 */
  public function save($DA)
  {
    if (!$DA['company_id']) {
      return false;
    }
    $variable = CompanyVariable::find($DA['company_id']);
    $variable->contract_due_remind = $DA['contract_due_remind'];
    $res = $variable->save();
    return $res;
  }

  public function getCompanyVariable($companyId)
  {
    $data = CompanyVariable::find($companyId);
    return $data;
  }

  /** 保存客户编号 默认为CUS */
  private function saveVariable($companyId, $cus_prefix)
  {
    $variable = new CompanyVariable;
    $variable->no = 1;
    $variable->company_id = $companyId;
    $variable->cus_prefix = isset($cus_prefix) ? $cus_prefix : 'CUS';
    $variable->save();
    return $variable;
  }

  // 创建客户的时候 初始化 公司变量信息
  public function initCompanyVariable($companyId)
  {
    try {
      $row = CompanyVariable::find($companyId);
      if ($row) return false;

      $variable = $this->variableModel();
      $variable->company_id = $companyId;
      $variable->cus_prefix = 'CUS';
      $res = $variable->save();

      // 项目初始化
      $project = new ProjectModel;
      $project->proj_type = '办公园区';
      $project->proj_name = '默认项目';
      $project->company_id = $companyId;
      $project->is_vaild = 1;
      $project->save();
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception("初始化公司失败!");
    }
    return true;
  }

  /** 客户编辑公司变量 */
  public function editVariable($DA, $user)
  {
    $variable = $this->variableModel()->find($user['company_id']);
    $variable->cus_prefix           = $DA['cus_prefix'];
    $variable->tenant_prefix        = $DA['tenant_prefix'];
    $variable->contract_due_remind  = $DA['contract_due_remind'];
    $variable->msg_revoke_time      = $DA['msg_revoke_time'];
    $variable->contract_prefix      = $DA['contract_prefix'];
    $variable->days                 = $DA['days'];
    $variable->u_uid                = $user['id'];
    $res = $variable->save();
    return $res;
  }

  /** 得到租户编号 */
  public function getTenantNo($companyId)
  {
    $res  = CompanyVariable::find($companyId);
    if (!$res) {
      $tenant_prefix = '';
      $res = $this->saveVariable($companyId, $tenant_prefix);
      $no = $res['tenant_no'];
    } else {
      $res->tenant_no    = $res['tenant_no'] + 1;
      $res->save();
      $no = $res['tenant_no'];
    }
    $TenantNo = $res['tenant_prefix'] . $companyId . str_pad($no, 5, 0, STR_PAD_LEFT);
    return $TenantNo;
  }
}
