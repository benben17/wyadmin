<?php

namespace App\Api\Services\Tenant;

use App\Api\Models\Bill\TenantShareFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

use function AlibabaCloud\Client\json;

class TenantShareService
{
  /** 分摊规则 Model */
  public function model()
  {
    return new TenantShareFee;
  }

  /** 保存分摊 */
  public function saveShareFee($DA, $user)
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
      $share->contract_id   = $DA['contract_id'];
      // $share->fee_type    = $DA['fee_type'];

      // Log::info("feeListStrings", $feeListStrings);
      $share->fee_list = json_encode($DA['fee_list']);
      $share->remark   = isset($DA['remark']) ? $DA['remark'] : "";
      $res = $share->save();
      return $res;
    } catch (Exception $e) {
      Log::error("分摊保存失败" . $e->getMessage());
      throw new Exception("分摊保存失败");
      return false;
    }
  }

  /** 批量保存分摊 */
  // public function batchSaveShare($DA, $user)
  // {
  //   try {
  //     $data = $this->formatRule($DA['share_list'], $user, $DA['contract_id'], $DA['share_type']);
  //     $res = $this->model()->addAll($data['data']);
  //     return $data;
  //   } catch (Exception $e) {
  //     Log::error("分摊规则保存失败！" . $e);
  //     throw new Exception("分摊规则保存失败！" . $e);
  //     return false;
  //   }
  // }

}