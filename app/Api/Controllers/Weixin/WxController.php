<?php

namespace App\Api\Controllers\Weixin;

use JWTAuth;
use FFI\Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Api\Services\Sys\UserServices;
use App\Api\Controllers\BaseController;

use Laravel\Socialite\Facades\Socialite;
use App\Api\Services\Weixin\WeiXinServices;

class WxController extends BaseController
{
  protected $wxService;
  protected $userService;
  public function __construct()
  {
    parent::__construct();
    $this->wxService = new WeiXinServices;
    $this->userService = new UserServices;
  }



  /**
   * @OA\Post(
   *     path="/api/wx/user/bind",
   *     tags={"微信小程序登陆"},
   *     summary="微信小程序绑定",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"code"},
   *       @OA\Property(property="code",type="String",description="微信code"),
   *     ),
   * 
   *       example={"user":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function bindWx(Request $request)
  {
    $validatedData = $request->validate([
      'unionid' => 'required',
    ]);

    $res = $this->wxService->bindWx($request->unionid, $this->uid);
    if ($res) {
      return $this->success("绑定成功;");
    } else {
      return $this->error("绑定失败");
    }
  }

  /**
   * @OA\Post(
   *     path="/api/wx/user/unbind",
   *     tags={"微信小程序登陆"},
   *     summary="微信解除绑定",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={}
   *     ),
   *       example={}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function unBindWx(Request $request)
  {

    $wxService = new WeiXinServices;
    $res = $wxService->unBindWx($request->uid);
    if ($res) {
      return $this->success("解绑微信成功.");
    } else {
      return $this->error("解绑微信失败!");
    }
  }



  public function getPhone(Request $request)
  {
    $validatedData = $request->validate([
      'encryptedData' => 'required',
      'iv' => 'required',
    ]);

    $appId = $request->app_id;
    // 获取 session_key
    $sessionKey = $this->wxService->getSessionKey($request->code, $appId);

    if (!$sessionKey) {
      return null;
    }

    // 解密手机号
    $data = $this->wxService->decryptData($request->encryptedData, $request->iv, $sessionKey, $appId);

    return $data;
  }
}
