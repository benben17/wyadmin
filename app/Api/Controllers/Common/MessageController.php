<?php

namespace App\Api\Controllers\Common;

use JWTAuth;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Services\Common\MessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 *
 */
class MessageController extends BaseController
{
    public function __construct()
    {
        $this->uid  = auth()->payload()->get('sub');
        if (!$this->uid) {
            return $this->error('用户信息错误');
        }
        $this->company_id = getCompanyId($this->uid);
        $this->msgService = new MessageService;
    }

    /**
     * @OA\Post(
     *     path="/api/common/msg/list",
     *     tags={"消息"},
     *     summary="消息列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"is_read"},
     *       @OA\Property(property="type",type="int",description="1:通知消息 2待办消息"),
     *       @OA\Property(property="is_read",type="int",description="1 所有 2未读 3 已读")
     *     ),
     *       example={
     *              "type":"1","is_read":"0"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function list(Request $request)
    {
        $validatedData = $request->validate([
            'is_read' => 'required|int|in:1,2,3',
        ]);
        $DA = $request->toArray();
        $pagesize = $request->input('pagesize');
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config("per_size");
        }
        $map = array();
        // 消息类型
        if ($request->input('type')) {
            $map['type'] = $request->input('type');
        }
        // 排序字段
        if ($request->input('orderBy')) {
            $orderBy = $request->input('orderBy');
        } else {
            $orderBy = 'id';
        }
        // 排序方式desc 倒叙 asc 正序
        if ($request->input('order')) {
            $order = $request->input('order');
        } else {
            $order = 'desc';
        }
        $companyId = array(0, $this->company_id);
        // return $this->uid;
        DB::enableQueryLog();
        $data = $this->msgService->msgModel()->where($map)
            ->whereIn('company_id', $companyId)
            ->where(function ($q) {
                $q->whereRaw('FIND_IN_SET(' . $this->uid . ',receive_uid)');
                $q->orWhere('receive_uid', -1);
            })
            ->when($DA['is_read'], function ($query) use ($DA) {
                if ($DA['is_read'] == 2) {
                    return $query->whereDoesntHave('messageRead', function ($q) use ($DA) {
                        return $q->where('uid', $this->uid);
                    });
                } else if ($DA['is_read'] == 3) {
                    return $query->whereHas('messageRead', function ($q) use ($DA) {
                        return $q->where('uid', $this->uid)->where('is_delete', 0);
                    });
                } else {
                    return $query->whereDoesntHave('messageRead', function ($q) use ($DA) {
                        return $q->where('is_delete', 1)->where('uid', $this->uid);
                    });
                }
            })
            ->with(['messageRead' => function ($q) {
                $q->where('uid', $this->uid);
            }])
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();
        // return response()->json(DB::getQueryLog());

        $data = $this->handleBackData($data);
        return $this->success($data);
    }


    /**
     * @OA\Post(
     *     path="/api/common/msg/setread",
     *     tags={"消息"},
     *     summary="设置消息已读",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"msgIds"},
     *       @OA\Property(
     *          property="msgIds",
     *          type="list",
     *          description="消息ID集合"
     *       )
     *     ),
     *       example={ "msgIds": "[]",}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function setRead(Request $request)
    {
        $validatedData = $request->validate([
            'msgIds' => 'required|array',
        ]);


        $res = $this->msgService->setRead($request->msgIds, $this->uid);
        if ($res) {
            return $this->success('success');
        }
        return $this->error('failed');
    }

    /**
     * @OA\Post(
     *     path="/api/common/msg/send",
     *     tags={"消息"},
     *     summary="发送消息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"content","role_id"},
     *       @OA\Property(
     *          property="content",
     *          type="String",
     *          description="消息内容"
     *       ),@OA\Property(
     *          property="role_id",
     *          type="String",
     *          description="接受人uid 多个逗号隔开"
     *       )
     *     ),
     *       example={
     *              "type":1,"content":"","role_id":""
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */

    public function store(Request $request)
    {
        $validatedData = $request->validate([

            'content' => 'required|String',
            'role_id' => 'required|String', // 多个角色用逗号隔开
        ]);
        $DA = $request->toArray();

        $roleIds = explode(',', $DA['role_id']);
        if (sizeof($roleIds) == 0) {
            return  $this->error('请传入正确的角色ID!');
        }

        $DA['type'] = 1;
        $user = auth('api')->user();
        $res = $this->msgService->sendMsg($DA, $user);
        if ($res) {
            return $this->success('消息发送成功。');
        }
        return $this->error('消息发送失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/common/msg/del",
     *     tags={"消息"},
     *     summary="删除个人消息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"msgIds"},
     *       @OA\Property(property="msgIds",type="list",description="消息ID集合")
     *     ),
     *       example={"msgIds":"[1,2,3]"}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function delete(Request $request)
    {
        $validatedData = $request->validate([
            'msgIds' => 'required|array',
        ]);

        $res = $this->msgService->deleteMsg($request->msgIds, $this->uid);
        if ($res) {
            return $this->success('消息删除成功。');
        }
        return $this->success('消息删除失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/common/msg/revoke",
     *     tags={"消息"},
     *     summary="消息撤回",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(property="id",type="int",description="消息ID")
     *     ),
     *       example={"id":"1"}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function revoke(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|int|gt:0',
        ]);
        $msg = $this->msgService->whereId($request->id)
            ->where('sender_uid', $this->uid)->first();
        $revokeTime = getVariable($this->company_id, 'msg_revoke_time');
        if (strtotime($msg['created_at']) + $revokeTime * 60 <= time()) {
            return $this->error('发送消息超过' . $revokeTime . '分钟不允许撤销！');
        }
        try {
            DB::transaction(function () use ($msg) {
                $this->msgReadModel()->where('msg_id', $msg->id)->delete();
                $msg->delete();
            });
            return $this->success('消息撤回成功。');
        } catch (Exception $e) {
            Log::error($e . getMessage());
            return $this->success('消息撤回失败！');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/common/msg/send/list",
     *     tags={"消息"},
     *     summary="查看我的发送列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={},
     *     ),
     *       example={
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function sendList(Request $request)
    {
        $DA = $request->toArray();
        $pagesize = $request->input('pagesize');
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config("per_size");
        }
        $map = array();
        // 排序字段
        if ($request->input('orderBy')) {
            $orderBy = $request->input('orderBy');
        } else {
            $orderBy = 'id';
        }
        // 排序方式desc 倒叙 asc 正序
        if ($request->input('order')) {
            $order = $request->input('order');
        } else {
            $order = 'desc';
        }
        $map['sender_uid'] = $this->uid;
        $map['company_id'] = $this->company_id;
        DB::enableQueryLog();
        $data = $this->msgService->msgModel()
            ->where($map)
            ->withCount('messageRead')
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();
        // return response()->json(DB::getQueryLog());
        $data = $this->handleBackData($data);
        $revokeTime = getVariable($this->company_id, 'msg_revoke_time');
        foreach ($data['result'] as $k => &$v) {
            if (strtotime($v['created_at']) + $revokeTime * 60 <= time()) {
                $v['is_revoke'] = 0;
            } else {
                $v['is_revoke'] = 1;
            }
        }
        return $this->success($data);
    }
    /**
     * @OA\Post(
     *     path="/api/common/msg/show",
     *     tags={"消息"},
     *     summary="查看接收的消息详情，或者是发送的消息详情",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id","show_type"},
     *       @OA\Property(property="id",type="int",description="消息ID"),
     *       @OA\Property(property="show_type",type="int",description="1自己发送的消息，2查看接收")
     *     ),
     *       example={"id":"","show_type":""}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function msgShow(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|int|gt:0',
            'show_type' => 'required|int|in:1,2',  // 1 查看自己发送的消息 2 查看接收的消息
        ]);
        $map['id'] = $request->id;
        if ($request->show_type == 1) {
            $map['sender_uid'] = $this->uid;
            $map['company_id'] = $this->company_id;
        }
        DB::enableQueryLog();
        $msg = $this->msgService->msgModel()->where($map)->first()->toArray();
        if (!$msg) {
            return $this->error('没有数据');
        }
        $msgService = new MessageService;
        if ($request->show_type == 1) {
            // 补充已读人员、未读人员信息
            $msg = $msgService->sendShow($msg);
        } else {
            // 未读消息点击查看设置为已读
            $msgService->setRead($request->id, $this->uid);
        }
        return $this->success($msg);
    }
    /**
     * @OA\Post(
     *     path="/api/common/msg/count",
     *     tags={"消息"},
     *     summary="统计消息未读已读总数",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id","show_type"},
     *       @OA\Property(property="type",type="int",description="1通知消息2待办消息")
     *     ),
     *       example={"type":""}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function msgCount(Request $request)
    {
        $map = array();
        // 消息类型

        if ($request->input('type')) {
            $map['type'] = $request->input('type');
        }

        $companyId = array(0, $this->company_id);
        DB::enableQueryLog();
        $unread_count = $this->msgService->msgModel()->where($map)
            ->whereIn('company_id', $companyId)
            ->where(function ($q) {
                $q->whereRaw('FIND_IN_SET(' . $this->uid . ',receive_uid)');
                $q->orWhere('receive_uid', -1);
            })
            ->whereDoesntHave('messageRead', function ($q) {
                return $q->where('uid', $this->uid);
            })
            ->count();
        $msg['unread_count']  = $unread_count;
        $read_count = $this->msgService->msgModel()->where($map)
            ->whereIn('company_id', $companyId)
            ->where(function ($q) {
                $q->whereRaw('FIND_IN_SET(' . $this->uid . ',receive_uid)');
                $q->orWhere('receive_uid', -1);
            })
            ->whereHas('messageRead', function ($q) {
                return $q->where('uid', $this->uid)->where('is_delete', 0);
            })->count();
        $msg['read_count'] = $read_count;
        $msg['total'] = $msg['read_count'] + $msg['unread_count'];
        // return response()->json(DB::getQueryLog());
        return $this->success($msg);
    }
}
