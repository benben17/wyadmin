<?php

namespace App\Api\Services\Operation;

use Exception;
use App\Enums\AppEnum;

use App\Enums\ChargeEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Common\SmsService;
use App\Api\Services\Common\MessageService;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Models\Operation\WorkOrder as WorkOrderModel;
use App\Api\Models\Operation\WorkOrderLog as WorkOrderLogModel;

/**
 * 工单服务
 */
class WorkOrderService
{
  public function workModel()
  {
    return new WorkOrderModel;
  }

  /** 保修工单开单 */
  public function saveWorkOrder($DA, $user)
  {
    try {
      if (isset($DA['id']) && $DA['id'] > 0) {
        $order = $this->workModel()->find($DA['id']);
        $order->u_uid = $user['id'];
      } else {
        $order = $this->workModel();
        $order->company_id = $user['company_id'];
        $order->c_uid = $user['id'];
        $order->order_no = $DA['order_no'] ??  $this->workorderNo();
      }
      $order->proj_id         = $DA['proj_id'];
      $order->open_time       = $DA['open_time'] ?? nowTime();
      $order->urgency_level   = $DA['urgency_level'] ?? "";
      $order->tenant_id       = isset($DA['tenant_id']) ? $DA['tenant_id'] : 0;
      $order->tenant_name     = isset($DA['tenant_name']) ? $DA['tenant_name'] : "";
      $order->building_floor_room = isset($DA['building_floor_room']) ? $DA['building_floor_room'] : "";
      $order->build_floor_room_id = isset($DA['build_floor_room_id']) ? $DA['build_floor_room_id'] : 0;
      $order->position        = isset($DA['position']) ? $DA['position'] : "";
      $order->open_person     = isset($DA['open_person']) ? $DA['open_person'] : "";
      $order->repair_goods    = $DA['repair_goods'] ?? "";
      $order->open_phone      = isset($DA['open_phone']) ? $DA['open_phone'] : "";
      $order->repair_content  = isset($DA['repair_content']) ? $DA['repair_content'] : "";
      $order->pic             = isset($DA['pic']) ? $DA['pic'] : "";
      if (isset($DA['deadline_time'])) {
        $order->deadline_time   = $DA['deadline_time'];
      }
      $order->order_source    = isset($DA['order_source']) ? $DA['order_source'] : "";
      $order->status          = AppEnum::workorderOpen;  // 开单
      $res = $order->save();
      // Log::error(json_encode($order));
      if ($res) {
        $msg = new MessageService;
        $DA['title']    = '报修工单消息通知';
        $DA['content']  = $order->open_person . ' ' . nowTime() . ' 提交了一个报修工单,请及时处理！</br>' . $DA['repair_content'];
        $DA['role_id']  = '-1';
        $msg->sendMsg($DA, $user, 2);
        // 写入日志
        $this->saveOrderLog($order->id, 1, $user);
      }
    } catch (Exception $e) {
      Log::error($e->getMessage());
    }
    return $res;
  }


  /**
   * [接单处理]
   * @Author   leezhua
   * @DateTime 2020-07-15
   * @param    [array]     $DA   [提交数据]
   * @param    [type]     $user [用户信息]
   * @return   [type]           [布尔]
   */
  public function orderWork($DA, $user)
  {
    $order = $this->workModel()->find($DA['id']);
    $order->order_time   = $DA['order_time'];
    $order->order_uid    = $DA['order_uid'];
    $order->order_person = isset($DA['order_person']) ? $DA['order_person'] : $user['realname'];
    $order->status       = AppEnum::workorderTake;   // 接单
    $res = $order->save();
    // 写入日志
    $this->saveOrderLog($DA['id'], 2, $user);
    return $res;
  }


  /**
   * 工单处理
   * @Author   leezhua
   * @DateTime 2020-07-15
   * @param    [type]     $DA [description]
   * @return   [type]         [description]
   */
  public function processWorkOrder($DA, $user)
  {
    try {
      DB::transaction(function () use ($DA, $user) {
        $order = WorkOrderModel::find($DA['id']);
        if ($order->status != 2) {
          return false;
        }
        $order->process_result  = $DA['process_result'];
        $order->return_time     = $DA['return_time'];
        $order->time_used       = $DA['time_used'] ?? 0;
        $order->maintain_pic    = isset($DA['maintain_pic']) ? $DA['maintain_pic'] : "";
        $order->charge_amount   = isset($DA['charge_amount']) ? $DA['charge_amount'] : 0.00;
        $order->engineering_type = isset($DA['engineering_type']) ? $DA['engineering_type'] : "";
        $order->maintain_person = isset($DA['maintain_person']) ? $DA['maintain_person'] : "";
        $order->status          = AppEnum::workorderProcess; // 处理完成
        $order->is_notice       =  isset($DA['is_notice']) ? $DA['is_notice'] : 0;
        $order->remark          = $order['tenant_name'] . "-维修-" . $order['repair_content'];
        $order->save();

        // 发送短信通知
        if ($DA['is_notice'] && preg_match("/^1[3456789]\d{9}$/", $order['open_phone'])) {
          $parm['open_time']      = $order['open_time'];
          $parm['repair_content'] = $order['repair_content'];
          $smsService = new SmsService;
          $smsService->sendSms($order['open_phone'], $parm);
        }
        // 写入日志
        $this->saveOrderLog($DA['id'], $order->status, $user, $order->remark);
      }, 3);

      return true;
    } catch (Exception $e) {
      Log::error("处理失败" . $e->getMessage());
      throw new Exception("处理失败！");
      return false;
    }
  }

  /**
   * 保修工单撤销
   * @Author   leezhua
   * @DateTime 2020-07-15
   * @param    [type]     $DA [description]
   * @return   [type]         [description]
   */
  public function cancelWorkorder(int $orderId, $user, $remark = "")
  {
    $order = WorkOrderModel::find($orderId);
    if ($order->status >= 3) {
      return false;
    }
    $order->status = AppEnum::workorderCancel;
    $order->remark = $remark;
    $res = $order->save();
    // 写入日志
    $this->saveOrderLog($orderId, AppEnum::workorderCancel, $user, $order->remark);
    return $res;
  }



  /**
   * 工单关闭
   *
   * @Author leezhua
   * @DateTime 2024-01-30
   * @param [type] $DA
   * @param [type] $user
   *
   * @return void
   */
  public function closeWork($DA, $user)
  {
    try {
      DB::transaction(function () use ($DA, $user) {
        $order = $this->workModel()->find($DA['id']);

        $order->feedback = $DA['feedback'] ?? "";
        $order->feedback_rate = $DA['feedback_rate'] ?? 5;
        // $order->is_notice       = $DA['is_notice']; // 工单关闭
        if ($order->status != AppEnum::workorderClose && $order->status >= 3) {
          $order->status    = AppEnum::workorderClose; // 工单关闭
        } else {
          throw new Exception("工单状态不允许关闭，或者已经是关闭状态");
        }
        $order->save();

        // 保存费用
        $chargeAmount = $order->charge_amount;
        if ($chargeAmount > 0) {
          $billDetail = [
            'company_id'   => $order->company_id,
            'proj_id'      => $order->proj_id,
            'charge_date'  => nowYmd(),
            'tenant_id'    => $order->tenant_id,
            'tenant_name'  => $order->tenant_name,
            'type'         => ChargeEnum::Income, // 收款
            'fee_type'     => AppEnum::maintenanceFeeType,
            'bank_id'      => $DA['bank_id'],
            'amount'       => $chargeAmount,
            'remark'       => $order->order_no . "-" . $order->tenant_name . "-维修-" . $order->repair_content
          ];

          $billService = new TenantBillService;
          $billService->saveBillDetail($billDetail, $user);
        }
        // 写入日志
        $this->saveOrderLog($DA['id'], $order->status, $user, $order['remark']);
      }, 2);
      return true;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception($e->getMessage());
      return false;
    }
  }
  /** 生成工单编号 */
  private function workorderNo()
  {;
    return 'WS-' . date('ymdHis') . random_int(10, 99);
  }

  /**
   * 工单处理日志
   * @Author   leezhua
   * @DateTime 2020-07-24
   * @param    [type]     $orderId     [工单id]
   * @param    [type]     $orderStatus [工单状态]
   * @param    [type]     $user        [description]
   * @return   [type]                  [description]
   */
  public function saveOrderLog($orderId, $orderStatus, $user, $remark = "")
  {
    try {
      $orderLog = new WorkOrderLogModel;
      $orderLog->workorder_id = $orderId;
      $orderLog->status = $orderStatus;
      $orderLog->c_username = $user['realname'];
      $orderLog->c_uid = $user['id'];
      $orderLog->remark = $remark;
      // DB::enableQueryLog();

      $res = $orderLog->save();
      // Log::error(response()->json(DB::getQueryLog()));
    } catch (Exception $e) {
      Log::error($e->getMessage());
    }
    return $res;
  }
}
