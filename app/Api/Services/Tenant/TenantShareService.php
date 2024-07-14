<?php

namespace App\Api\Services\Tenant;

use Exception;
use App\Enums\AppEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function AlibabaCloud\Client\json;
use App\Api\Models\Bill\TenantShareFee;
use App\Api\Services\Common\ContactService;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Contract\ContractService;

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
      // ->whereColumn('parent_id', '!=', 'tenant_id')
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
      $item['fee_list'] = json_decode($item['fee_list'], true);
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

  //#MARK: 分摊租户保存
  /**
   * @Desc:已分摊租户删除，已经核销的账单不做删除
   * @Author leezhua
   * @Date 2024-07-10
   * @param array $DA
   * @return void
   */
  public function delShareTenant(array $tenantShare, $user)
  {
    $DA = $tenantShare;
    try {
      DB::transaction(function () use ($DA, $user) {
        $billService = new TenantBillService();

        // status = 1 或者 receive_amount > 0 的账单
        $shareTenantBillsExists = $billService->billDetailModel()
          ->where('tenant_id', $DA['tenant_id'])
          ->where('contract_id', $DA['contract_id'])
          ->where(function ($query) {
            $query->where('status', AppEnum::feeStatusReceived)
              ->orWhere('receive_amount', '>', 0);
          })
          ->count() > 0;
        if ($shareTenantBillsExists) {
          throw new Exception("已有应收核销或者已收款！");
        }
        // 查询删除租户的应收账单
        $feeBills = $billService->billDetailModel()
          ->where('tenant_id', $DA['tenant_id'])
          ->where('contract_id', $DA['contract_id'])
          ->where('status', AppEnum::feeStatusUnReceive)
          ->where('receive_amount', '0') // 已经核销的账单不做删除
          ->get()->toArray();

        // 查询主租户分摊表
        $primaryTenantShare = $this->model()->where('tenant_id', $DA['parent_id'])
          ->where('contract_id', $DA['contract_id'])
          ->first()->pluck('fee_list');
        if ($primaryTenantShare->isEmpty()) {
          throw new Exception("未找到对应主租户分摊信息！");
        }
        $primaryTenantFees = json_decode($primaryTenantShare->fee_list, true);
        $billIds = [];
        if ($feeBills->isNotEmpty()) {
          foreach ($feeBills as $key => $feeBill) {
            $primaryTenantFee = $billService->billDetailModel()
              ->where('tenant_id', $DA['parent_id'])
              ->where('contract_id', $DA['contract_id'])
              ->where('fee_type', $feeBill['fee_type'])
              ->where('charge_date', $feeBill['charge_date'])
              ->first();
            if (!$primaryTenantFee) {
              Log::error("未找到对应主租户账单信息！写入一条新数据");
              $feeBill['tenant_id']   = $DA['parent_id'];
              $billService->saveBill($feeBill, $user); // 直接更新

            } else {
              // 找到主租户账单信息，更新主租户账单信息 主要是更新金额和状态
              $primaryTenantFee->amount = bcadd($primaryTenantFee->amount, $feeBill['amount'], 2);
              $primaryTenantFee->status = AppEnum::feeStatusUnReceive;
              $primaryTenantFee->save();
            }

            // 更新主租户分摊金额
            foreach ($primaryTenantFees as &$primaryFee) {
              if ($this->isMatchingFee($primaryTenantFee, $feeBill)) {
                $primaryFee['share_amount'] = bcsub($primaryFee['share_amount'], $feeBill['amount'], 2);
              }
            }
            unset($primaryFee);
            // 删除分摊租户的账单ID 集合
            $billIds[] = $feeBill['id'];
          }

          // 删除分摊租户的账单
          if (!empty($billIds)) {
            $billService->billDetailModel()->whereIn('id', $billIds)->delete();
            // 处理删除分摊表中的信息
            $this->model()->where('tenant_id', $DA['tenant_id'])->where('contract_id', $DA['contract_id'])->delete();

            // 更新主租户 分摊表信息
            $primaryTenantShare->fee_list = json_encode($primaryTenantFees);
            $primaryTenantShare->save();
          }
          // 保存合同日志
          $contractService = new ContractService;
          $BA['id'] = $DA['contract_id'];
          $BA['title'] = "删除分摊租户";
          $BA['c_uid'] = $user['id'];
          $BA['c_username'] = $user['realname'];
          $BA['remark'] = '删除分摊租户' . getTenantNameById($DA['tenant_id']);
          $contractService->saveLog($BA);
        }
      });
    } catch (Exception $e) {
      Log::error("分摊租户删除失败!" . $e->getMessage());
      throw new Exception("分摊租户删除失败!");
    }
  }



  private function isMatchingFee(array $primaryTenantFee, array $feeBill): bool
  {
    return $primaryTenantFee['fee_type'] == $feeBill['fee_type'] &&
      $primaryTenantFee['charge_date'] == $feeBill['charge_date'] &&
      $primaryTenantFee['contract_id'] == $feeBill['contract_id'];
  }
}
