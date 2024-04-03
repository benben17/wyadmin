<?php

namespace App\Api\Services\Common;

use Exception;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;

/**
 * 短信服务
 */
class SmsService
{

  /**
   * 发送消息
   * @Author   leezhua
   * @DateTime 2020-07-08
   * @param    [type]     $DA   [消息内容]
   * @param    [type]     $user [用户信息]
   * @param    integer    $type [1 通知 2待办]
   * @return   [type]           [description]
   */

  public function sendSms($PhoneNumber, array $Param)
  {
    try {
      $smsConfig = config('ALI_SMS');
      $SignName = $smsConfig['SignName'];
      $TemplateCode = $smsConfig['TemplateCode'];
      $send = Aliyunsms::sendSms(strval($PhoneNumber), $SignName, $TemplateCode, $Param);
      Log::info(json_encode($send));

      return $send->Code == 'OK';
    } catch (Exception $e) {
      Log::error("messageService:" . $e->getMessage());
      throw new Exception("短信发送失败!");
      return false;
    }
  }
}
