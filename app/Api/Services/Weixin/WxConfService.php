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
    // 使用缓存键简化逻辑
    $cacheKey = ConfEnum::WECHAT_PAY_CONF . $this->companyId;

    // 缓存2小时 
    return Cache::remember($cacheKey, 120, function () {
      $wxPayConf = $this->wyAppConf->where('type', WeixinEnum::PAY)->first();

      // 使用 null coalescing 运算符简化判断
      if ($wxPayConf) {
        Cache::setMultiple([
          ConfEnum::MERCHANT_ID . $this->companyId => $wxPayConf['mch_id'],
          ConfEnum::XCX_APPID . $this->companyId => $wxPayConf['app_id'],
          ConfEnum::MERCHANT_PRIVATE_KEY . $this->companyId => $wxPayConf['mch_key'],
          ConfEnum::MERCHANT_CERTIFICATE_SERIAL . $this->companyId => $wxPayConf['mch_key_serial'],
          ConfEnum::NOTIFY_URL . $this->companyId => $wxPayConf['notify_url'],
          ConfEnum::PLATFORM_CERTIFICATE . $this->companyId => $wxPayConf['platform_cert'],
        ]);
      }

      return $wxPayConf ?: null;
    });
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
    // 使用缓存键简化逻辑
    $cacheKey = ConfEnum::APPID . $appid . '_' . $type;

    return Cache::rememberForever($cacheKey, function () use ($appid, $type) {
      $wxAppConf = $this->wyAppConf->where('app_id', $appid)
        ->where('type', $type)
        ->first();

      return $wxAppConf ?: null; // 使用简化的三元运算符
    });
  }
}
