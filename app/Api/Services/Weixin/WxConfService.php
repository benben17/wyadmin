<?php

namespace App\Api\Services\Weixin;

use App\Enums\ConfEnum;
use App\Api\Models\Weixin\WeChatPayConf;
use Illuminate\Support\Facades\Cache;

class WxConfService
{


  private $merchantId;
  private $merchantPrivateKeyInstance;
  private $merchantCertificateSerial;
  private $platformPublicKeyInstance;
  private $platformCertificateSerial;
  private $notifyUrl;
  private $wxPayConfModel;
  private $companyId;
  public function __construct()
  {
    $this->wxPayConfModel = new WeChatPayConf();
    $user = auth('api')->user();
    $this->companyId = $user->company_id;
  }



  public function getWechatPayConf()
  {
    $wxPayConf =  $this->wxPayConfModel->where('type', 1)->firstOrFail();
    if ($wxPayConf) {
      Cache::set(ConfEnum::MERCHANT_ID . $this->companyId, $wxPayConf['mch_id']);
      Cache::set(ConfEnum::XCX_APPID . $this->companyId, $wxPayConf['app_id']);
      Cache::set(ConfEnum::MERCHANT_PRIVATE_KEY . $this->companyId, $wxPayConf['mch_key']);
      Cache::set(ConfEnum::MERCHANT_CERTIFICATE_SERIAL . $this->companyId, $wxPayConf['mch_key_serial']);
      Cache::set(ConfEnum::NOTIFY_URL . $this->companyId, $wxPayConf['notify_url']);
      Cache::set(ConfEnum::PLATFORM_CERTIFICATE . $this->companyId, $wxPayConf['platform_cert']);

      return $wxPayConf;
    }
    return null;
  }
}
