<?php

namespace App\Api\Services\Operation;

use Exception;
use App\Enums\AppEnum;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Common\SmsService;
use App\Api\Services\Common\MessageService;
use App\Api\Models\Operation\YhWorkOrder as YhWorkOrderModel;
use App\Api\Models\Operation\WorkOrderLog as WorkOrderLogModel;

/**
 * 工单服务
 */
class YhWorkOrderService
{
  public function yhWorkModel()
  {
    return new YhWorkOrderModel;
  }

  /** 隐患工单开单 */
  public function saveYhWorkOrder($DA, $user)
  {
    try {
      if (isset($DA['id']) && $DA['id'] > 0) {
        $order = $this->yhWorkModel()->find($DA['id']);
        $order->u_uid = $user['id'];
      } else {
        $order = $this->yhWorkModel();
        $order->company_id = $user['company_id'];
        $order->c_uid = $user['id'];
      }
      $order->order_no       = $DA['order_no'] ?? $this->yhWorkorderNo();
      $order->proj_id        = $DA['proj_id'];
      $order->hazard_type    = $DA['hazard_type'] ?? "";
      $order->hazard_level   = $DA['hazard_level'] ?? "";
      $order->tenant_id      = $DA['tenant_id'] ?? 0;
      $order->tenant_name    = $DA['tenant_name'] ?? "";
      $order->process_type   = $DA['process_type'] ?? "立即整改";
      $order->deadline_time  = $DA['deadline_time'] ?? "";
      $order->position       = $DA['position'] ?? "";
      $order->open_person    = $DA['open_person'] ?? "";
      $order->check_type     = $DA['check_type'] ?? "";
      $order->user_in_charge = $DA['user_in_charge'] ?? "";
      $order->open_phone     = $DA['open_phone'] ?? "";
      $order->open_time      = $DA['open_time'] ?? nowTime();
      $order->hazard_issues  = $DA['hazard_issues'] ?? "";
      $order->pic            = $DA['pic'] ?? "";
      if (isset($DA['deadline_time'])) {
        $order->deadline_time   = $DA['deadline_time'];
      }
      // $order->order_source    = isset($DA['order_source']) ? $DA['order_source'] : "";
      $order->status          = AppEnum::workorderOpen;  // 开单
      $res = $order->save();
      // Log::error(json_encode($order));
      if ($res) {
        $msg = new MessageService;
        $DA['title']    = '报修工单消息通知';
        $DA['content']  = $order->open_person . ' ' . nowTime() . ' 提交了一个隐患工单,请及时处理！</br>' . $DA['hazard_issues'];
        $DA['role_id']  = '-1';
        $msg->sendMsg($DA, $user, 2);
        // 写入日志
        $this->saveYhOrderLog($order->id, 1, $user);
      }
      return true;
    } catch (Exception $e) {
      throw new Exception("隐患工单开单失败");
      Log::error($e->getMessage());
    }
  }


  /**
   * [隐患工单接单处理]
   * @Author   leezhua
   * @DateTime 2020-07-15
   * @param    [array]     $DA   [提交数据]
   * @param    [type]     $user [用户信息]
   * @return   [type]           [布尔]
   */
  public function orderWork($DA, $user)
  {
    $order = $this->yhWorkModel()->find($DA['id']);
    $order->order_time   = $DA['order_time'];
    $order->order_uid    = $DA['order_uid'];
    $order->order_person = isset($DA['order_person']) ? $DA['order_person'] : $user['realname'];
    $order->status       = AppEnum::workorderTake;   // 接单
    $res = $order->save();
    // 写入日志
    $this->saveYhOrderLog($DA['id'], 2, $user);
    return $res;
  }


  /**
   * 隐患工单-处理
   * @Author   leezhua
   * @DateTime 2020-07-15
   * @param    [type]     $DA [description]
   * @return   [type]         [description]
   */
  public function processWorkOrder($DA, $user)
  {
    try {
      DB::transaction(function () use ($DA, $user) {
        $order = $this->yhWorkModel()->find($DA['id']);
        if ($order->status != 2) {
          return false;
        }

        $order->process_result = $DA['process_result'];
        $order->process_time   = $DA['process_time'];
        $order->time_used      = $DA['time_used'] ?? 0;
        $order->process_pic    = isset($DA['process_pic']) ? $DA['process_pic'] : "";
        $order->process_status = $DA['process_status'];
        $order->process_user   = isset($DA['process_user']) ? $DA['process_user'] : "";
        $order->status         = AppEnum::workorderProcess; // 处理完成
        $order->is_notice      =  isset($DA['is_notice']) ? $DA['is_notice'] : 0;
        $order->remark         = "【" . $user->name . "】-处理-" . $order['hazard_issues'];
        $order->save();


        // 发送短信通知
        if (isset($DA['is_notice']) && preg_match("/^1[3456789]\d{9}$/", $order['open_phone'])) {
          $parm['open_time']      = $order['open_time'];
          $parm['hazard_issues'] = $order['hazard_issues'];
          $smsService = new SmsService;
          $smsService->sendSms($order['open_phone'], $parm);
        }
        // 写入日志
        $this->saveYhOrderLog($DA['id'], $order->status, $user, $order->remark);
      }, 3);

      return true;
    } catch (Exception $e) {
      Log::error("处理失败" . $e->getMessage());
      return false;
    }
  }

  /**
   * 保修工单 删除
   * @Author   leezhua
   * @DateTime 2020-07-15
   * @param    [orderId]     $DA [description]
   */
  public function delWorkorder(int $orderId)
  {
    try {
      $this->yhWorkModel()->find($orderId)->delete();
      // 删除日志
      WorkOrderLogModel::where('yh_order_id', $orderId)->delete();
      return true;
    } catch (Exception $e) {
      Log::error("隐患工单删除" . $e->getMessage());
      return false;
    }
  }

  /**
   * 隐患工单审核
   *
   * @Author leezhua
   * @DateTime 2024-01-30
   * @param [type] $DA
   * @param [type] $user
   *
   * @return void
   */
  public function auditWorkOrder($DA, $user)
  {
    try {
      DB::transaction(function () use ($DA, $user) {
        $order = $this->yhWorkModel()->find($DA['id']);
        if ($order->status != 3) {
          throw new Exception("工单状态不允许审核");
        }
        $order->remark      .= $DA['remark'] ?? "";
        $order->audit_time   = $DA['audit_time'] ?? nowYmd();
        $order->audit_person = $user['username'];

        $order->status = $DA['audit_status'] == 1 ? AppEnum::workorderClose : AppEnum::workorderTake; // 工单关闭
        $order->save();
        // 写入日志
        $this->saveYhOrderLog($DA['id'], $order->status, $user, $order->remark);
      });
      return true;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }

  /**
   * 隐患工单派单
   *
   * @Author leezhua
   * @DateTime 2024-01-30
   * @param [type] $DA
   * @param [type] $user
   *
   * @return void
   */
  public function orderDispatch($DA, $user)
  {
    try {
      DB::transaction(function () use ($DA, $user) {
        $yhWorkOrder = $this->yhWorkModel()->find($DA['id']);

        $yhWorkOrder->dispatch_time = $DA['dispatch_time'] ?? nowTime();
        $yhWorkOrder->dispatch_user = $DA['dispatch_user'] ?? $user->name;
        $yhWorkOrder->pick_user_id     = $DA['pick_user_id'];
        $yhWorkOrder->pick_user  = $DA['pick_user'];
        $yhWorkOrder->status = AppEnum::workorderTake;

        $yhWorkOrder->save();
        // 写入日志
        $this->saveYhOrderLog($DA['id'], $yhWorkOrder->status, $user, $yhWorkOrder['remark']);
      });
      return true;
    } catch (Exception $e) {
      Log::error("派单错误:" . $e->getMessage());
      return false;
    }
  }
  /** 生成工单编号 */
  private function yhWorkorderNo()
  {
    return 'YH-' . date('ymdHis') . mt_rand(10, 99);
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
  public function saveYhOrderLog($orderId, $orderStatus, $user, $remark = "")
  {
    try {
      $orderLog = new WorkOrderLogModel;
      $orderLog->yh_order_id = $orderId;
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
