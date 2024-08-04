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

class WxAuthController extends BaseController
{
  protected $wxService;
  protected $userService;
  public function __construct()
  {
    $this->wxService = new WeiXinServices;
    $this->userService = new UserServices;
  }

  /**
   * @OA\Post(
   *     path="/api/wx/auth/login",
   *     tags={"微信登陆"},
   *     summary="微信登陆",
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function weixin()
  {
    return Socialite::with('weixinweb')->redirect();
  }
  /**
   * @OA\Get(
   *     path="/api/wx/auth/callback",
   *     tags={"微信回调"},
   *     summary="微信回调",
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function wxCallback(Request $request)
  {
    // $uid = base64_decode($request->state);
    try {
      $wxUser = Socialite::driver('weixinweb')->user();
      $wx_user = $this->wxService->saveWxUser($wxUser);

      return $this->success($wx_user);
    } catch (\Exception $e) {
      return $this->error("回调失败 " . $e->getMessage());
    }
  }

  /**
   * @OA\Post(
   *     path="/api/wx/auth/weixin",
   *     tags={"微信小程序登陆"},
   *     summary="微信小程序登陆",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"code"},
   *       @OA\Property(property="code",type="String",description="code")
   *
   *     ),
   *       example={"code":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function wxAppAuth(Request $request)
  {
    $validatedData = $request->validate([
      'code' => 'required',
      'appid' => 'required',
    ]);
    try {
      // 获取小程序用户信息
      $wxResult = $this->wxService->getSessionKey($request->code, $request->appid);
      if (!isset($wxResult['unionid'])) {
        return $this->error("微信登录失败: " . ($wxResult['errmsg'] ?? '未知错误'));
      }

      // 查找绑定用户 获取web端Token
      $user = $this->userService->userModel()->where('unionid', $wxResult['unionid'])->first();
      if ($user) {
        if (!$token = Auth::guard('api')->claims(['guard' => 'api'])->login($user, false)) {
          return $this->error('登录失败!');
        }
        $user = Auth::guard('api')->user();
        $userData = $this->userService->genWxUserLoginData($user);
        $data['token'] = $token;
        return $this->success($userData);
      } else {
        return $this->error("未绑定用户");
      }
    } catch (\Exception $e) {
      return $this->error("登录失败: " . $e->getMessage());
    }
  }


  /**
   * @OA\Post(
   *     path="/api/wx/auth/passwdlogin",
   *     tags={"auth认证"},
   *     summary="登录",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"name", "password"},
   *       @OA\Property(
   *          property="username",
   *          type="string",
   *          description="用户名"
   *       ),
   *       @OA\Property(
   *          property="password",
   *          type="string",
   *          description="密码"
   *       )
   *     ),
   *       example={
   *              "username": "test", "password": "123456"
   *           }
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function wxLogin(Request $request)
  {
    $credentials['name']     = $request->input('username');
    $credentials['password'] = $request->input('password');
    if (!$token = auth('api')->claims(['gurad' => 'api'])->attempt($credentials)) {
      return $this->error('用户名或密码错误!');
    }
    // 登录成功后获取用户信息
    $user = Auth::guard('api')->user();
    $userData = $this->userService->genWxUserLoginData($user);
    $data['token'] = $token;
    return $this->success($userData);
  }
}
