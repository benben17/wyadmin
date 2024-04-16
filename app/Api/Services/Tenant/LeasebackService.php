<?php

namespace App\Api\Services\Tenant;

use Exception;
use App\Enums\AppEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Tenant\Leaseback;
use App\Api\Services\Tenant\TenantService;
use App\Api\Services\Common\MessageService;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Contract\ContractService;

/**
 *   租户合同退租服务
 */
class LeasebackService
{
  /** 退租租户 */
  public function model()
  {
    return new Leaseback;
  }

  /**
   * [租户退租]
   * 2 写入退租原因 以及退租押金信息
   * 3、更新租户状态 1 租户 客户状态为退租租户
   * @Author   leezhua
   * @DateTime 2020-06-27
   * @param    Array      $DA  [description]
   * @param    user        $user [description]
   * @return   [布尔]          [description]
   */
  public function save($DA, $user, $type = 2)
  {
    try {
      DB::transaction(function () use ($DA, $user) {
        if (isset($DA['id']) && $DA['id'] > 0) {
          $leaseback = $this->model()->find($DA['id']);
          $leaseback->u_uid = $user['id'];
        } else {
          $leaseback = $this->model();
          $leaseback->c_uid = $user['id'];
        }
        // 更新租户状态
        $contractService = new ContractService;
        // $data['on_rent'] = 0;
        $tenantService = new TenantService;
        // 更新合同状态
        $contract = $contractService->model()->find($DA['contract_id']);
        $tenantId = $contract->tenant_id;
        $leaseback->tenant_id            = $tenantId;
        $leaseback->contract_id          = $DA['contract_id'];
        $leaseback->tenant_name          = getTenantNameById($tenantId);
        $leaseback->leaseback_date       = $DA['leaseback_date'];
        $leaseback->regaddr_change_date  = isset($DA['regaddr_change_date']) ? $DA['regaddr_change_date'] : "null";
        $leaseback->leaseback_reason     = isset($DA['leaseback_reason']) ? $DA['leaseback_reason'] : "";
        $leaseback->type                 = $DA['type'];
        $leaseback->is_settle            = isset($DA['is_settle']) ? $DA['is_settle'] : 1;
        $leaseback->company_id           = $contract->company_id;
        $leaseback->proj_id              = $contract->proj_id;
        $leaseback->remark               = isset($DA['remark']) ? $DA['remark'] : "";
        $leaseback->save();

        $contract->contract_state = AppEnum::contractLeaseBack;
        $contract->save();

        $contractCount = $contractService->model()
          ->where('tenant_id', $tenantId)
          ->where('contract_state', AppEnum::contractExecute)->count();

        if ($contractCount == 0) {
          $data['on_rent'] = 0;
          $data['status'] = AppEnum::Leaseback;
          $tenantService->tenantModel()->where('id', $tenantId)->update($data);
        }
        // 处理租户应收
        if (!empty($DA['fee_list'])) {
          $this->processTenantFee($DA['fee_list']);
        }

        // $shareService->model()->where('contract_id', $DA['contract_id'])->delete();
        $msgContent = $contract->tenant_name . "在" . $DA['leaseback_date'] . '完成退租';
        $this->sendMsg($contract->tenant_name . '租户退租', $msgContent, $user);
        // 保存日志
        $title = $contract->tenant_name . '租户退租';
        $logRemark = $contract->tenant_name . '在' . $DA['leaseback_date'] . '完成退租';
        $contractService->saveContractLog($contract, $user, $title, $logRemark);
      });
      return true;
    } catch (Exception $e) {
      Log::error("退租失败" . $e);
      throw new Exception("退租失败" . $e->getMessage());
      return false;
    }
  }

  // 处理退租租户应收
  public function processTenantFee($feeList)
  {
    $tenantBillService = new TenantBillService;
    foreach ($feeList as $fee) {
      if ($fee['is_valid'] == 0) {
        $tenantBillService->billDetailModel()->where('id', $fee['id'])->delete();
      }
    }
  }

  /** 发送通知消息 */
  private function sendMsg($title, $content, $user, $receiveUid = 0)
  {
    try {
      $msg = new MessageService;
      $DA['company_id']   =  $user['company_id'];
      $DA['title']        = $title;
      $DA['content']      = $content;
      $DA['receive_uid']  = $receiveUid;
      $msg->contractMsg($DA);
    } catch (Exception $e) {
      Log::error($e->getMessage());
    }
  }
}
