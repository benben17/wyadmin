<?php

namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Tenant\TenantShare as TenantShareModel;


/**
 *   租户服务
 */
class TenantShareService
{
  // /** 分摊模型 */
  public function shareModel()
  {
    $model = new TenantShareModel;
    return $model;
  }

  /** 保存分摊 */
  public function saveShare($DA, $user)
  {
    try {
      if (isset($DA['id']) && $DA['id'] > 0) {
        $share            = $this->shareModel()->find($DA['id']);
      } else {
        $share            = $this->shareModel();
      }
      $share->tenant_id   = $DA['parent_id'];
      $share->tenant_id   = $DA['name'];
      $share->fee_type    = $DA['fee_type'];
      $share->share_type  = $DA['share_type'];   // 分摊类型 1 面积 2 比例 3固定金额
      $share->share_num   = isset($DA['share_num']) ? $DA['share_num'] : 0.00; // 分摊数量
      $share->share_rate  = isset($DA['share_rate']) ? $DA['share_rate'] : 0;
      $share->share_amount = isset($DA['share_amount']) ? $DA['share_amount'] : 0.00;
      $share->remark      = isset($DA['remark']) ? $DA['remark'] : 0.00;
      $res = $share->save();
      return $res;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception("分摊保存失败");
      return false;
    }
  }
  /**
   * 解绑分摊租户，不影响其他账单信息
   * @Author   leezhua
   * @DateTime 2021-05-31
   * @param    [type]     $tenantId [description]
   * @return   [type]               [description]
   */
  public function unlinkShare($tenantId)
  {
    $data['parent_id'] = 0;
    return $this->shareModel()->where('tenant_id', $tenantId)->update($data);
  }
}
