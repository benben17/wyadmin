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
      $data = $this->formatRule($DA['share_list'], $user, $DA['contract_id'], $DA['share_type']);
      $res = $this->model()->addAll($data['data']);
      return $data;
    } catch (Exception $e) {
      Log::error("分摊规则保存失败！" . $e);
      throw new Exception("分摊规则保存失败！" . $e);
      return false;
    }
  }

  private function formatRule(array $shareList, $user, $contractId, $shareType)
  {
    $BA = array();
    $i = 0;
    $tenantIds = [];
    foreach ($shareList as $key => $rule) {
      foreach ($rule['share_rule'] as $k => $v) {
        $BA[$i]['company_id']    = $user['id'];
        $BA[$i]['c_uid']         = $user['id'];
        $BA[$i]['created_at']    = nowTime();
        $BA[$i]['updated_at']    = nowTime();
        $BA[$i]['bill_rule_id']  = $v['bill_rule_id'];
        $BA[$i]['contract_id']   = $contractId;
        $BA[$i]['share_type']    = $shareType;
        $BA[$i]['tenant_id']     = $rule['tenant_id'];
        $BA[$i]['fee_type']      = isset($v['fee_type']) ? $v['fee_type'] : 0;
        $BA[$i]['share_num']     = $v['share_num'];
        $BA[$i]['remark']    = isset($v['remark']) ? $v['remark'] : "";
        $i++;
      }
      array_push($tenantIds, $rule['tenant_id']);
    }

    Log::error(json_encode($BA));
    $res['data'] = $BA;
    $res['tenantIds'] = $tenantIds;
    Log::error(json_encode($tenantIds));
    return $res;
  }
}
