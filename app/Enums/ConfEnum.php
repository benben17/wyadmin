<?php

namespace App\Enums;

use MyCLabs\Enum\Enum;


abstract class ConfEnum extends Enum
{
  const MERCHANT_ID       = 'MERCHANT_ID';  //  渠道
  const MERCHANT_PRIVATE_KEY = 'MERCHANT_PRIVATE_KEY'; // 商户私钥
  const MERCHANT_CERTIFICATE_SERIAL = 'MERCHANT_CERTIFICATE_SERIAL'; //Merchant API Certificate" serial number
  const PLATFORM_CERTIFICATE    = 'PLATFORM_CERTIFICATE_KEY';  //  供应商
  const NOTIFY_URL  = 'NOTIFY_URL';  // 公共关系
  const XCX_APPID        = 'XCX_APPID';  // 租户
  const CusClue       = 6;  // 线索


}
