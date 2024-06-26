<?php

namespace App\Api\Services\Weixin;

use App\Enums\ConfEnum;
use App\Enums\WeixinEnum;
use App\Api\Models\Company\WyAppConf;
use Illuminate\Support\Facades\Cache;

class WxConfService
{


  // private $merchantId;
  // private $merchantPrivateKeyInstance;
  // private $merchantCertificateSerial;
  // private $platformPublicKeyInstance;
  // private $platformCertificateSerial;
  // private $notifyUrl;
  private $wyAppConf;
  private $companyId;
  public function __construct()
  {
    $this->wyAppConf = new WyAppConf();
    $user = auth('api')->user();
    $this->companyId = $user->company_id;
  }



  public function getWechatPayConf()
  {
    $wxPayConf =  $this->wyAppConf->where('type', WeixinEnum::PAY)->firstOrFail();
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
  /**
   * @Desc: 获取微信配置
   * @Author leezhua
   * @Date 2024-04-06
   * @param [type] $appid
   * @param [type] $type
   * @return void
   */
  public function getWeixinConf($appid, $type)
  {
    if (Cache::has(ConfEnum::APPID . $appid)) {
      return [
        'app_id' => Cache::get(ConfEnum::APPID . $appid),
        'app_secret' => Cache::get(ConfEnum::APP_SECRET . $appid)
      ];
    }
    $where = [];
    $where(['app_id' => $appid]);
    $where(['type' => $type]);
    $wxAppConf =  $this->wyAppConf->where($where)->firstOrFail();
    if ($wxAppConf) {
      Cache::set(ConfEnum::APPID . $appid, $wxAppConf['app_id']);
      Cache::set(ConfEnum::APP_SECRET . $appid, $wxAppConf['app_secret']);
      return $wxAppConf;
    }
    return null;
  }
}
