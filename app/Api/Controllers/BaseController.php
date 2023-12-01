<?php

namespace App\Api\Controllers;

use App\Api\Controllers\Controller;

/**
 * @OA\Info(
 *     version="v1.0.0",
 *     title="物业管理系统 OpenApi"
 * )
 */
class BaseController extends Controller
{

    protected $uid;
    protected $company_id;
    protected $user;

    public function __construct()
    {
        $this->uid  = auth()->payload()->get('sub');
        if (!$this->uid) {
            return $this->error('用户信息错误');
        }
        $this->company_id = getCompanyId($this->uid);
        $this->user = auth('api')->user();
    }

    //成功返回
    public function success($data = "", $msg = "ok")
    {
        $this->parseNull($data);
        $result = [
            "code"      => 200,
            "message"   => $msg,
            "data"      => $data,
        ];
        return response()->json($result, 200);
    }

    //失败返回
    public function error($msg = "fail", $code = 4000)
    {
        $result = [
            "code" => $code,
            "message" => $msg,
        ];
        return response()->json($result, 200);
    }

    //如果返回的数据中有 null 则那其值修改为空 （安卓和IOS 对null型的数据不友好，会报错）
    public function parseNull(&$data)
    {
        array_walk_recursive($data, function (&$value) {
            $value = is_null($value) ? '' : $value;
        });
    }

    public function handleBackData($data)
    {
        $backData['result'] = $data['data'];
        $backData['pageInfo'] =  ['currentPage' => $data['current_page'], 'totalPage' => $data['last_page'], 'totalNum' => $data['total']];
        return $backData;
    }
    public function formatArray($data)
    {
        return array_map(function ($value) {
            return is_null($value) ? '' : $value;
        }, $data);
    }
}
