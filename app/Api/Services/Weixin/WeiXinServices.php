<?php

namespace App\Api\Services\Weixin;

use Exception;
use App\Models\User;
use GuzzleHttp\Client;
use App\Enums\WeixinEnum;
use App\Api\Models\Weixin\WxUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Api\Services\Weixin\WxConfService;

/**
 * 微信公众号服务
 */
class WeiXinServices
{
  protected $wxApiUrl = 'https://api.weixin.qq.com/';

  public function wxUserModel()
  {
    return new WxUser;
  }

  public function userModel()
  {
    return new User;
  }
  //#MARK: 微信用户保存
  /**
   * 保存微信用户
   *
   * @param [type] $DA
   * @return void
   */
  public function saveWxUser($DA)
  {
    try {
      $wx_user = $this->wxUserModel()->where('unionid', $DA['unionid'])->first();
      if (!$wx_user) {
        $wx_user = $this->wxUserModel();

        $wx_user->unionid   = isset($DA['unionid']) ? $DA['unionid'] : "";
        $wx_user->openid    = isset($DA['openid']) ? $DA['openid'] : "";
        $wx_user->name      = isset($DA['name']) ? $DA['name'] : "";
        $wx_user->email     = isset($DA['email']) ? $DA['email'] : "";
        $wx_user->username  = isset($DA['username']) ? $DA['username'] : "";
        $wx_user->phone     = isset($DA['phone']) ? $DA['phone'] : "";
        $wx_user->avatar    = isset($DA['avatar']) ? $DA['avatar'] : "";
        $wx_user->nickname  = isset($DA['nickName']) ? $DA['nickName'] : "";
        $wx_user->country   = isset($DA['country']) ? $DA['country'] : "";
        $wx_user->province  = isset($DA['province']) ? $DA['province'] : "";
        $wx_user->city      = isset($DA['city']) ? $DA['city'] : "";
        $wx_user->location  = isset($DA['location']) ? $DA['location'] : "";
        $wx_user->gender    = isset($DA['gender']) ? $DA['gender'] : "";
        // $wx_user->uid       = isset($DA['uid']) ? $DA['uid'] : 0;

        $wx_user->save();
      }

      return $wx_user;
    } catch (Exception $e) {
      throw $e;
    }
  }
  //#MARK: 获取公众号token
  /** 获取公众号token */
  public function getAccessToken($appid, $weixinType)
  {
    $tokenKey = $appid . '_token';

    return Cache::remember($tokenKey, 90, function () use ($appid, $weixinType) {
      $wxConfService = new WxConfService;
      $wxConf = $wxConfService->getWeixinConf($appid, $weixinType);

      if (!$wxConf) {
        // 处理错误，例如抛出异常或记录日志
        throw new \Exception("Failed to retrieve WeChat config for app ID: $appid");
      }

      $response = Http::get($this->wxApiUrl . '/cgi-bin/token', [
        'grant_type' => 'client_credential',
        'appid' => $wxConf['appid'],
        'secret' => $wxConf['app_secret'],
      ]);

      $data = $response->json();

      if (!isset($data['access_token'])) {
        // 处理access_token获取失败，记录日志或抛出异常
        throw new \Exception("Failed to obtain WeChat access token: " . $response->body());
      }

      return $data['access_token'];
    });
  }
  /**
   * 发送微信公众号订阅消息
   *
   * @param [type] $touser
   * @param [type] $template_id
   * @param [type] $page
   * @param [type] $content
   * @return void
   */
  public function sendSubMsg($appid, $touser, $template_id, $page, $content)
  {
    //access_token
    $access_token = $this->getAccessToken($appid, WeixinEnum::OFFICIAL_ACCOUNT);
    //请求url

    $url = $this->wxApiUrl . '/cgi-bin/message/subscribe/send?access_token=' . $access_token;
    //发送内容
    $data = [];
    //接收者（用户）的 openid
    $data['touser'] = $touser;
    //所需下发的订阅模板id
    $data['template_id'] = "DWDkKsAfQO53IoU0_JZ0W6ZqJbXOlkndGE4AW47wY34";
    //点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,（示例index?foo=bar）。该字段不填则模板无跳转。
    $data['page'] = $page;
    //模板内容，格式形如 { "key1": { "value": any }, "key2": { "value": any } }
    $data['data'] = [
      "first" => [
        "value" => $content['order_no']
      ],
      "keyword1" => [
        'value' => $content['repair_goods']
      ],
      "keyword2" => [
        'value' => $content['repair_content']
      ],
      "keyword3" => [
        'value' => $content['create_at']
      ],
      'keyword4' => [
        'value' => $content['open_person']
      ],
      'remark' => [
        'value' => $content['remark']
      ]
    ];

    //跳转小程序类型：developer为开发版；trial为体验版；formal为正式版；默认为正式版
    $data['miniprogram_state'] = 'formal';
    try {
      $http     = new Client;
      $result   = $http->post($url, $data);
      $response = $result->getBody()->getContents();
      return json_decode($response, true);
    } catch (Exception $e) {
      Log::error(__CLASS__ . $e->getMessage());
      return false;
    }
  }

  //#MARK: 微信小程序登录
  /**
   * 获取wxKey
   * @param $code 传入
   * @return array|mixed
   */
  public function wxXcxLogin($appid, $code)
  {
    // 微信小程序ID
    $wxConfService = new WxConfService;
    $weixinConf = $wxConfService->getWeixinConf($appid, WeixinEnum::MINI_PROGRAM);
    try {
      $params = http_build_query([
        'appid'       => $weixinConf['appid'],
        'secret'      => $weixinConf['AppSecret'],
        'js_code'     => $code,
        'grant_type'  => 'authorization_code'
      ]);
      $url = $this->wxApiUrl . '/sns/jscode2session?';
      $client = new Client;
      $result = $client->get($url . $params);
      $response = $result->getBody()->getContents();

      // Log::error($url . $params);
      Log::info($response);
      return json_decode($response, true);
    } catch (Exception $e) {
      Log::error(__CLASS__ . $e->getMessage());
      return false;
    }
  }

  //#MARK: 微信小程序绑定
  /**
   * 微信与用户绑定
   *
   * @param [type] $wxUser
   * @param [type] $userId
   * @return void
   */
  public function bindWx($unionid, $uid)
  {
    try {
      DB::transaction(function () use ($unionid, $uid) {
        // 使用 Eloquent 的 updateOrCreate 方法一步更新或创建
        User::where('id', $uid)->update(['unionid' => $unionid]);
        $this->wxUserModel()->where('unionid', $unionid)->update(['uid' => $uid]);
      });

      return true;
    } catch (Exception $e) {
      // 记录更详细的错误信息，例如用户 ID 和微信用户信息
      Log::error("绑定微信失败 - 用户ID: {uid}，微信信息: {wxUser}，错误信息: {message}", [
        'uid' => $uid,
        'message' => $e->getMessage(),
      ]);
      return false;
    }
  }

  //#MARK: 微信小程序解绑
  /**
   * 解绑微信
   *
   * @param [type] $userId
   * @return void
   */
  public function unBindWx($userId)
  {
    try {
      DB::transaction(function () use ($userId) {
        $data['unionid'] = "";
        User::whereId($userId)->update($data);
        $this->wxUserModel()->where('uid', $userId)->update(['uid' => 0]);
      });
      return true;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }

  /**
   * 处理新的微信用户
   *
   * @param [type] $wxResult
   * @return void
   */
  public function handleNewWxUser($wxResult)
  {
    $wxUser = $this->wxUserModel()->where('unionid', $wxResult['unionid'])->first();
    if (!$wxUser) {
      $wxUser = [
        'unionid'  => $wxResult['unionid'],
        'openid'   => $wxResult['openid'],
        'nickname' => $wxResult['nickname'] ?? "",
        'avatar'   => $wxResult['headimgurl'] ?? "",
      ];
      $this->saveWxUser($wxUser);
    }

    if (!$token = Auth::guard('mini_program')->claims(['guard' => 'mini_program'])->login($wxUser, false)) {
      throw new \Exception('Failed to create token');
    }

    $tokenResult = [
      'access_token' => $token,
      'token_type' => 'bearer',
      'expires' => auth()->factory()->getTTL() * 60,
      'user_info' => [
        'avatar' => $wxUser['avatar'],
        'nickname' => $wxUser['nickname'],
        'phone' => $wxUser['user_phone'],
        'email' => $wxUser['email'],
        'county' => $wxUser['country'],
        'province' => $wxUser['province'],
        'city' => $wxUser['city'],
        'gender' => $wxUser['gender']
      ]
    ];

    return $tokenResult;
  }




  /**
   * 获取微信 session_key
   *
   * @param string $code 用户登录凭证
   * @param string $appId 小程序AppID
   * @param string $appSecret 小程序AppSecret
   * @return string|null 返回session_key或null
   */
  public function getSessionKey($code, $appId)
  {
    $wxConf = $this->getWxConf($appId, WeixinEnum::MINI_PROGRAM);
    $response = Http::get('https://api.weixin.qq.com/sns/jscode2session', [
      'appid' => $appId,
      'secret' => $wxConf['app_secret'],
      'js_code' => $code,
      'grant_type' => 'authorization_code',
    ]);

    if ($response->successful()) {
      $data = $response->json();
      return $data['session_key'] ?? null;
    }
    return null;
  }

  /**
   * 解密数据
   *
   * @param string $encryptedData 加密数据
   * @param string $iv 初始向量
   * @param string $sessionKey 会话密钥
   * @param string $appId 小程序AppID
   * @return array|null 返回解密后的数据或null
   */
  public function decryptData($encryptedData, $iv, $sessionKey, $appId)
  {
    $aesKey = base64_decode($sessionKey);
    $aesIV = base64_decode($iv);
    $aesCipher = base64_decode($encryptedData);

    $result = openssl_decrypt($aesCipher, 'AES-128-CBC', $aesKey, OPENSSL_RAW_DATA, $aesIV);
    $dataObj = json_decode($result, true);

    if ($dataObj == null) {
      return null;
    }

    if ($dataObj['watermark']['appid'] != $appId) {
      return null;
    }

    return $dataObj;
  }



  private function getWxConf($appid, $type)
  {
    // 使用缓存键简化逻辑
    $wxConfService = new WxConfService;
    return $wxConfService->getWeixinConf($appid, $type);
  }
}
