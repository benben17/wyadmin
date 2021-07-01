<?php

namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Services\Tenant\TenantService;
use App\Api\Models\Tenant\TenantLeaseback;
use App\Api\Services\Common\MessageService;
use App\Api\Models\Operation\Invoice;
use App\Api\Models\Company\CompanyVariable;
use App\Api\Models\Tenant\Tenant;

/**
 *   租户退租服务
 */
class LeasebackService
{
  /** 退租租户 */
  public function leasebackModel()
  {
    $model = new TenantLeaseback;
    return $model;
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
  public function save($DA, $user)
  {
    try {
      DB::transaction(function () use ($DA, $user) {
        if (isset($DA['id']) && $DA['id'] > 0) {
          $leaseback = $this->leasebackModel()->find($DA['id']);
          $leaseback->u_uid = $user['id'];
        } else {
          $leaseback = $this->leasebackModel();
          $leaseback->c_uid = $user['id'];
        }
        $leaseback->tenant_id            = $DA['tenant_id'];
        $leaseback->tenant_name          = $DA['tenant_name'];
        $leaseback->leaseback_date       = $DA['leaseback_date'];
        $leaseback->regaddr_change_date  = isset($DA['regaddr_change_date']) ? $DA['regaddr_change_date'] : "null";
        $leaseback->leaseback_reason     = isset($DA['leaseback_reason']) ? $DA['leaseback_reason'] : "";
        $leaseback->type                 = $DA['type'];
        $leaseback->is_settle            = isset($DA['is_settle']) ? $DA['is_settle'] : 1;
        $leaseback->rental_deposit_back  = isset($DA['rental_deposit_back']) ? $DA['rental_deposit_back'] : 0;
        $leaseback->manager_deposit_back = isset($DA['manager_deposit_back']) ? $DA['manager_deposit_back'] : 0;
        $leaseback->remark               = isset($DA['remark']) ? $DA['remark'] : "";
        $leaseback->save();
        // 更新租户状态
        $tenantService = new TenantService;
        $data['on_rent'] = 0;
        $tenantService->tenantModel()->where('id', $DA['tenant_id'])->update($data);
        // 更新房源信息
        // $contractRoom = ContractRoomModel::selectRaw('group_concat(room_id) as room_id')->where('contract_id', $DA['id'])->first();
        // $roomService = new BuildingService;
        // $roomService->updateRoomState($contractRoom['room_id'], 1);  // 1房间可招商
        // 发送消息
        $msgContent = $DA['tenant_name'] . "在" . $DA['leaseback_date'] . '完成退租';
        $this->sendMsg($title = '租户退租', $msgContent, $user);
      });
      return true;
    } catch (Exception $e) {
      Log::error("退租失败" . $e->getMessage());
      return false;
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
