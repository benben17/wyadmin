<?php

namespace App\Api\Controllers\Weixin;

use JWTAuth;
use Socialite;
use App\Api\Controllers\BaseController;
use App\Api\Services\Weixin\WeiXinServices;
use Illuminate\Http\Request;
use SocialiteProviders\WeixinWeb\Provider;
use App\Api\Services\Sys\UserServices;
use App\Api\Models\Weixin\WxUser;
use FFI\Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;



class WeiXinController extends BaseController
{
  public function __construct()
  {
  }


  /**
   * [redirectToProvider description]
   * @Author   leezhua
   * @DateTime 2021-05-31
   * @param    Request    $request [description]
   * @return   [type]              [description]
   */
  public function redirectToProvider(Request $request)
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
  public function handleProviderCallback(Request $request)
  {
    try {
      $user_data = Socialite::with('weixinweb')->stateless()->user();
      Log::error("user_data");
      if ($user_data) {
        // Log::error("11111");
        $uid = base64_decode($request->state);
        Log::error($uid . "------" . $request->state);
        $wx_user = $user_data->user;

        $wxService = new WeiXinServices;
        DB::enableQueryLog();
        // return response()->json(DB::getQueryLog());
        $res = $wxService->bindWx($wx_user, $uid);
        if ($res) {
          return $this->success("绑定成功;");
        } else {
          return $this->error("绑定失败");
        }
      }
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return $this->error($e->getMessage());
    }
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
    // $result = $wxService->wxKey($request->code);

    // if (isset($result['errcode']) || !isset($result['unionid'])) {
    //   return $this->error($result['errmsg']);
    // }
    // Log::error($result['unionid']);
    // DB::enableQueryLog();
    $result['unionid'] = "o-9QJ1K7V8sV4dsHtneM1P9o67s8";
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
   *          required={"uid"},
   *          @OA\Property(property="uid",type="int",description="用户id"),
   * 
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
    if ($uid) {
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
        $map['unionid']   = $result['unionid'];
        $map['is_vaild'] = 1;
        $user = \App\Models\User::where($map)->first();
        if (!$token = auth('api')->tokenById($user->id)) {
          return $this->error('Token获取失败!');
        }
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
