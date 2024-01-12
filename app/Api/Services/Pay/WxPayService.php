<?php

namespace App\Api\Services\Pay;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

require_once('vendor/autoload.php');

use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;

class WxPayService
{
  private const MERCHANT_ID = '190000';
  private const MERCHANT_PRIVATE_KEY_PATH = 'file:///path/to/merchant/apiclient_key.pem';

  private const MERCHANT_CERTIFICATE_SERIAL = '3775B6A45ACD588826D15E583A95F5DD********';

  private const PLATFORM_CERTIFICATE_PATH = 'file:///path/to/wechatpay/cert.pem';


  private $merchantId;
  private $merchantPrivateKeyInstance;
  private $merchantCertificateSerial;
  private $platformPublicKeyInstance;
  private $platformCertificateSerial;

  public function __construct()
  {
    $this->merchantId = self::MERCHANT_ID;

    // Load "Merchant API Private Key" from a local file
    $this->merchantPrivateKeyInstance = Rsa::from(self::MERCHANT_PRIVATE_KEY_PATH, Rsa::KEY_TYPE_PRIVATE);

    // Set "Merchant API Certificate" serial number
    $this->merchantCertificateSerial = self::MERCHANT_CERTIFICATE_SERIAL;

    // Load "WeChat Pay Platform Certificate" from a local file
    $this->platformPublicKeyInstance = Rsa::from(self::PLATFORM_CERTIFICATE_PATH, Rsa::KEY_TYPE_PUBLIC);

    // Get "WeChat Pay Platform Certificate" serial number
    $this->platformCertificateSerial = PemUtil::parseCertificateSerialNo(self::PLATFORM_CERTIFICATE_PATH);
  }


  // Instance function
  public function instance()
  {
    return Builder::factory([
      'mchid'      => $this->merchantId,
      'serial'     => $this->merchantCertificateSerial,
      'privateKey' => $this->merchantPrivateKeyInstance,
      'certs'      => [
        $this->platformCertificateSerial => $this->platformPublicKeyInstance,
      ],
    ]);
  }

  /**
   * 微信native支付
   *
   * @Author leezhua
   * @DateTime 2024-01-12
   * @param array $orderInfo
   *
   * @return void
   */
  function jsapiPay(array $orderInfo, $openid)
  {
    try {
      $resp = $this->instance()
        ->chain('v3/pay/transactions/jsapi')
        ->post(['json' => [
          'mchid'        => $this->merchantId,
          'out_trade_no' => $orderInfo['out_trade_no'],
          'appid'        => $orderInfo['appid'],
          'description'  => $orderInfo['description'],
          'notify_url'   => $orderInfo['notify_url'],
          'amount'       => [
            'total'    => $orderInfo['total_amount'],
            'currency' => 'CNY',
          ],
          'payer' => [
            'openid' => $openid,
          ]
        ]]);

      return [
        'status_code' => $resp->getStatusCode(),
        'response'    => $resp->getBody()->getContents(),
      ];
    } catch (\Exception $e) {
      // Log the error
      Log::error($e->getMessage());

      if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
        $r = $e->getResponse();
        Log::error($r->getStatusCode() . ' ' . $r->getReasonPhrase());
        Log::error($r->getBody());
      }

      Log::error($e->getTraceAsString());

      return [
        'error' => $e->getMessage(),
      ];
    }
  }
}
