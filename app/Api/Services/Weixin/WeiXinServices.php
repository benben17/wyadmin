<?php

namespace App\Api\Services\Weixin;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Api\Models\Weixin\WxInfo;
use GuzzleHttp\Client;
use App\Models\User;

/**
 * 微信公众号服务
 */
class WeiXinServices
{
  public function wxModel()
  {
    $model = new WxInfo;
    return $model;
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
      $wxModel = $this->wxModel();
      $wx_user = $wxModel->where('unionid', $DA['unionid'])->first();
      if (!$wx_user) {
        $wx_user = new WxInfo;
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
      $wx_user->uid       = isset($DA['uid']) ? $DA['uid'] : 0;
      return $wx_user->save();
    } catch (Exception $e) {
      throw $e;
    }
  }

  /** 获取公众号token */
  public function getAccessToken()
  {
    //当前时间戳
    $now_time = strtotime(date('Y-m-d H:i:s', time()));
    //失效时间
    $timeout = 7200;
    //判断access_token是否过期
    $before_time = $now_time - $timeout;
    //未查找到就为过期
    // $map['open_id'] = $openId;
    // $access_token = $this->wxModel()->find(1);
    //如果过期

    //获取新的access_token
    $weixinConf = config('weixin');
    $appid  = $weixinConf['appid'];
    $secret = $weixinConf['AppSecret'];
    $url    = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $secret;
    $res    = json_decode(file_get_contents($url), true);
    $access_token = $res['access_token'];
    //更新数据库
    // $update = ['access_token' => $access_token, 'update_time' => $now_time];

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
    $access_token = $this->getAccessToken();
    //请求url
    $url = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=' . $access_token;
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
  public function wxKey($code)
  {
    /**
     * code 换取 session_key
     * ​这是一个 HTTPS 接口，开发者服务器使用登录凭证 code 获取 session_key 和 openid。
     * 其中 session_key 是对用户数据进行加密签名的密钥。为了自身应用安全，session_key 不应该在网络上传输。
     */
    // 微信小程序ID
    $weixinConf = config('weixin');
    try {
      $params = http_build_query([
        'appid'       => $weixinConf['appid'],
        'secret'      => $weixinConf['AppSecret'],
        'js_code'     => $code,
        'grant_type'  => 'authorization_code'
      ]);
      $url = 'https://api.weixin.qq.com/sns/jscode2session?';
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
  public function bindWx($unionid, $userId, $wxUser)
  {
    $map['unionid'] = $unionid;
    $map['id'] = $userId;
    $res =  User::where($map)->count();
    if ($res > 0) {
      return false;
    }
    try {
      DB::transaction(function () use ($unionid, $userId, $wxUser) {
        $data['unionid'] = $unionid;
        User::whereId($userId)->update($data);
        if ($wxUser) {
          $wxUser['avatar'] = isset($wxUser['avatarUrl']) ? $wxUser['avatarUrl'] : "";
          $wxUser['unionid'] = $unionid;
          $this->saveWxUser($wxUser);
        }
      });
      return true;
    } catch (Exception $e) {
      Log::error($unionid . "unionid");
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
        // $this->wxModel()->where('uid', $userId)->update(['uid' => 0]);
      });
      return true;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }

  public function checkBind($map)
  {
    return $this->wxModel()->where($map)->count();
  }
}
