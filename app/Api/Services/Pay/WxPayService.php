<?php

namespace App\Api\Services\Pay;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Formatter;

class WxPayService
{
  private const MERCHANT_ID = '1664888422';
  private const MERCHANT_PRIVATE_KEY = '-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDolrLJ6/1VituG
nzh9BvVHLvbjgg1Xfp+KjmBv1qunhOLal0GVtkDL6tqdsfiN5uZaV3ksee4dWIOD
m8oicPihLfuZsoUe6xD0gazL/wNDld/fJZ6lgegt0Uf6yZbA0yPJo6pcVHXUyMw8
CuiJsdHXLO2nAdPnEHN2YteMBVfi8PObkOjA8CmFQiqi+ZSeje4D+a8iXO8dpNx+
PDwj0AHvlhShHm5S91m7L0pbAOMvU0ttm+het0mMOgck5qR4sVCQGVXrCT/ihuqo
WMbt/J0RVVZz9xo/wqGfzkd6ubdgAXjJs6Iub3aoBfGb85BqxvgFLlCx6GwZpZqt
yDS0T1/HAgMBAAECggEBAN2eajF4iWUu8EnwALUxIhl3qIvTab8Kyh4N19n45Iq0
ViwOn9F1XXxwBWrpH3qmnqtKWg2FCNwxcO7ATPRQTLfXxrsGnU1+kiNIREwEaynE
7xIDGI1/oKm3lixiVSDajmkdZ8CeQcBErEYx5qz9IIM1LiVJ2o164WnWeKfUl4+G
Uj1TCEN/QrutxdCqWx4SdpI7GaSZqEohfMxvlol8MAGII3cqFmXY26nmOGRoZaBj
TCT0NZsMwd5GFA6o1AOdxs/A5GWvgYD/YxEFG8tGDqFITomsmymkLxeRwdS/WKmX
K08GJj6A6aUjdxj2FCeChVJUAXQDXn9vA0dXioGAouECgYEA/y0wP6pI1takEyKD
ju//F5gzOiz7HavnO8/gpDOEBbpV/eNoZC0XOuhh5E/IV9BH2cxH5OlqcgpaizgT
3AgvtUI/fn5JnNtraGVxJ388p/NvDGtOC78M3+rJ2Q4FRu55aj/UzU0iZS2dvwYa
tef2KbgOBG0QgKlaFjWwXViWEfkCgYEA6VbZZMm3IT738piOs8u9fvVhvWYOiVFd
C/2y4IEdQKOC00YONMiQkyxtT/YML+6OxDO+9+A9RJu5lA9nSkt0meVScPNFdzGC
bGi8EN+iq7aN8d6BExGS9wbZUJgjhdjyfEQfgDBmrGQ1Ifr+jnuKUJ9KvL5utxN1
cdTFr+I1b78CgYEAkHsSjYmqElKXtend2WnT4pUftDnRuTwjAf+yruYoQ2H83HMN
IWNSet0myDHQOsBIXm5G6rqqtYVdPOR0gin0cUngT4vLvE+UYhjF19o4CtRPtRVw
rD/xVztGLGq+3Cmcf2dC4zdgWS9Z2NXo+8Qp4fc/oIvsQx0gT+D4SfIljmkCgYBz
a/SJOIaPuXgo1nHwWh3YSUUZzPvvzQF7xvjOuM9hhABYxdSNI5DwXA+OeCU7KIQS
ZY5XSuLDp0w7AwuS4pRA9AC9wnhgJ2teeMheiGENE3ZPaELszcqmywqAJWGc+d2o
voHehRKkv8TQlDmK/W1DyCfOCVz2zndP4XIQOJM6PQKBgFTTvbnQr3SoDjymU8+m
MLvyOWvs4OADJneKrasGXPhNx0B86lgmulaW5f8QZDXyfBeNBuXO5PhBqTFkBEM1
B/xhrUwYK+zs7hdrM2zMquRlbUiVSZ0K8ehsC+/HhlQncnO+WFIF7iFhz31JBHk+
7Lh8e3kwrq4lJ+4wwj5e6BPm
-----END PRIVATE KEY-----';

  // private const MERCHANT_CERTIFICATE_SERIAL = '1ddb4d864b24dac869267344d532a45e';
  private const MERCHANT_CERTIFICATE_SERIAL = '45860A6925389C877CF838A36DA11A41BF6C8716';

  private const PLATFORM_CERTIFICATE_PATH = 'file:///Users/benben/Desktop/weixin/apiclient_cert.pem';


  private $merchantId;
  private $merchantPrivateKeyInstance;
  private $merchantCertificateSerial;
  private $platformPublicKeyInstance;
  private $platformCertificateSerial;
  private $notifyUrl;

  public function __construct()
  {
    $this->merchantId = self::MERCHANT_ID;
    // Load "Merchant API Private Key" from a local file
    $this->merchantPrivateKeyInstance = Rsa::from(self::MERCHANT_PRIVATE_KEY, Rsa::KEY_TYPE_PRIVATE);

    // Set "Merchant API Certificate" serial number
    $this->merchantCertificateSerial = self::MERCHANT_CERTIFICATE_SERIAL;

    $paltformCert = "file:///Users/benben/Desktop/weixin/wechatpay_cert.pem";

    // Load "WeChat Pay Platform Certificate" from a local file
    $this->platformPublicKeyInstance = Rsa::from($paltformCert, Rsa::KEY_TYPE_PUBLIC);
    $this->platformCertificateSerial = PemUtil::parseCertificateSerialNo($paltformCert);
    $this->notifyUrl = 'https://admin.rss2rbook.com/api/wx/pay/notify_url';
  }


  // Instance function
  public function instance()
  {

    $instance =  Builder::factory([
      'mchid'      => $this->merchantId,
      'serial'     => $this->merchantCertificateSerial,
      'privateKey' => $this->merchantPrivateKeyInstance,
      'certs'      => [
        $this->platformCertificateSerial => $this->platformPublicKeyInstance,
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
          'appid'        => "wx3deff7fd66b83f61",
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
      Log::error($resp->getBody());

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
    $platformPublicKeyInstance = Rsa::from(self::PLATFORM_CERTIFICATE_PATH, Rsa::KEY_TYPE_PUBLIC);
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
      $inBodyResource = AesGcm::decrypt($ciphertext, self::MERCHANT_CERTIFICATE_SERIAL, $nonce, $aad);
      // 把解密后的文本转换为PHP Array数组
      $inBodyResourceArray = (array)json_decode($inBodyResource, true);
      // print_r($inBodyResourceArray);// 打印解密后的结果
      Log::error($inBodyResourceArray);
      return $inBodyResourceArray;
    }
    Log::error($wxBody);
  }
}
