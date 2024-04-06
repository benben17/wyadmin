<?php

namespace App\Api\Controllers\Weixin;

use JWTAuth;
use FFI\Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Sys\UserServices;
use App\Api\Controllers\BaseController;

use Laravel\Socialite\Facades\Socialite;
use App\Api\Services\Weixin\WeiXinServices;

class WeixinController extends BaseController
{
  public function __construct()
  {
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

    return $wxUser;
    $wxService = new WeiXinServices;
    $res = $wxService->saveWxUser($wxUser);

    return $res ? $this->success($wxUser) : $this->error("绑定失败");
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
    $wxService = new WeiXinServices;
    $result = $wxService->wxKey($request->code);
    Log::error(json_encode($result));
    if (isset($result['errcode']) || !isset($result['unionid'])) {
      return $this->error($result['errmsg']);
    }
    // Log::error($result['unionid']);
    // DB::enableQueryLog();
    // $result['unionid'] = "o-9QJ1K7V8sV4dsHtneM1P9o67s8";
    $res = $wxService->bindWx($result['unionid'], $uid, $request->wxUser);
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
      $wxService = new WeiXinServices;
      $result = $wxService->wxKey($request->code);
      if (isset($result['unionid'])) {
        $map['unionid']  = $result['unionid'];
        $map['is_vaild'] = 1;
        // $user = \App\Models\User::where($map)->first();
        $user = \App\User::where($map)->first();
        if (!$user) {
          return $this->error("未绑定微信，请登陆绑定微信!");
        }
        if (!$token =  auth('api')->login($user, false)) { //$user->id
          return $this->error('Token获取失败!');
        }
        $user = auth('api')->user();
        // Log::error("aaaa" . json_encode($user));
        $userService = new UserServices;
        $data = $userService->loginUserInfo($user, $token);
        return $this->success($data);
      } else {
        return $this->error("获取失败" . $result['errcode']);
      }
    } catch (Exception $e) {
      throw $e->getMessage();
      return $this->error("获取失败");
    }
  }
}
