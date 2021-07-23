<?php

namespace App\Api\Services\Tenant;

use App\Api\Models\Bill\TenantShareRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ShareRuleService
{
  /** 分摊规则 Model */
  public function model()
  {
    return new TenantShareRule;
  }

  /** 保存分摊 */
  public function saveShare($DA, $user)
  {
    try {
      if (isset($DA['id']) && $DA['id'] > 0) {
        $share            = $this->model()->find($DA['id']);
        $share->u_uid     = $user['id'];
      } else {
        $share            = $this->model();
        $share->c_uid     = $user['id'];
        $share->company_id = $user['company_id'];
      }
      $share->tenant_id   = $DA['tenant_id'];
      $share->fee_type    = $DA['fee_type'];
      $share->share_type  = $DA['share_type'];   // 分摊类型  1 比例 2固定金额
      $share->share_rate  = isset($DA['share_rate']) ? $DA['share_rate'] : 0;
      $share->share_amount = isset($DA['share_amount']) ? $DA['share_amount'] : 0.00;
      $share->remark      = isset($DA['remark']) ? $DA['remark'] : "";
      $res = $share->save();
      return $res;
    } catch (Exception $e) {
      Log::error("分摊规则保存失败" . $e);
      throw new Exception("分摊规则保存失败");
      return false;
    }
  }

  /** 批量保存分摊 */
  public function batchSaveShare($DA, $user)
  {
    try {
      $data = $this->formatRule($DA['share_rules'], $user, $DA['contract_id'], $DA['share_type']);
      $this->model()->addAll($data);
    } catch (Exception $e) {
      Log::error("分摊规则保存失败！" . $e);
      throw new Exception("分摊规则保存失败！" . $e);
      return false;
    }
  }

  private function formatRule(array $shareRules, $user, $contractId, $sharetype)
  {
    $BA = array();
    foreach ($shareRules as $k => $v) {
      $BA[$k]['company_id']    = $user['id'];
      $BA[$k]['c_uid']         = $user['id'];
      $BA[$k]['created_at']    = nowTime();
      $BA[$k]['updated_at']    = nowTime();
      $BA[$k]['bill_rule_id']  = $v['bill_rule_id'];
      $BA[$k]['contract_id']   = $contractId;
      $BA[$k]['share_type']    = $sharetype;
      $BA[$k]['tenant_id']     = $v['tenant_id'];
      $BA[$k]['fee_type']      = isset($v['fee_type']) ? $v['fee_type'] : 0;
      $BA[$k]['share_rate']    = isset($v['share_rate']) ? $v['share_rate'] : 0.00;
      $BA[$k]['share_amount']  = isset($v['share_amount']) ? $v['share_amount'] : 0.00;
      $BA[$k]['remark']    = isset($v['remark']) ? $v['remark'] : "";
    }
    return $BA;
  }
}
