<?php

namespace App\Api\Services\Tenant;

use App\Api\Models\Tenant\BillRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class BillRuleService
{
  public function model()
  {
    $model = new BillRule;
    return $model;
  }

  public function save($DA, $uid)
  {
    try {
      if (isset($DA['id']) || $DA['id'] > 0) {
        $rule = $this->model()->find($DA['id']);
        $rule->u_uid    = $uid;
      } else {
        $rule = $this->model();
        $rule->c_uid    = $uid;
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
      $rule->bill_date   = $DA['bill_date'];
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
      $ruleData = formatRuleData($DA, $user, $contractId, $tenantId);
      return $this->model()->addAll($ruleData);
    } catch (Exception $th) {
      throw $th;
      Log::error("费用规则保存失败." . $th->getMessage());
      return false;
    }
  }

  public function formatRuleData($DA, $user, $contractId, $tenantId)
  {
    foreach ($DA as $k => &$v) {
      $v['remark'] = isset($DA['remark']) ? $DA['remark'] : 0;
      $v['c_uid'] = $user['id'];
      $v['u_uid'] = $user['id'];
      $v['tenant_id'] = $tenantId;
      $v['contract_id'] = $contractId;
    }
  }
}