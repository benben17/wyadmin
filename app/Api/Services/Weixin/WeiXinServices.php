<?php

namespace App\Api\Services\Weixin;

use Exception;
use App\Models\User;
use GuzzleHttp\Client;
<<<<<<< HEAD
use App\Enums\WeixinEnum;
use Illuminate\Support\Arr;
=======
>>>>>>> a87f70ac6d3e4a910b9b421854bc86614ccedae9
use App\Api\Models\Weixin\WxUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 微信公众号服务
 */
class WeiXinServices
{
<<<<<<< HEAD
  protected $wxApiUrl = 'https://api.weixin.qq.com';
  protected $wxConfService;
=======
>>>>>>> a87f70ac6d3e4a910b9b421854bc86614ccedae9
  public function wxUserModel()
  {
    $model = new WxUser;
    return $model;
    $this->wxConfService = new WxConfService;
  }
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
      }
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
      return $wx_user;
    } catch (Exception $e) {
      throw $e;
    }
  }

  /** 获取公众号token */
  public function getAccessToken($appid, $weixinType)
  {
    $tokenKey = $appid . '_token';
    if (Cache::has($tokenKey)) {
      $token = Cache::get($tokenKey);
      return $token;
    }

    $wxConf = $this->wxConfService->getWeixinConf($appid, $weixinType);
    //获取新的access_token
    $appid  = $wxConf['appid'];
    $secret = $wxConf['app_secret'];
    $url    = $this->wxApiUrl . "/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $secret;
    $res    = json_decode(file_get_contents($url), true);
    $access_token = $res['access_token'];
    Cache::put($tokenKey, $access_token, 60 * 60 * 1.5); // 缓存1.5小时
    return $access_token;
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
  public function sendSubMsg($touser, $template_id, $page, $content)
  {
    //access_token
    $access_token = $this->getAccessToken($appid, WeixinEnum::OFFICIAL_ACCOUNT);
    //请求url

<<<<<<< HEAD
    $url = $this->wxApiUrl . '/cgi-bin/message/subscribe/send?access_token=' . $access_token;
=======
    $url = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=' . $access_token;
>>>>>>> a87f70ac6d3e4a910b9b421854bc86614ccedae9
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

  /**
   * 获取wxKey
   * @param $code 传入
   * @return array|mixed
   */
  public function wxXcxLogin($appid, $code)
  {
    // 微信小程序ID
    $weixinConf = $this->wxConfService->getWeixinConf($appid, WeixinEnum::MINI_PROGRAM);
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

      Log::error($result->getBody());
      // Log::error($url . $params);
      Log::error($response);
      return json_decode($response, true);
    } catch (Exception $e) {
      Log::error(__CLASS__ . $e->getMessage());
      return false;
    }
  }

  /**
   * 微信与用户绑定
   *
   * @param [type] $wxUser
   * @param [type] $userId
   * @return void
   */
  public function bindWx($wxUser, $uid)
  {
    try {
      DB::transaction(function () use ($wxUser, $uid) {
        User::whereId($uid)->update(['unionid' => $wxUser['unionid']]);
        $this->wxUserModel()->updateOrCreate('unionid', $wxUser['unionid'], ['uid' => $uid]);
      });
      return true;
    } catch (Exception $e) {
      throw $e;
      Log::error($e->getMessage());
      return false;
    }
  }

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
   * 获取小程序openid ,unionid
   *
   * @param [type] $code
   * @return void
   */
  public function getMiniProgramOpenId($code, $appid)
  {
    try {
      $wxConf = $this->wxConfService->getWeixinConf($appid, WeixinEnum::MINI_PROGRAM);
      $params = [
        'appid' => $appid,
        'secret' => $wxConf['app_secret'],
        'js_code' => $code,
        'grant_type' => 'authorization_code'
      ];
      $url = $this->wxApiUrl . 'sns/jscode2session?' . http_build_query($params);
      return json_decode(file_get_contents($url), true);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception($e->getMessage());
      return false;
    }
  }
}
