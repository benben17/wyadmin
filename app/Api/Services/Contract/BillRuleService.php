<?php

namespace App\Api\Services\Contract;

use Exception;
use App\Enums\AppEnum;
use PhpParser\Node\Stmt\TryCatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Contract\BillRule;

class BillRuleService
{
  public function model()
  {
    $model = new BillRule;
    return $model;
  }

  // 检查递增规则
  public function validateIncrease(array $rule)
  {
    foreach ($rule as $k => $v) {
      if (
        $v['fee_type'] == AppEnum::rentFeeType &&
        !($v['increase_start_period'] && $v['increase_date']) &&
        ($v['increase_start_period'] || $v['increase_date'])
      ) {
        throw new Exception('租金规则中的递增周期或者递增日期不能为空');
      }
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
  public function ruleBatchSave($rule, $user, $contractId, $tenantId, $isSave = false)
  {
    try {
      DB::transaction(function () use ($rule, $user, $contractId, $tenantId, $isSave) {
        $this->validateIncrease($rule);
        if (!$isSave) {
          $this->model()->where('contract_id', $contractId)->delete();
        }
        // $this->model()->where('contract_id', $contractId)->delete();
        $ruleData = $this->formatRuleData($rule, $user, $contractId, $tenantId);
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
        // Log::error(json_encode($v));
        $data[$k]['c_uid']       = $user['id'];
        $data[$k]['u_uid']       = $user['id'];
        $data[$k]['tenant_id']   = $tenantId;
        $data[$k]['contract_id'] = $contractId;
        $data[$k]['type']        = $v['type'];
        $data[$k]['fee_type']    = isset($v['fee_type']) ? $v['fee_type'] : 1;
        $data[$k]['unit_price']  = isset($v['unit_price']) ? $v['unit_price'] : 0.00;
        $data[$k]['price_type']  = isset($v['price_type']) ? $v['price_type'] : 0;
        $data[$k]['start_date']  = $v['start_date'] ?? "";
        $data[$k]['bill_type']        = $v['bill_type'] ?? 1;
        $data[$k]['end_date']         = $v['end_date'] ?? "";
        $data[$k]['charge_date']      = $v['charge_date'] ?? "";
        $data[$k]['area_num']         = isset($v['area_num']) ? $v['area_num'] : 0.00;
        $data[$k]['lease_term']       = $v['lease_term'] ?? 0;
        $data[$k]['pay_method']       = isset($v['pay_method']) ? $v['pay_method'] : 0;
        $data[$k]['bill_day']         = isset($v['bill_day']) ? $v['bill_day'] : 0;
        $data[$k]['amount']           = isset($v['amount']) ? $v['amount'] : 0.00;
        $data[$k]['month_amt']        = isset($v['month_amt']) ? $v['month_amt'] : 0.00;
        $data[$k]['ahead_pay_month']  = isset($v['ahead_pay_month']) ? $v['ahead_pay_month'] : 0;
        $data[$k]['increase_show']    = $v['increase_show'] ?? 0;
        $data[$k]['increase_rate']    = $v['increase_rate'] ?? 0;
        $data[$k]['increase_date']    = $v['increase_date'] ?? "";
        $data[$k]['increase_start_period'] = $v['increase_start_period'] ?? 0;
        $data[$k]['unit_price_label'] = isset($v['unit_price_label']) ? $v['unit_price_label'] : "";
        $data[$k]['remark']           = isset($v['remark']) ? $v['remark'] : "";
        $data[$k]['is_valid']         = $v['is_valid'] ?? 1;                                          // 不传默认为有效
        $data[$k]['created_at']       = nowTime();
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
