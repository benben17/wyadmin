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
    $this->wxService = new WeiXinServices;
    $this->userService = new UserServices;
  }


  public function weixin()
  {
    return Socialite::with('weixinweb')->redirect();
  }
  /**
   * 微信接口回调
   * @Author   leezhua
   * @DateTime 2021-05-27
   * @param    Request    $request [description]
   * @return   [type]              [description]
   */
  public function weixinCallback()
  {
    // $uid = base64_decode($request->state);
    $wxUser = Socialite::driver('weixinweb')->stateless()->user();


    $wx_user = $this->wxService->saveWxUser($wxUser);

    return $this->success($wx_user);
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
      'code' => 'required',
    ]);
    $uid  = auth()->payload()->get('sub');
    if (!$uid) {
      return $this->error("请先登录");
    }

    $result = $this->wxService->getMiniProgramOpenId($request->appid, $request->code);
    Log::error(json_encode($result));
    if (isset($result['errcode']) || !isset($result['unionid'])) {
      return $this->error($result['errmsg']);
    }
    $res = $this->wxService->bindWx($result['unionid'], $uid);
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

    $uid  = auth()->payload()->get('sub');
    if (!$uid) {
      return $this->error("请先登录");
    }
    $wxService = new WeiXinServices;
    $res = $wxService->unBindWx($request->uid);
    if ($res) {
      return $this->success("解绑微信成功.");
    } else {
      return $this->error("解绑微信失败!");
    }
  }
  /**
   * @OA\Post(
   *     path="/api/wx/auth/login",
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
    ]);
    try {
      // 获取小程序用户信息
      $wxResult = $this->wxService->getMiniProgramOpenId($validatedData['appid'], $validatedData['code']);
      if (!isset($wxResult['unionid'])) {
        return $this->error("微信登录失败: " . ($wxResult['errmsg'] ?? '未知错误'));
      }

      // 查找绑定用户
      $user = $this->userService->userModel()->where('unionid', $wxResult['unionid']);
      if (!$user) {
        return $this->error("未绑定微信，请登录绑定微信!");
      }

      // 生成token
      if (!$token = Auth::guard('api')->login($user, false)) {
        return $this->error('登录失败!');
      }

      $data = $this->userService->getLoginUserInfo($user->id);
      $data['token'] = $token; // 将token添加到用户信息中

      return $this->success($data);
    } catch (\Exception $e) {
      return $this->error("登录失败: " . $e->getMessage());
    }
  }
}
