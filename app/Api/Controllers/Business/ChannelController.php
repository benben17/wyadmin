<?php

namespace App\Api\Controllers\Business;

use Exception;
use App\Enums\AppEnum;
use App\Api\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Controllers\BaseController;
use App\Api\Excel\Business\ChannelExcel;
use Illuminate\Support\Facades\Validator;
use App\Api\Services\Channel\ChannelService;
use App\Api\Models\Common\Contact as ContactModel;

/**
 * 渠道管理
 * 类型 1 渠道 2 客户 3 租户 4供应商 5 公共关系
 */
/**
 * @OA\Tag(
 *     name="渠道",
 *     description="渠道管理/渠道政策"
 * )
 */
class ChannelController extends BaseController
{
    private $parent_type;
    private $channelService;
    public function __construct()
    {
        parent::__construct();
        $this->parent_type = AppEnum::Channel;
        $this->channelService = new ChannelService;
    }
    /**
     * @OA\Post(
     *     path="/api/business/channel/list",
     *     tags={"渠道"},
     *     summary="渠道列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize"},
     *       @OA\Property(
     *          property="pagesize",
     *          type="int",
     *          description="每页行数"
     *       ),
     *       @OA\Property(
     *          property="channel_name",
     *          type="string",
     *          description="渠道名称"
     *       )
     *     ),
     *       example={
     *          "?pagesize=10"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function index(Request $request)
    {

        $pagesize = $this->setPagesize($request);
        $map = array();
        // 渠道ID
        if ($request->id && $request->id > 0) {
            $map['id'] = $request->id;
        }
        // 是否可用 1 可用0禁用
        if ($request->input('is_valid')) {
            $map['is_valid'] = $request->input('is_valid');
        }

        // 渠道类型
        // 排序字段
        if ($request->input('orderBy')) {
            $orderBy = $request->input('orderBy');
        } else {
            $orderBy = 'created_at';
        }
        // 排序方式desc 倒叙 asc 正序
        if ($request->input('order')) {
            $order = $request->input('order');
        } else {
            $order = 'desc';
        }

        DB::enableQueryLog();
        $subQuery = $this->channelService->model()->where($map)
            ->where(function ($q) use ($request) {
                $request->channel_name && $q->where('channel_name', 'like', '%' . $request->channel_name . '%');
                $request->channel_type && $q->where('channel_type', $request->channel_type);
                if ($request->proj_ids) {
                    $q->whereRaw(" (proj_ids = '' or find_in_set('" . $request->proj_ids . "',proj_ids))");
                }
                $request->c_uid && $q->where('c_uid', $request->c_uid);
                $request->start_time && $q->where('created_at', '>=', $request->start_time);
                $request->end_time && $q->where('created_at', '<=', $request->end_time);
            })->whereHas('channelPolicy', function ($q) use ($request) {
                $request->policy_name && $q->where('name', 'like', '%' . $request->policy_name . '%');
            });


        $data = $subQuery->with(['channelPolicy:id,name', 'channelContact'])
            ->withCount('customer')
            ->withCount([
                'customer as cus_deal_count' => function ($q) {
                    $q->where('state', '成交客户');
                }
            ]);
        $data = $this->pageData($data, $request);
        if ($request->export) {
            return $this->exportToExcel($data['result'], ChannelExcel::class);
        }
        // 统计渠道类型的客户数量以及渠道数量
        $data['stat'] = $this->channelService->statChannel($subQuery, $this->uid);
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/business/channel/customer",
     *     tags={"渠道"},
     *     summary="渠道查看-查看此渠道带来的客户信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize"},
     *       @OA\Property(
     *          property="pagesize",
     *          type="int",
     *          description="每页行数"
     *       ),
     *       @OA\Property(
     *          property="channel_id",
     *          type="int",
     *          description="渠道ID"
     *       )
     *     ),
     *       example={
     *          "channel_id":1
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    /** 获取渠道带来的客户 */
    public function getCustomer(Request $request)
    {
        $validatedData = $request->validate([
            'channel_id' => 'required|numeric',
        ]);
        $pagesize = $this->setPagesize($request);

        // DB::enableQueryLog();
        $data = Tenant::with('extraInfo:tenant_id,demand_area,id')
            ->withCount(['brokerageLog as brokerage_amount' => function ($q) {
                $q->select(DB::Raw('sum(brokerage_amount)'));
            }])
            ->where('channel_id', $request->channel_id)
            ->paginate($pagesize);
        // return response()->json(DB::getQueryLog());
        $data = $this->handleBackData($data);
        return $this->success($data);
    }


    /**
     * @OA\Post(
     *     path="/api/business/channel/add",
     *     tags={"渠道"},
     *     summary="渠道新增",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"channel_name","channel_type","channel_contact"},
     *       @OA\Property(
     *          property="channel_name",
     *          type="String",
     *          description="渠道名称"
     *       ),
     *       @OA\Property(
     *          property="channel_type",
     *          type="String",
     *          description="渠道类型，从接口dict中获取"
     *       ),
     *       @OA\Property( property="channel_contact",
     *       type="list",
     *       description="渠道联系人")
     *     ),
     *       example={
     *              "channel_name": "1","channel_type":"type","channel_contact":"[{}]"
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
            'channel_name' => 'required|String|max:64',
            'channel_name' => Rule::unique('bse_channel'),
            'channel_type' => 'required',
            'is_vaild' => 'required|numeric',
            'channel_contact' => 'array',
        ]);
        try {
            $userInfo = $this->user;
            $userInfo['parent_type'] = AppEnum::Channel;
            DB::transaction(function () use ($request, $userInfo) {
                $channel = $this->channelService->formatChannel($request->toArray(), $userInfo); // 格式化数据
                $result = $this->channelService->model()->Create($channel);
                if ($result &&  $request->channel_contact) {
                    $channelId = $result->id;
                    $contacts = formatContact($request->channel_contact, $channelId, $userInfo);
                    if ($contacts) {
                        $contact = new ContactModel;
                        $contact->addAll($contacts);
                    }
                }
            });
            return $this->success('渠道新增成功！');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error("渠道新增失败！");
        }
    }

    /**
     * @OA\Post(
     *     path="/api/business/channel/show",
     *     tags={"渠道"},
     *     summary="根据渠道id获取渠道信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="渠道ID"
     *       )
     *     ),
     *       example={
     *              "id": 11
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function show(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|numeric|gt:0',
        ]);
        $data = $this->channelService->model()->with('channelContact')
            ->with('channelPolicy:id,name')
            ->with('channelMaintain')
            ->with('createUser:id,name,realname')
            ->find($request->input('id'));
        DB::enableQueryLog();
        if (!$data) {
            return $this->success($data);
        }
        $projIds = $data->proj_ids ?? false;
        if (!$projIds) {
            $data['proj_label'] = "全部项目";
        } else {
            $res = Project::selectRaw("group_concat(proj_name) as proj_name")
                ->whereIn("id", str2Array($projIds))->first();
            $data['proj_label'] =  $res->proj_name;
        }
        // return response()->json(DB::getQueryLog());
        return $this->success($data);
    }


    /**
     * @OA\Post(
     *     path="/api/business/channel/edit",
     *     tags={"渠道"},
     *     summary="渠道编辑",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"channel_name","channel_type","id"},
     *      @OA\Property(
     *       property="id",
     *       type="int",
     *       description="渠道Id")
     *     ),
     *       @OA\Property(
     *          property="channel_name",
     *          type="String",
     *          description="渠道名称"
     *       ),
     *       @OA\Property(
     *          property="channel_type",
     *          type="String",
     *          description="渠道类型，从接口dict中获取"
     *       ),
     *       example={
     *              "channel_name": "1","channel_type":"type","id":"1"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function update(Request $request)
    {
        $messages = [
            'id.required' => '渠道信息不存在!',
            'channel_name.required' => '渠道信息名称不能为空!',
            'channel_name.unique' => '渠道名称重复!'
        ];
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|gt:0',
            'channel_name' =>  ['required', Rule::unique('bse_channel')->ignore($request->input('id'))],
            'channel_contact' => 'required|array',
        ], $messages);

        $error = $validator->errors()->first();
        if ($error) {
            return $this->error($error);
        }
        $checkChannel = $this->channelService->model()->findOrFail($request->id);
        if (!$checkChannel) {
            return $this->error('渠道不存在');
        }
        try {
            DB::transaction(function () use ($request) {
                $channelId = $request->id;
                $channelData = $request->toArray();
                // $userInfo = $this->user;
                $userInfo['parent_type'] = AppEnum::Channel;
                $channel = $this->channelService->formatChannel($channelData, $userInfo, 2); //编辑传入值
                //更新渠道
                $this->channelService->model()->whereId($channelId)->update($channel);
                // //更新或者新增渠道联系人
                if ($channelData['channel_contact']) {
                    $contacts = formatContact($channelData['channel_contact'], $channelId, $userInfo, 2);
                    ContactModel::where('parent_id', $channelId)->where('parent_type', $this->parent_type)->delete();
                    $contact = new ContactModel;
                    $contact->addAll($contacts);
                }
            });
            return $this->success('渠道更新成功');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error('渠道更新失败!');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/business/channel/enable",
     *     tags={"渠道"},
     *     summary="渠道禁用启用",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"Ids"},
     *       @OA\Property(
     *          property="Ids",
     *          type="list",
     *          description="渠道ID集合"
     *       )
     *     ),
     *       example={
     *              "Ids": "[]"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */

    public function enable(Request $request)
    {
        $validatedData = $request->validate([
            'Ids' => 'required',
            'is_valid' => 'required|numeric|in:0,1',
        ]);
        // 0 禁用 1 启用
        $data['is_valid'] = $request->is_valid;
        DB::enableQueryLog();


        $res = $this->channelService->model()->whereIn('id', $request['Ids'])->update($data);
        // return response()->json(DB::getQueryLog());
        if ($res) {
            return $this->success("渠道更新成功.");
        } else {
            return $this->error('渠道更新失败!');
        }
    }
    /**
     * @OA\Post(
     *     path="/api/business/channel/policy/add",
     *     tags={"渠道"},
     *     summary="渠道政策添加",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"name","month","policy_type"},
     *       @OA\Property(property="name",type="String",description="渠道政策名称"),
     *       @OA\Property(property="month",type="numeric",description="几个月租金"),
     *       @OA\Property(property="policy_type",type="int",description="类型")
     *     ),
     *       example={
     *              "name": "","month":"","policy_type",""}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function storePolicy(Request $request)
    {
        $validatedData = $request->validate([
            'policy_type' => 'required|int|in:1,0',
            'name' => 'required|String|min:1|max:64',
            'month' => 'required',
        ]);
        $BA = $request->toArray();
        $policy = new ChannelService;
        $check =  $policy->policyExists($BA);
        if ($check) {
            return $this->error('渠道佣金政策名称！');
        }
        $res = $policy->savePolicy($BA, $this->user);
        if ($res) {
            return $this->success('渠道政策保存成功。');
        }
        return $this->error('渠道政策保存失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/business/channel/policy/edit",
     *     tags={"渠道"},
     *     summary="渠道政策更新",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"name","id"},
     *       @OA\Property(property="name",type="String",description="渠道政策名称"),
     *       @OA\Property(property="id",type="int",description="政策ID")
     *     ),
     *       example={
     *              "name": "","id":""}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function updatePolicy(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|integer|gt:0',
            'name' => 'required|String|min:1|max:64',
        ]);

        $BA = $request->toArray();
        $policy = new ChannelService;
        if ($request->is_vaild == 0) {
            if ($policy->policyIsUsed($BA)) {
                return $this->error('渠道政策在被其他的渠道使用不能禁用！');
            }
        }

        $check =  $policy->policyExists($BA);
        if ($check) {
            return $this->error('渠道佣金政策名称重复！');
        }
        $res = $policy->savePolicy($BA, $this->user);
        return $res ? $this->success('渠道政策保存成功。') : $this->error('渠道政策保存失败！');
    }

    public function showPolicy(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|integer|gt:0',
        ]);
        $policy = new ChannelService;
        $data = $policy->policyModel()->find($request->id);

        return $this->success($data);
    }

    /**
     * 渠道政策列表
     *
     * @Author leezhua
     * @DateTime 2024-01-14
     * @param Request $request
     *
     * @return void
     */
    public function policyList(Request $request)
    {

        $map = array();
        if ($request->id && $request->id > 0) {
            $map['id'] = $request->id;
        }
        // 是否可用 1 可用0禁用
        if (isset($request->is_vaild) && empty($request->is_vaild)) {
            $map['is_vaild'] = $request->input('is_vaild');
        }
        //
        $policy = new ChannelService;
        $subQuery =  $policy->policyModel()->where($map)
            ->where(function ($q) use ($request) {
                $request->name && $q->where('name', 'like', '%' . $request->name . '%');
                $request->policy_type && $q->where('policy_type', $request->policy_type);
                $request->month && $q->where('month', $request->month);
            });

        // return response()->json(DB::getQueryLog());
        $data = $this->pageData($subQuery, $request);
        return $this->success($data);
    }
}
