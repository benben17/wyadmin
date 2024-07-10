<?php

namespace App\Api\Services\Tenant;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function AlibabaCloud\Client\json;

use App\Api\Models\Bill\TenantShareFee;

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
      $where['tenant_id']   = $DA['tenant_id'];
      $where['contract_id'] = $DA['contract_id'];
      $share = $this->model()->where($where)->first();
      if ($share) {
        $share->u_uid     = $user['id'];
      } else {
        $share            = $this->model();
        $share->c_uid     = $user['id'];
        $share->company_id = $user['company_id'];
      }
      $share->parent_id   = $DA['parent_id'];
      $share->tenant_id   = $DA['tenant_id'];
      $share->contract_id   = $DA['contract_id'];
      // $share->fee_type    = $DA['fee_type'];

      // Log::info("feeListStrings", $feeListStrings);
      $share->fee_list = json_encode($DA['fee_list']);
      $share->remark   = isset($DA['remark']) ? $DA['remark'] : "";
      $res = $share->save();
      return $res;
    } catch (Exception $e) {
      Log::error("分摊明细保存失败" . $e->getMessage());
      throw new Exception("分摊明细保存失败");
      return false;
    }
  }

  /**
   * 根据主租户的id 查询分摊租户
   *
   * @Author leezhua
   * @DateTime 2024-03-12
   * @param [type] $tenantId
   *
   * @return void
   */
  public function getShareTenants($tenantId)
  {
    $data = $this->model()
      ->select('tenant_id', 'contract_id')
      ->where('parent_id', $tenantId)
      ->get()
      ->toArray();

    // 如果查询结果为空，直接返回空数组
    if (empty($data)) {
      return [];
    }

    // 使用引用方式来修改数组元素
    foreach ($data as &$item) {
      $item['tenant_name'] = getTenantNameById($item['tenant_id']);
    }

    return $data;
  }


  /**
   * 根据合同id获取分摊租户
   *
   * @Author leezhua
   * @DateTime 2024-03-12
   * @param [type] $contractId
   *
   * @return void
   */
  public function getShareTenantsByContractId($contractId)
  {
    $data = $this->model()
      ->select('tenant_id', 'contract_id', 'fee_list')
      ->whereColumn('parent_id', '!=', 'tenant_id')
      ->where('contract_id', $contractId)
      ->get()
      ->toArray();

    // 如果查询结果为空，直接返回空数组
    if (empty($data)) {
      return [];
    }

    // 使用数组映射函数来转换每个结果的 tenant_name
    $data = array_map(function ($item) {
      $item['tenant_name'] = getTenantNameById($item['tenant_id']);
      return $item;
    }, $data);

    return $data;
  }


  /**
   * 判断合同是否处理过分摊
   *
   * @Author leezhua
   * @DateTime 2024-03-13
   * @param [type] $contractId
   *
   * @return integer
   */
  public function isShare($contractId): int
  {
    return $this->model()->where('contract_id', $contractId)->count() > 0 ? 1 : 0;
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
