<?php

namespace App\Api\Services\Tenant;

use App\Api\Models\Contract\BillRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use PhpParser\Node\Stmt\TryCatch;

class BillRuleService
{
  public function model()
  {
    $model = new BillRule;
    return $model;
  }

  public function save($DA, $user)
  {
    try {
      if (isset($DA['id']) || $DA['id'] > 0) {
        $rule = $this->model()->find($DA['id']);
        $rule->u_uid    = $user['id'];
      } else {
        $rule = $this->model();
        $rule->c_uid    = $user['id'];
      }
      $rule->tenant_id   = $DA['tenant_id'];
      $rule->contract_id = $DA['contract_id'];
      $rule->fee_type    = $DA['fee_type'];
      $rule->unit_price  = $DA['unit_price'];
      $rule->price_type  = $DA['price_type'];
      $rule->start_date  = $DA['start_date'];
      $rule->end_date    = $DA['end_date'];
      $rule->area_num    = $DA['area_num'];
      $rule->pay_method  = $DA['pay_method'];
      $rule->bill_day    = $DA['bill_day'];
      $rule->amount      = isset($DA['amount']) ? $DA['amount'] : 0.00;
      $rule->month_amt      = $DA['mounth_amt'];
      $rule->ahead_pay_month = $DA['ahead_pay_month'];
      $rule->unit_price_label  = $DA['unit_price_label'];
      $rule->remark      = isset($DA['remark']) ? $DA['remark'] : 0;
      $rule->save();
      return true;
    } catch (Exception $e) {
      Log::errror("租户账单规则写入失败" . $e->getMessage());
      return false;
    }
  }
  /** 批量保存 */
  public function batchSave($DA, $user, $contractId, $tenantId)
  {
    try {
      $ruleData = $this->formatRuleData($DA, $user, $contractId, $tenantId);
      return $this->model()->addAll($ruleData);
    } catch (Exception $e) {
      throw $e;
      Log::error("费用规则保存失败." . $e->getMessage());
      return false;
    }
  }

  /**
   * 合同规则批量更新保存
   *
   * @param [type] $DA
   * @param [type] $user
   * @param [type] $contractId
   * @param [type] $tenantId
   * @return void
   */
  public function batchUpdate($DA, $user, $contractId, $tenantId)
  {
    try {
      DB::transaction(function () use ($DA, $user, $contractId, $tenantId) {
        $this->model()->where('contract_id', $contractId)->delete();
        $ruleData = $this->formatRuleData($DA, $user, $contractId, $tenantId);
        $this->model()->addAll($ruleData);
      });
      return true;
    } catch (Exception $e) {
      throw $e;
      Log::error("费用规则保存失败." . $e->getMessage());
      return false;
    }
  }

  /**
   * 格式化规则
   *
   * @param [type] $DA 规则数据
   * @param [type] $user
   * @param [type] $contractId
   * @param [type] $tenantId
   * @return void
   */
  function formatRuleData($DA, $user, $contractId, $tenantId)
  {
    try {
      foreach ($DA as $k => &$v) {
        $v['c_uid'] = $user['id'];
        $v['u_uid'] = $user['id'];
        $v['tenant_id'] = $tenantId;
        $v['contract_id'] = $contractId;
        $v['remark'] = isset($DA['remark']) ? $DA['remark'] : 0;
      }
    } catch (Exception $th) {
      Log::error('格式化账单规则失败' . $th->getMessage());
      throw $th;
    }
    return $DA;
  }
}
