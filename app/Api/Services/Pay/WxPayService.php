<?php

namespace App\Api\Services\Pay;

use App\Enums\ConfEnum;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Formatter;
use App\Api\Services\Weixin\WxConfService;

class WxPayService
{

  private $appid;
  private $companyId;
  private $merchantId;
  private $mchPrivateKey;
  private $apiv3Key;
  private $notifyUrl;
  private $platformCert;
  private $mchPrivateCertSerial;
  private $confService;
  public function __construct()
  {
    $user = auth('api')->user();

    $this->companyId = $user->company_id;

    $this->confService = new WxConfService;

    $conf = $this->confService->getWechatPayConf();
    $this->appid = Cache::get(ConfEnum::XCX_APPID . $this->companyId) ?? $conf['app_id'];
    $this->merchantId = Cache::get(ConfEnum::MERCHANT_ID . $this->companyId) ?? $conf['mch_id'];
    $this->notifyUrl = Cache::get(ConfEnum::NOTIFY_URL . $this->companyId) ?? $conf['notify_url'];
    $this->platformCert = Cache::get(ConfEnum::PLATFORM_CERTIFICATE . $this->companyId) ?? $conf['platform_cert'];
    $this->mchPrivateKey = Cache::get(ConfEnum::MERCHANT_PRIVATE_KEY . $this->companyId) ?? $conf['mch_key'];
    $this->apiv3Key = Cache::get(ConfEnum::MERCHANT_PRIVATE_CERT . $this->companyId) ?? $conf['api_key'];
    $this->mchPrivateCertSerial = Cache::get(ConfEnum::MERCHANT_CERTIFICATE_SERIAL . $this->companyId) ?? $conf['mch_key_serial'];
  }


  // Instance function
  public function instance()
  {

    $certSerial   = PemUtil::parseCertificateSerialNo($this->platformCert);

    $merchantPrivateKey = Rsa::from($this->mchPrivateKey, Rsa::KEY_TYPE_PRIVATE);
    $instance     =  Builder::factory([
      'mchid'      => $this->merchantId,
      'serial'     => $this->mchPrivateCertSerial,
      'privateKey' => $merchantPrivateKey,
      'certs'      => [
        $certSerial => Rsa::from($this->platformCert, Rsa::KEY_TYPE_PUBLIC),
      ],
    ]);

    // $resp = $instance->chain('v3/certificates')->get(
    //   ['debug' => true] // 调试模式，https://docs.guzzlephp.org/en/stable/request-options.html#debug
    // );
    // echo $resp->getBody(), PHP_EOL;

    return $instance;
  }

  /**
   * 微信jsapi支付  小程序
   *
   * @Author leezhua
   * @DateTime 2024-01-12
   * @param array $orderInfo
   *
   * @return void
   */
  function wxJsapiPay(array $order, $openid): array
  {
    try {
      $resp = $this->instance()
        ->chain('v3/pay/transactions/jsapi')
        ->post(['json' => [
          'mchid'        => $this->merchantId,
          'out_trade_no' => $order['out_trade_no'],
          'appid'        => $this->appid,
          'description'  => $order['description'],
          'notify_url'   => $this->notifyUrl,
          'amount'       => [
            'total'    => 1,
            'currency' => 'CNY',
          ],
          'payer' => [
            'openid' => $openid,
          ]
        ]]);
      // Log::error($resp->getBody());

      return (array)json_decode($resp->getBody(), true);
    } catch (\Exception $e) {
      // Log the error
      Log::error($e->getMessage());

      if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
        $r = $e->getResponse();
        Log::error($r->getStatusCode() . ' ' . $r->getReasonPhrase());
        Log::error($r->getBody());
      }
      return [
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * 微信支付退款
   *
   * @Author leezhua
   * @DateTime 2024-03-03
   * @param array $pay
   *
   * @return boolean
   */
  function wxRefund(array $pay): bool
  {
    try {
      $resp = $this->instance()
        ->chain('v3/refund/domestic/refunds')
        ->post(['json' => [
          'transaction_id'  => $pay['transaction_id'],
          'out_refund_no'   => $pay['out_trade_no'],
          'reason'          => $pay['description'],
          'amount'          => [
            'refund'    => $pay['refundAmt'],
            'total'     => $pay['total'],
            'currency' => 'CNY',
          ]
        ]]);

      // return "okok";
      $res = (array)json_decode($resp->getBody(), true);
      if (array_key_exists('status', $res)) {
        $out_refund_no = $res['out_refund_no'];
      }
      return true;
    } catch (\Exception $e) {
      // Log the error
      Log::error("退款" . $e->getMessage());

      if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
        $r = $e->getResponse();
        Log::error($r->getStatusCode() . ' ' . $r->getReasonPhrase());
        Log::error($r->getBody());
      }
      return false;
    }
  }

  /**
   * 支付回调
   *
   * @Author leezhua
   * @DateTime 2024-01-13
   * @param [type] $wxSignature
   * @param [type] $wxTimestamp
   * @param [type] $wxpayNonce
   * @param [type] $wxBody
   *
   * @return void
   */
  function wxPayNotify($wxSignature, $wxTimestamp, $wxpayNonce, $wxBody)
  {
    $platformPublicKeyInstance = Rsa::from($this->platformCert, Rsa::KEY_TYPE_PUBLIC);
    // // 检查通知时间偏移量，允许5分钟之内的偏移
    $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$wxTimestamp);
    $verifiedStatus = Rsa::verify(
      //   // 构造验签名串
      Formatter::joinedByLineFeed($wxTimestamp, $wxpayNonce, $wxBody),
      $wxSignature,
      $platformPublicKeyInstance
    );
    if ($timeOffsetStatus && $verifiedStatus) {
      // 转换通知的JSON文本消息为PHP Array数组
      $inBodyArray = (array)json_decode($wxBody, true);
      // 使用PHP7的数据解构语法，从Array中解构并赋值变量
      ['resource' => [
        'ciphertext'      => $ciphertext,
        'nonce'           => $nonce,
        'associated_data' => $aad
      ]] = $inBodyArray;
      // 加密文本消息解密
      $inBodyResource = AesGcm::decrypt($ciphertext, $this->apiv3Key, $nonce, $aad);
      // 把解密后的文本转换为PHP Array数组
      $inBodyResourceArray = (array)json_decode($inBodyResource, true);
      // print_r($inBodyResourceArray);// 打印解密后的结果
      Log::error($inBodyResourceArray);
      return $inBodyResourceArray;
    }
    Log::error($wxBody);
  }
}
