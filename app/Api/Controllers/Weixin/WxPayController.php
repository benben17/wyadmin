<?php

namespace App\Api\Controllers\Weixin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Pay\WxPayService;
use App\Api\Controllers\BaseController;

class WxPayController extends BaseController
{
  private $wxPayService;
  public function __construct()
  {
    // parent::__construct();
    $this->wxPayService = new WxPayService;
  }

  /**
   * @OA\Post(
   *     path="/api/wx/pay/notify_url",
   *     tags={"微信支付回调"},
   *     summary="微信支付回调",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(
   *          property="id",
   *          type="int",
   *          description="场馆Id"
   *       )
   *     ),
   *       example={"id": 11}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */

  public function WxPayNotify(Request $request)
  {
    try {

      // Get headers
      $headers = $request->headers->all();
      $wxSignature = $headers['Wechatpay-Signature'];
      $wxTimestamp = $headers['Wechatpay-Timestamp'];
      $wxpaySerial = $headers['Wechatpay-Serial'];
      $wxpayNonce = $headers['Wechatpay-Nonce'];
      $wxBody = $request->getContent();

      // 输出回调数据
      Log::info('Raw Callback Data: ' . $request->getContent());
      // Handle the payment callback using the WxPayService
      $response = $this->wxPayService->wxPayNotify($wxSignature, $wxTimestamp, $wxpayNonce, $wxBody);
      Log::info('Callback Response: ' . $response);

      return response($request->getContent());
    } catch (\Exception $e) {
      // Log any exceptions that occur during callback processing
      Log::error('Exception during callback processing: ' . $e->getMessage());

      // Return an error response to WeChat Pay
      return response('FAIL', 500);
    }
  }
}
