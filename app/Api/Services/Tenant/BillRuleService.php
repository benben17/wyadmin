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
      } else {
        $rule = $this->model();
        $rule->c_uid      = $uid;
      }
      $rule->tenant_id    = $DA['tenant_id'];
      $rule->contract_id  = $DA['contract_id'];
      $rule->unit_price   = $DA['unit_price'];
      $rule->type         = $DA['type'];
      $rule->start_date   = $DA['start_date'];
      $rule->end_date     = $DA['end_date'];
      $rule->area_num     = $DA['area_num'];
      $rule->pay_method   = $DA['pay_method'];
      $rule->remark = isset($DA['remark']) ? $DA['remark'] : 0;
      $rule->save();
      return true;
    } catch (Exception $e) {
      Log::errror("租户账单规则写入失败" . $e->getMessage());
      return false;
    }
  }
}
