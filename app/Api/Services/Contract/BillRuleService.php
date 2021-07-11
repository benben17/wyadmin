<?php

namespace App\Api\Services\Contract;

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
    $data = array();
    try {
      foreach ($DA as $k => $v) {
        Log::error(json_encode($v));
        $data[$k]['c_uid'] = $user['id'];
        $data[$k]['u_uid'] = $user['id'];
        $data[$k]['tenant_id'] = $tenantId;
        $data[$k]['contract_id'] = $contractId;
        $data[$k]['fee_type']    = $v['fee_type'];
        $data[$k]['unit_price']  = $v['unit_price'];
        $data[$k]['price_type']  = $v['price_type'];
        $data[$k]['start_date']  = $v['start_date'];
        $data[$k]['end_date']    = $v['end_date'];
        $data[$k]['area_num']    = $v['area_num'];
        $data[$k]['pay_method']  = $v['pay_method'];
        $data[$k]['bill_day']    = $v['bill_day'];
        $data[$k]['amount']      = isset($v['amount']) ? $v['amount'] : 0.00;
        $data[$k]['month_amt']      = $v['month_amt'];
        $data[$k]['ahead_pay_month'] = $v['ahead_pay_month'];
        $data[$k]['unit_price_label']  = isset($v['unit_price_label']) ? $v['unit_price_label'] : "";
        $data[$k]['remark']      = isset($v['remark']) ? $v['remark'] : "";
        $data[$k]['created_at']  = nowTime();
      }
    } catch (Exception $e) {
      Log::error('格式化账单规则失败' . $e->getMessage());
      throw $e;
    }
    return $data;
  }

  /**
   * 获取租金规则，会有免租的事情处理
   *
   * @param [int] $contractId
   * @return 对象
   */
  public function getRentRule($contractId)
  {
    $rentRule = $this->model()->where('contract_id', $contractId)->where('fee_type', 101)->first();
    return $rentRule;
  }
}
