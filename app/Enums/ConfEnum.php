<?php

namespace App\Enums;

use MyCLabs\Enum\Enum;


abstract class ConfEnum extends Enum
{
  const MERCHANT_ID                 = 'MERCHANT_ID';  //  商户ID
  const MERCHANT_PRIVATE_KEY        = 'MERCHANT_PRIVATE_KEY'; // 商户私钥
  const MERCHANT_PRIVATE_CERT       = 'MERCHANT_PRIVATE_CERT'; // 商户私钥
  const MERCHANT_CERTIFICATE_SERIAL = 'MERCHANT_CERTIFICATE_SERIAL'; //Merchant API Certificate" serial number
  const PLATFORM_CERTIFICATE        = 'PLATFORM_CERTIFICATE_KEY';  //  平台证书
  const NOTIFY_URL                  = 'NOTIFY_URL';  //  支付通知地址
  const XCX_APPID                   = 'XCX_APPID';  // 小程序ID


}
