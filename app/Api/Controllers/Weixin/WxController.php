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

  /**
   * @OA\Post(
   *     path="/api/wx/login",
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
   *     path="/api/wx/callback",
   *     tags={"微信回调"},
   *     summary="微信回调",
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function weixinCallback()
  {
    // $uid = base64_decode($request->state);

    $wxUser = Socialite::driver('weixinweb')->user();


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


  /**
   * @OA\Post(
   *     path="/api/auth/wx/login",
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
    if (!$token = auth('api')->attempt($credentials)) {
      return $this->error('用户名或密码错误!');
    }
    // $user = auth('api')->user();
    // 登录成功后获取用户信息
    $user = Auth::guard('api')->user();
    $data['token'] = $token;

    // 使用 UserService 获取其他用户信息
    $data = array_merge($data, $this->userService->getLoginUserInfo($user->id));

    // 获取用户系统权限，当用户is admin 的时候返回空
    $data['menu_list'] = $this->userService->userAppMenu($user);

    return $this->success($data);
  }
}
