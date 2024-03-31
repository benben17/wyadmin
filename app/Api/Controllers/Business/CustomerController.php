<?php

namespace App\Api\Controllers\Business;

use JWTAuth;
//use App\Exceptions\ApiException;
use Exception;
use App\Enums\AppEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Business\CusClue;
use App\Api\Models\Tenant\ExtraInfo;
use App\Api\Models\Tenant\TenantRoom;
use App\Api\Services\CustomerService;
use App\Api\Services\Sys\UserServices;
use App\Api\Controllers\BaseController;
use App\Api\Services\Common\DictServices;
use App\Api\Services\Tenant\BaseInfoService;
use App\Api\Services\Business\CusClueService;
use App\Api\Models\Common\Contact as ContactModel;

/**
 *  客户
 *  客户联系人   parent_type 2
 */
class CustomerController extends BaseController
{
    private $customerService;
    private $parent_type;
    public function __construct()
    {
        parent::__construct();
        $this->parent_type = AppEnum::Tenant;
        $this->customerService = new CustomerService;
    }

    /**
     * @OA\Post(
     *     path="/api/business/customer/list",
     *     tags={"客户"},
     *     summary="客户列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize","orderBy","order"},
     *       @OA\Property(property="list_type",type="int",description="// 1 客户列表 2 在租户 3 退租租户")
     *     ),
     *       example={
     *
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
        // $validatedData = $request->validate([
        //     'type' => 'required|int|in:1,2,3', // 1 客户列表 2 在租户 3 退租租户
        // ]);
        $pagesize = $this->setPagesize($request);
        $map = array();
        if ($request->id) {
            $map['id'] = $request->id;
        }

        if ($request->channel_id && $request->channel_id > 0) {
            $map['channel_id'] = $request->channel_id;
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


        $request->type = [1, 2, 3];  // 只显示客户，和退租客户 不显示租户
        $subQuery = $this->customerService->tenantModel()
            ->where($map)
            ->where(function ($q) use ($request) {
                $request->type && $q->whereIn('type', $request->type);
                $request->name && $q->where('name', 'like', '%' . $request->name . '%');
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->room_type && $q->where('room_type', $request->room_type);
                $request->source_type && $q->where('source_type', $request->source_type);
                $request->industry && $q->where('industry', $request->industry);
                $request->parent_id && $q->where('parent_id', 0);
                $request->state && $q->where('state', $request->state);

                if ($request->visit_times) {
                    $q->whereHas('follow', function ($q) {
                        $q->where('follow_type', AppEnum::followVisit);
                        $q->havingRaw('count(*) >0');
                    });
                }
                return UserServices::filterByDepartId($q, $this->user, $request->depart_id);
            });
        $result = $subQuery
            ->with('channel:channel_name,channel_type,id')
            // ->with('contacts')
            ->with('contactInfo')
            ->with('extraInfo')
            ->withCount('follow')
            ->withCount(['follow as visit_times' => function ($q) {
                $q->where('follow_type', AppEnum::followVisit);
            }])
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();


        // 通过数据库查询获取统计数据
        $cusStat = $subQuery
            ->selectRaw('state, ifnull(count(*),0) as count')
            ->groupBy('state')
            ->get();

        // 根据ID获取字典信息
        $dict = new DictServices;
        $cusStateDicts = $dict->getByKey([0, $this->company_id], 'cus_state');

        // 构建客户统计数据数组
        $customerStat = array();
        $cusTotalCount = 0;
        foreach ($cusStateDicts as $kt => $vt) {
            $count = $cusStat->firstWhere('state', $vt['value'])['count'] ?? 0;
            $customerStat[$kt] = [
                'state' => $vt['value'],
                'count' => $count,
            ];
            $cusTotalCount += $count;
        }

        // 添加客户总计到客户统计数据数组
        $customerStat[] = [
            'state' => '客户总计',
            'count' => $cusTotalCount
        ];


        // return response()->json(DB::getQueryLog());
        $data = $this->handleBackData($result);
        foreach ($data['result'] as $k => &$v) {
            $v['demand_area'] = $v['extra_info']['demand_area'] ?? "";
            $v['source_type_label'] = getDictName($v['source_type']);
            $v['contact_user'] = $v['contact_info']['name'] ?? "";
            $v['contact_phone'] = $v['contact_info']['phone'] ?? "";
            $v['channel_name'] = $v['channel']['channel_name'] ?? "";
            $v['channel_type'] = $v['channel']['channel_type'] ?? "";
        }
        $data['stat'] = $customerStat;
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/business/customer/add",
     *     tags={"客户"},
     *     summary="客户新增",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"channel_id","type","name"},
     *       @OA\Property(
     *          property="type",
     *          type="int",
     *          description="客户类型 1:公司 2 个人"
     *       ),
     *       @OA\Property(
     *          property="name",
     *          type="String",
     *          description="客户名称"
     *       ),
     *       @OA\Property(
     *          property="channel_id",
     *          type="int",
     *          description="渠道ID"
     *       ),
     *       @OA\Property(
     *          property="contact_name",
     *          type="String",
     *          description="联系人名称"
     *       ),
     *       @OA\Property(
     *          property="contact_phone",
     *          type="String",
     *          description="联系人电话"
     *       )
     *     ),
     *       example={
     *              "channel_id":1,"type":1,"name":"公司客户","contact_name":"","contact_phone":""
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
            'type' => 'required|in:1,2',
            'name' => 'required|String|max:64',
            'channel_id' => 'required|numeric|gt:0',
            'contacts' => 'array',
            'extra_info' => 'array',
            'tenant_rooms' => 'array'
        ]);
        $DA = $request->toArray();
        $map['company_id'] = $this->company_id;
        $map['name'] = $request->name;
        DB::enableQueryLog();
        $checkRepeat = $this->customerService->tenantModel()->where($map)->count();
        if ($checkRepeat) {
            return $this->error('客户名称重复');
        }
        // return response()->json(DB::getQueryLog());
        try {
            DB::transaction(function () use ($DA) {
                $user = auth('api')->user();
                // DB::enableQueryLog();
                $res = $this->customerService->saveTenant($DA, $user);
                //写入联系人
                $parent_id = $res->id;
                $cusExtra = $this->customerService->formatCusExtra($DA['extra_info']);
                $cusExtra['tenant_id'] = $parent_id;
                $cusExtra['c_uid'] = $this->uid;
                ExtraInfo::Create($cusExtra);
                // 写入联系人 支持多联系人写入

                if ($DA['contacts']) {
                    $user['parent_type'] = $this->parent_type;
                    $contacts = formatContact($DA['contacts'], $parent_id, $user);
                    // 联系人保存
                    $contact = new contactModel;
                    $contact->addAll($contacts);
                }
                // 房间
                if (!empty($DA['tenant_rooms']) && $DA['tenant_rooms']) {
                    $roomList = $this->customerService->formatCustomerRoom($DA['tenant_rooms'], $res->id, $DA['room_type']);
                    $rooms = new TenantRoom;
                    $rooms->addAll($roomList);
                }
                // 工商信息
                $businessInfo = $DA['business_info'];
                if ($businessInfo) {
                    $businessInfo['name'] = $DA['name'];
                    $info = new BaseInfoService;
                    $baseInfo = $info->model()->where('name', $businessInfo['name'])->first();
                    if ($baseInfo) {
                        $businessInfo['id'] = $baseInfo->id;
                        $business = $info->save($businessInfo, 2);   // 1 新增
                    } else {
                        $business = $info->save($businessInfo, 1);   // 1 新增
                    }
                    if ($business) {
                        $businessData['business_id'] = $business->id;
                        $this->customerService->tenantModel()->whereId($parent_id)->update($businessData);
                    }
                }
                // 线索更新
                if (isset($DA['clue_id']) && $DA['clue_id'] > 0) {
                    $clueService = new CusClueService;
                    $clueService->changeStatus($DA['clue_id'], 2, $parent_id);
                }

                //插入日志
                $cusLog['content'] = $user['realname'] . "创建客户【" . $res->name . '】';
                $cusLog['tenant_id'] = $parent_id;
                $this->customerService->saveTenantLog($cusLog, $user);
            });

            return $this->success('客户保存成功！');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error('客户保存失败！');
        }
    }



    /**
     * @OA\Post(
     *     path="/api/business/customer/edit",
     *     tags={"客户"},
     *     summary="客户编辑",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"channel_id","type","name","id"},
     *       @OA\Property(
     *          property="type",
     *          type="int",
     *          description="客户类型 1:公司 2 个人"
     *       ),
     *       @OA\Property(
     *          property="name",
     *          type="String",
     *          description="客户名称"
     *       ),
     *       @OA\Property(property="channel_id",type="int", description="渠道ID"),
     *       @OA\Property(
     *          property="contact_name",
     *          type="String",
     *          description="联系人名称"
     *       ),
     *       @OA\Property(
     *          property="contact_phone",
     *          type="String",
     *          description="联系人电话"
     *       )
     *     ),
     *       example={
     *             "channel_id":1,"type":1,"name":"公司客户","contact_name":"","contact_phone":""
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
        $validatedData = $request->validate([
            'id' => 'required|numeric|gt:0',
            'type' => 'required|in:1,2',
            'name' => 'required|String|max:64',
            'contacts' => 'array',
            'extra_info' => 'array',
            'tenant_rooms' => 'array'
        ]);
        $DA = $request->toArray();
        $map['company_id'] = $this->company_id;
        $map['name'] = $request->name;

        $checkRepeat = $this->customerService->tenantModel()->where($map)->where('id', '!=', $DA['id'])->exists();
        if ($checkRepeat) {
            return $this->error('客户名称重复!');
        }
        try {
            DB::transaction(function () use ($DA) {
                $user = auth('api')->user();
                DB::enableQueryLog();
                $this->customerService->saveTenant($DA, $user, 2);
                $cusExtra = $this->customerService->formatCusExtra($DA['extra_info']);
                $cusExtra['tenant_id'] = $DA['id'];
                $cusExtra['u_uid'] = $this->uid;
                ExtraInfo::where('tenant_id', $DA['id'])->update($cusExtra);
                // 写入联系人 支持多联系人写入

                $contacts = $DA['contacts'];
                if ($contacts) {
                    $user['parent_type'] = $this->parent_type;
                    $res = ContactModel::where('parent_id', $DA['id'])->where('parent_type', $this->parent_type)->delete();
                    $contacts = formatContact($contacts, $DA['id'], $user, 2);
                    $contact = new ContactModel;
                    $res = $contact->addAll($contacts);
                    Log::info("联系人" . $res);
                }
                // 房间
                if ($DA['tenant_rooms'] && !empty($DA['tenant_rooms'])) {
                    $res = TenantRoom::where('tenant_id', $DA['id'])->delete();  // 删除
                    $roomList = $this->customerService->formatCustomerRoom($DA['tenant_rooms'], $DA['id'], $DA['room_type']);
                    $rooms = new TenantRoom;
                    $rooms->addAll($roomList);
                }

                // 更新工商信息
                $businessInfo = $DA['business_info'];
                if ($businessInfo) {
                    $businessInfo['business_info_id'] = $DA['business_id'];
                    $businessInfo['name'] = $DA['name'];
                    $info = new BaseInfoService;
                    $res = $info->model()->where('name', $DA['name'])->first();
                    if ($res) {
                        $businessData['business_id'] = $res->id;
                        $this->customerService->tenantModel()::whereId($DA['id'])->update($businessData);
                        $businessInfo['id'] = $res->id;
                        $info->save($businessInfo, 2);
                    } else {
                        $info->save($businessInfo, 2);
                    }
                }
                $cusLog['content'] = '编辑客户【' . $DA['name'] . '】';
                $cusLog['tenant_id'] = $DA['id'];
                $cusLog = $this->customerService->saveTenantLog($cusLog, $user);
            });
            return $this->success('客户更新成功。');
        } catch (Exception $e) {
            Log::error("客户更新失败" . $e);
            return $this->error('客户更新失败');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/business/customer/distribute",
     *     tags={"客户"},
     *     summary="客户分配",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"Ids","belong_uid","distribute_uid"},
     *       @OA\Property(property="Ids",type="int",description="客户id"),
     *       @OA\Property(property="belong_uid",type="int",description="接收客户uid"),
     *     ),
     *       example={
     *              "id":1
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function distribute(Request $request)
    {
        $validatedData = $request->validate([
            'Ids' => 'required|array',
            'belong_uid' => 'required|int',
            'distribute_uid' => 'required|int',
        ]);
        if (!$this->user['is_admin']) {
            return $this->error("用户没有权限，请联系管理员。");
        }
        $data['belong_uid'] = $request->belong_uid;
        $data['distribute_uid'] = $this->uid;
        $res = $this->customerService->tenantModel()->whereIn('id', $request->Ids)->update($data);
        if ($res) {
            return $this->success("客户分配完成。");
        } else {
            return $this->error("客户分配失败！");
        }
    }


    /**
     * @OA\Post(
     *     path="/api/business/customer/show",
     *     tags={"客户"},
     *     summary="根据客户获取客户信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="客户id"
     *       )
     *     ),
     *       example={
     *              "id":1
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
            'id' => 'required|numeric|min:1',
        ]);
        $data = $this->customerService->tenantModel()
            ->with('contacts')
            ->with('extraInfo')
            ->with('tenantRooms')
            ->with('channel:id,channel_name,channel_type')
            ->find($request->id)->toArray();

        $info = new BaseInfoService;
        $data['source_type_label'] = getDictName($data['source_type']);
        $business_info  = $info->getById($data['business_id']);
        if (empty($business_info)) {
            $business_info = (object)[];
        }
        $clue = CusClue::where('tenant_id', $request->id)->first();
        $data['clue'] = (object)[];
        if ($clue) {
            $data['clue'] = $clue;
        }
        $data['business_info'] = $business_info;
        $data['channel_name'] = $data['channel']['channel_name'] ?? "";
        $data['channel_type'] = $data['channel']['channel_type'] ?? "";
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/business/customer/baseinfo",
     *     tags={"客户"},
     *     summary="获取客户基础的工商信息。公司名字或者客户必须传一个",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *       @OA\Property(
     *          property="name",
     *          type="String",
     *          description="公司名字，全名"
     *       ),
     *       @OA\Property(
     *          property="tenant_id",
     *          type="int",
     *          description="客户ID"
     *       )
     *     ),
     *       example={
     *              "name":"公司名字","tenant_id":1
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */

    public function getInfo(Request $request)
    {
        if (!$request->name) {
            return $this->error('客户名称必须传！');
        }
        $user = auth('api')->user();
        $skyeyeService = new BaseInfoService;
        $data = $skyeyeService->getCompanyInfo($request->name, $user);
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/business/customer/baseinfo/edit",
     *     tags={"客户"},
     *     summary="客户工商信息编辑",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required = {"id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="id"
     *       )
     *     ),
     *       example={
     *              "id":"1"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function editInfo(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|numeric|gt:0',
        ]);
        $DA = $request->toArray();
        $info = new BaseInfoService;
        $res = $info->save($DA, 2);

        if ($res) {
            return $this->success('公司工商信息编辑成功。');
        }
        return $this->error('公司工商信息编辑失败！');
    }
}
