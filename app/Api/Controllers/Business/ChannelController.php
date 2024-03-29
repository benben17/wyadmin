<?php

namespace App\Api\Controllers\Business;

use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Api\Controllers\BaseController;
use App\Api\Models\Channel\Channel as ChannelModel;
use App\Api\Models\Common\Contact as ContactModel;
use App\Api\Services\Common\DictServices;
use App\Api\Services\Channel\ChannelService;
use App\Api\Models\Channel\ChannelBrokerage as BrokerageModel;
use App\Api\Models\Project;
use App\Api\Models\Tenant\Tenant;
use App\Api\Excel\Business\ChannelExcel;
use App\Enums\AppEnum;
use Exception;

/**
 * 渠道管理
 * 类型 1 渠道 2 客户 3 租户 4供应商 5 公共关系
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
        \Validator::make($request->all(), [
            'proj_ids' => 'required',
        ])->validate();

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
        // Use a single query to fetch the main data
        $data = $this->channelService->model()->where($map)
            ->where(function ($q) use ($request) {
                $request->channel_name && $q->where('channel_name', 'like', '%' . $request->channel_name . '%');
                $request->channel_type && $q->where('channel_type', $request->channel_type);
                if ($request->proj_ids) {
                    // $q->orWhere(DB::Raw("proj_ids = ''"));
                    $q->whereRaw(" (proj_ids = '' or find_in_set('" . $request->proj_ids . "',proj_ids))");
                }
                $request->c_uid && $q->where('c_uid', $request->c_uid);
                $request->start_time && $q->where('created_at', '>=', $request->start_time);
                $request->end_time && $q->where('created_at', '<=', $request->end_time);
            })
            ->with([
                'channelPolicy:id,name',
                'channelContact',
            ])
            ->withCount('customer')
            ->withCount([
                'customer as cus_deal_count' => function ($q) {
                    $q->where('state', '成交客户');
                }
            ])

            ->whereHas('channelPolicy', function ($q) use ($request) {
                $request->policy_name && $q->where('name', 'like', '%' . $request->policy_name . '%');
            })
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)
            ->toArray();


        $data = $this->handleBackData($data);
        // return response()->json(DB::getQueryLog());

        if ($request->export) {
            return $this->exportToExcel($data['result'], ChannelExcel::class);
        }
        /** 根据渠道类型统计有多少渠道 */
        $dict = new DictServices;
        $stat = $dict->getByKey(getCompanyIds($this->uid), 'channel_type');
        $channelTypeCounts = [];
        $channelTotal = 0;
        $cusCount = 0;
        // $stat = array();
        foreach ($stat as $key => &$v) {
            $channel = $this->channelService->model()
                ->select(DB::Raw('group_concat(id) as Ids,count(id) as count'))
                ->where($map)
                ->where(function ($q) use ($request) {
                    $request->channel_name && $q->where('channel_name', 'like', '%' . $request->channel_name . '%');
                    if ($request->proj_ids) {
                        // $q->orWhere(DB::Raw("proj_ids = ''"));
                        $q->whereRaw(" proj_ids = '' or find_in_set('" . $request->proj_ids . "',proj_ids)");
                    }
                    $request->c_uid && $q->where('c_uid', $request->c_uid);
                    $request->start_time && $q->where('created_at', '>=', $request->start_time);
                    $request->end_time && $q->where('created_at', '<=', $request->end_time);
                })
                ->where('channel_type', $v['value'])
                ->with('channelPolicy:id,name')
                ->whereHas('channelPolicy', function ($q) use ($request) {
                    $request->policy_name && $q->where('name', 'like', '%' . $request->policy_name . '%');
                })
                ->first();

            $v['count'] = $channel['count'];
            $v['channel_type'] = $v['value'];
            if (empty($channel['Ids']) || !$channel['Ids']) {
                $v['cus_count'] = 0;
            } else {
                $Ids = explode(",", $channel['Ids']);
                $v['cus_count'] = Tenant::whereIn('channel_id', $Ids)->count();
            }
            $cusCount += $v['cus_count'];
            $channelTotal += $v['count'];
        }

        $stat = array_merge($stat, array(array('channel_type' => '总计', 'count' => $channelTotal, 'cus_count' => $cusCount)));
        // return response()->json(DB::getQueryLog());
        $data['stat'] = $stat;
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
            DB::transaction(function () use ($request) {
                $userInfo = auth('api')->user();
                $channel = $request->toArray();
                $channel = $this->formatChannel($channel); // 格式化数据
                $result = channelModel::Create($channel);
                if ($result &&  $request->channel_contact) {
                    $channel_id = $result->id;
                    $userInfo['parent_type'] = AppEnum::Channel;
                    $contacts = formatContact($request->channel_contact, $channel_id, $userInfo);
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
        $data = channelModel::with('channelContact')
            ->with('channelPolicy:id,name')
            ->with('channelMaintain')
            ->with('createUser:id,name')
            ->find($request->input('id'));
        DB::enableQueryLog();
        if ($data) {
            if ($data['proj_ids']  == '') {
                $data['proj_label'] = "全部项目";
            } else {
                $res = Project::selectRaw("group_concat(proj_name) as proj_name")
                    ->whereIn("id", str2Array($data['proj_ids']))->first();
                $data['proj_label'] =  $res->proj_name;
            }
        }
        // return response()->json(DB::getQueryLog());
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/business/channel/brokerage/list",
     *     tags={"渠道"},
     *     summary="根据渠道id获取渠道佣金信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(property="id",type="int", description="渠道ID"
     *       )
     *     ),
     *       example={"id": 11}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function brokerageList(Request $request)
    {
        $pagesize = $this->setPagesize($request);


        $map = array();
        if ($request->channel_id) {
            $map['channel_id'] = $request->channel_id;
        }
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

        $data = BrokerageModel::where($map)
            ->where(function ($q) use ($request) {
                $request->tenant_id && $q->where('tenant_id', $request->tenant_id);
            })
            ->whereHas('tenant', function ($q) use ($request) {
                if (!$this->user['is_admin']) {
                    if ($request->depart_id) {
                        $departIds = getDepartIds([$request->depart_id], [$request->depart_id]);
                        $q->whereIn('depart_id', $departIds);
                    }
                    if ($this->user['is_manager']) {
                        $departIds = getDepartIds([$this->user['depart_id']], [$this->user['depart_id']]);
                        $q->whereIn('depart_id', $departIds);
                    } else if (!$request->depart_id) {
                        $q->where('belong_uid', $this->uid);
                    }
                }
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
            })
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();
        if ($data) {
            $data = $this->handleBackData($data);
        }

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
        $validator = \Validator::make($request->all(), [
            'id' => 'required|numeric|gt:0',
            'channel_name' => 'required',
            'channel_name' => Rule::unique('bse_channel')->ignore($request->input('id')),
            'channel_contact' => 'required|array',
        ], $messages);

        $error = $validator->errors()->first();
        if ($error) {
            return $this->error($error);
        }
        $checkChannel = channelModel::findOrFail($request->id);
        if (!$checkChannel) {
            return $this->error('渠道不存在');
        }
        try {
            DB::transaction(function () use ($request) {
                $data = $request->toArray();
                $userinfo = auth('api')->user();
                $userinfo['parent_type'] = AppEnum::Channel;
                $channel = $this->formatChannel($data, 2); //编辑传入值
                //更新渠道
                DB::enableQueryLog();
                $res = channelModel::whereId($request->id)->update($channel);
                //更新或者新增渠道联系人
                if ($data['channel_contact']) {
                    $contacts = formatContact($data['channel_contact'], $request->id, $userinfo, 2);
                    ContactModel::where('parent_id', $request->id)->where('parent_type', $this->parent_type)->delete();
                    $contact = new ContactModel;
                    $contact->addAll($contacts);
                }
            });
            return $this->success('渠道更新成功');
        } catch (Exception $e) {
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


        $res = channelModel::whereIn('id', $request['Ids'])->update($data);
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
        if ($res) {
            return $this->success('渠道政策保存成功。');
        }
        return $this->error('渠道政策保存失败！');
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
        $pagesize = $this->setPagesize($request);
        $map = array();
        if ($request->id && $request->id > 0) {
            $map['id'] = $request->id;
        }
        // 是否可用 1 可用0禁用
        if ($request->input('is_vaild')) {
            $map['is_vaild'] = $request->input('is_vaild');
        }

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

        $BA = $request->toArray();
        DB::enableQueryLog();
        //
        $policy = new ChannelService;
        $data =  $policy->policyModel()->where($map)
            ->where(function ($q) use ($request) {
                $request->name && $q->where('name', 'like', '%' . $request->name . '%');
                $request->policy_type && $q->where('policy_type', $request->policy_type);
                $request->month && $q->where('month', $request->month);
            })
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();

        // return response()->json(DB::getQueryLog());
        $data = $this->handleBackData($data);
        return $this->success($data);
    }
    // return response()->json(DB::getQueryLog());

    private function formatChannel($DA, $type = 1)
    {
        if ($type == 1) {
            $BA['company_id'] = $this->company_id;
            $BA['c_uid'] = $this->uid;
            $BA['is_valid'] = $DA['is_vaild'];
            $BA['created_at'] = nowTime();
        } else {
            $BA['u_uid'] = $this->uid;
            $BA['id'] = $DA['id'];
        }
        $BA['channel_name'] = $DA['channel_name'];
        if (isset($DA['channel_addr'])) {
            $BA['channel_addr'] = $DA['channel_addr'];
        }
        if (isset($DA['channel_type'])) {
            $BA['channel_type'] = $DA['channel_type'];
        }
        if (isset($DA['policy_id'])) {
            $BA['policy_id'] = $DA['policy_id'];
        }
        if (isset($DA['brokerage_amount'])) {
            $BA['brokerage_amount'] = $DA['brokerage_amount'];
        }

        if (isset($DA['remark'])) {
            $BA['remark'] = $DA['remark'];
        }
        $BA['proj_ids'] = isset($DA['proj_ids']) ? $DA['proj_ids'] : "";

        return $BA;
    }
}
