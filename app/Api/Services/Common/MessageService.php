<?php

namespace App\Api\Services\Common;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use App\Api\Models\Common\Message as MessageModel;
use App\Api\Models\Common\MessageRead as MessageReadModel;

/**
 * 全局公共消息服务
 */
class MessageService
{
  // 返回 model
  public function msgModel()
  {
    $model = new MessageModel;
    return $model;
  }

  public function MsgReadModel()
  {
    $model = new MessageReadModel;
    return $model;
  }


  /**
   * 发送消息
   * @Author   leezhua
   * @DateTime 2020-07-08
   * @param    [type]     $DA   [消息内容]
   * @param    [type]     $user [用户信息]
   * @param    integer    $type [1 通知 2待办]
   * @return   [type]           [description]
   */
  public function sendMsg($DA, $user, $type = 1)
  {
    try {
      if ($DA['role_id'] == '-1') {
        $receiveUid = '-1';
      } else {
        $roleIds = explode(',', $DA['role_id']);
        $receive  = \App\Models\User::whereIn('role_id', $roleIds)
          ->where('is_vaild', 1)
          ->select(DB::Raw('group_concat(id) uids'))->first();
        $receiveUid = $receive['uids'];
      }
      if (!$receiveUid) {
        Log::error("role_id:" . $DA['role_id'] . '未找到用户信息');
        return false;
      }

      $DA['receive_uid'] = $receiveUid;
      DB::transaction(function () use ($DA, $user, $type) {
        $message  = new MessageModel;
        $message->company_id  = $user['company_id'];
        $message->type        = $type;
        if (isset($DA['title']) && $DA['title']) {
          $message->title       = $DA['title'];
        } else {
          if ($type == 1) {
            $message->title   = isset($DA['title']) ? $DA['title'] : "通知消息";
          } else {
            $message->title   = isset($DA['title']) ? $DA['title'] . "待处理消息" : "待处理消息";
          }
        }
        $message->content     = $DA['content'];
        $message->receive_uid = $DA['receive_uid'];
        $message->sender_uid  = $user['id'];
        $message->sender_username = $user['realname'];
        $message->save();
      });
      return true;
    } catch (Exception $e) {
      Log::error("messageService:" . $e);
      throw new Exception("消息发送失败!");
      return false;
    }
  }

  /**
   * 合同审核退租作废的时候 发送的系统消息
   * @Author   leezhua
   * @DateTime 2020-06-27
   * @param    [Array]     $DA [description]
   * @return   [type]         [description]
   */
  public function contractMsg($DA)
  {
    try {
      DB::transaction(function () use ($DA) {
        $message  = new MessageModel;
        $message->company_id = $DA['company_id'];
        $message->type = 1;
        $message->title = $DA['title'];
        $message->content = $DA['content'];
        $message->receive_uid = '-1';
        $message->sender_uid = -1;
        $message->sender_username = "系统通知";
        $message->save();
      });
      return true;
    } catch (Exception $e) {
      Log::error($e);
      return false;
    }
  }

  /** 设置消息已读 */
  public function setRead($msgIds, $uid)
  {
    if (!is_array($msgIds)) {
      $msgIds = str2Array($msgIds);
    }
    try {
      DB::transaction(function () use ($msgIds, $uid) {
        foreach ($msgIds as $msgId) {
          $msgRead = MessageReadModel::where('msg_id', $msgId)->where('uid', $uid)->exists();
          if (!$msgRead) {
            $msg = new MessageReadModel;
            $msg->msg_id = $msgId;
            $msg->uid = $uid;
            $msg->save();
          }
        }
      });
      return true;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }
  // 删除接收的消息。1 已读删除 2 未读删除
  public function deleteMsg(array $msgIds, $uid)
  {
    try {
      DB::transaction(function () use ($msgIds, $uid) {
        foreach ($msgIds as $msgId) {
          $map['msg_id'] = $msgId;
          $map['uid'] = $uid;
          $msg = MessageReadModel::where($map)->first();
          if ($msg) {  // 已读 直接更新
            $msg->is_delete = 1;
            $msg->save();
          } else {   // 未读 写入一条记录
            $msg = new MessageReadModel;
            $msg->msg_id = $msgId;
            $msg->uid = $uid;
            $msg->is_delete = 1;
            $msg->save();
          }
        }
      });
      return true;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }

  /**
   * 查看自己发送的消息，格式化数据
   * @Author   leezhua
   * @DateTime 2020-07-16
   * @param    [type]     $msg [description]
   * @return   [type]          [description]
   */
  public function sendShow($msg)
  {
    if ($msg['receive_uid'] == '-1') {
      $receiveUser = '所有人';
    } else {
      $receiveUser = $this->getUserName($msg['receive_uid']);
    }
    // add receive username
    $msg['receive_user'] = $receiveUser;  // 添加接收人

    $readUid = $this->getReadUid($msg['id']);
    $msg['read_user'] = $this->getUserName($readUid);

    $revokeTime = config('msg_revoke');
    if (strtotime($msg['created_at']) + $revokeTime['time'] * 60 <= time()) {
      $msg['is_revoke'] = 0;
    } else {
      $msg['is_revoke'] = 1;
    }
    return $msg;
  }

  /**
   * 通过msg id 获取已读用户id
   * @Author   leezhua
   * @DateTime 2020-07-03
   * @param    [int]     $msgId [description]
   * @return   [array]            [已读用户uids array]
   */
  public function getReadUid($msgId)
  {
    $readUids = MessageReadModel::select(DB::Raw('GROUP_CONCAT(uid) uids'))
      ->where('msg_id', $msgId)->first();
    return explode(',', $readUids['uids']);
  }
  /**
   * 根据用户id list 获取用户名
   * @Author   leezhua
   * @DateTime 2020-07-03
   * @param    Array      $uids [description]
   * @return   [String]           [用户多个用户逗号隔开]
   */
  public function getUserName($uids)
  {
    $uids = str2Array($uids);
    $users = \App\Models\User::whereIn('id', $uids)
      ->select(DB::Raw('GROUP_CONCAT(realname) as realname'))
      ->first();
    return $users['realname'];
  }
}
