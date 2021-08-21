<?php

namespace App\Api\Controllers\Business;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Tenant\TenantRoom;
use App\Api\Models\Common\Contact as ContactModel;
use App\Api\Models\Tenant\ExtraInfo;
use App\Api\Services\CustomerService;
use App\Api\Services\Common\DictServices;
use App\Api\Services\Tenant\BaseInfoService;
use App\Enums\AppEnum;

/**
 *  客户
 *  客户联系人   parent_type 2
 */
class CustomerController extends BaseController
{

    public function __construct()
    {
        $this->uid  = auth()->payload()->get('sub');
        if (!$this->uid) {
            return $this->error('用户信息错误');
        }
        $this->company_id = getCompanyId($this->uid);
        $this->user = auth('api')->user();
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
        $validatedData = $request->validate([
            'type' => 'required|int|in:1,2,3', // 1 客户列表 2 在租户 3 退租租户
        ]);
        $pagesize = $request->input('pagesize');
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config('per_size');
        }
        if ($pagesize == '-1') {
            $pagesize = config('export_rows');
        }
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
        $request->type = [1, 2, 3];
        DB::enableQueryLog();
        $result = $this->customerService->tenantModel()
            ->where($map)
            ->where(function ($q) use ($request) {
                $request->type && $q->whereIn('type', $request->type);
                $request->name && $q->where('name', 'like', '%' . $request->name . '%');
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->belong_uid && $q->where('belong_uid', $request->belong_uid);
                $request->room_type && $q->where('room_type', $request->room_type);
                $request->state && $q->where('state', $request->state);
                if (!$this->user['is_admin']) {
                    $q->where('belong_uid', $this->uid);
                }
            })
            ->with('contacts')
            ->with('extraInfo')
            ->whereHas('extraInfo', function ($q) use ($request) {
                $request->demand_area && $q->where('demand_area', $request->demand_area);
            })
            // ->with('customerRoom')
            ->withCount('follow')
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();

        // return response()->json(DB::getQueryLog());
        $cusState = "";
        // if ($request->list_type == 1) {
        $customerStat = $this->customerService->tenantModel()
            ->select('state', DB::Raw('ifnull(count(*),0) as count'))
            ->where($map)
            ->where(function ($q) use ($request) {
                $request->type && $q->whereIn('type', $request->type);
                $request->belong_uid && $q->where('belong_uid', $request->belong_uid);
                $request->room_type && $q->where('room_type', $request->room_type);
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
            })
            ->groupBy('state')->get()->toArray();
        // return response()->json(DB::getQueryLog());
        $dict = new DictServices;  // 根据ID 获取字典信息
        $cusState = $dict->getByKey([0, $this->company_id], 'cus_state');

        $total = 0;
        foreach ($cusState as $kt => &$vt) {
            $vt['count'] = 0;
            $vt['state'] = $vt['value'];
            foreach ($customerStat as $k => $v) {
                if ($v['state'] == $vt['value']) {
                    $vt['state'] = $vt['value'];
                    $vt['count'] = $v['count'];
                    $total += $cusState[$kt]['count'];
                    break;
                } else {
                    $vt['count'] = 0;
                    $vt['state'] = $vt['value'];
                }
            }
        }
        $cus_total[sizeof($cusState)]['state'] = '客户总计';
        $cus_total[sizeof($cusState)]['count'] = $total;
        $cusState = array_merge($cusState, $cus_total);
        // }
        // return response()->json(DB::getQueryLog());
        $data = $this->handleBackData($result);
        foreach ($data['result'] as $k => &$v) {
            $v['demand_area'] = $v['extra_info']['demand_area'];
        }
        $data['stat'] = $cusState;
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
                $cusExtra = $this->formatCusExtra($DA['extra_info']);
                $cusExtra['tenant_id'] = $parent_id;
                $cusExtra['c_uid'] = $this->uid;
                $customerExtra = ExtraInfo::Create($cusExtra);
                // 写入联系人 支持多联系人写入

                if ($DA['contacts']) {
                    $user['parent_type'] = $this->parent_type;
                    $contacts = formatContact($DA['contacts'], $parent_id, $user);
                    // return $contacts;
                    $contact = new contactModel;
                    $contact->addAll($contacts);
                }
                // 房间
                if (!empty($DA['tenant_rooms']) && $DA['tenant_rooms']) {
                    $roomList = $this->formatRoom($DA['tenant_rooms'], $res->id, $DA['room_type']);
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
                // 插入跟进
                // $BA['cus_follow_type'] = $customer['cus_follow_type'];
                // $BA['cus_id'] =  $parent_id;
                // $BA['cus_follow_record'] = '首次跟进'.$customer['remark'];
                // $BA['cus_follow_time'] = $customer['cus_visit_date'];
                // $follow = new CustomerService;
                // $follow->saveFollow($data,$this->user);

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
     *          required={"id","type","tenant_idname"},
     *       @OA\Property(property="id",type="int",description="客户id"),
     *       @OA\Property(property="type",type="int",description="客户类型 1:公司 2 个人"),
     *       @OA\Property(property="name",type="String",description="客户名称")
     *     ),
     *       example={
     *              "id":1,"type":1,"name":"公司客户"
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
                $res = $this->customerService->saveTenant($DA, $user, 2);
                $cusExtra = $this->formatCusExtra($DA['extra_info']);
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
                    Log::error("联系人" . $res);
                }
                // 房间
                if ($DA['tenant_rooms'] && !empty($DA['tenant_rooms'])) {
                    $res = TenantRoom::where('tenant_id', $DA['id'])->delete();  // 删除
                    $roomList = $this->formatRoom($DA['tenant_rooms'], $DA['id'], $DA['room_type']);
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
     *       @OA\Property(property="distribute_uid",type="int",description="分配客户uid"),
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
        $data['distribute_uid'] = $request->distribute_uid;
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
            ->find($request->id)->toArray();

        $info = new BaseInfoService;
        $business_info  = $info->getById($data['business_id']);
        if (empty($business_info)) {
            $business_info = (object)[];
        }
        $data['business_info'] = $business_info;
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
     *          type="Strig",
     *          description="公司名字，全名"
     *       ),
     *       @OA\Property(
     *          property="tenant_id",
     *          type="Strig",
     *          description="客户ID"
     *       )
     *     ),
     *       example={
     *              "name":"北京玄墨信息科技有限公司"
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
        $data = $skyeyeService->getCompanyInfo($request->name, $user->toArray());
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






    private function formatCusExtra($DA)
    {
        $BA['demand_area'] = isset($DA['demand_area']) ? $DA['demand_area'] : "";
        // $BA['demand_area_end'] = isset($DA['demand_area_end'])? $DA['demand_area_end']:0.00;
        $BA['trim_state'] = isset($DA['trim_state']) ? $DA['trim_state'] : "";
        $BA['recommend_room_id'] = isset($DA['recommend_room_id']) ? $DA['recommend_room_id'] : "";
        $BA['recommend_room'] = isset($DA['recommend_room']) ? $DA['recommend_room'] : "";
        $BA['purpose_room'] = isset($DA['purpose_room']) ? $DA['purpose_room'] : 0.00;
        $BA['purpose_price'] = isset($DA['purpose_price']) ? $DA['purpose_price'] : 0.00;
        $BA['purpose_term_lease'] = isset($DA['purpose_term_lease']) ? $DA['purpose_term_lease'] : 0.00;
        $BA['purpose_free_time'] = isset($DA['purpose_free_time']) ? $DA['purpose_free_time'] : 0.00;
        $BA['current_proj'] = isset($DA['current_proj']) ? $DA['current_proj'] : "";
        $BA['current_addr'] = isset($DA['current_addr']) ? $DA['current_addr'] : "";
        $BA['current_area'] = isset($DA['current_area']) ? $DA['current_area'] : "";
        $BA['current_price'] = isset($DA['current_price']) ? $DA['current_price'] : "";
        return $BA;
    }

    private function formatRoom(array $DA, $tenantId, $roomType)
    {
        $rooms = array();
        foreach ($DA as $k => &$v) {
            $rooms[$k]['created_at']   = nowTime();
            $rooms[$k]['updated_at']   = nowTime();
            $rooms[$k]['tenant_id']    = $tenantId;
            $rooms[$k]['proj_id']    = $v['proj_id'];
            $rooms[$k]['proj_name']    = isset($v['proj_name']) ? $v['proj_name'] : "";
            $rooms[$k]['build_id']    = $v['build_id'];
            $rooms[$k]['build_no']    = $v['build_no'];
            $rooms[$k]['floor_id']    = $v['floor_id'];
            $rooms[$k]['floor_no']    = $v['floor_no'];
            $rooms[$k]['room_id']    = $v['room_id'];
            $rooms[$k]['room_no']    = $v['room_no'];
            $rooms[$k]['room_area']    = $v['room_area'];
            $rooms[$k]['room_type']    = isset($v['room_type']) ? $v['room_type'] : $roomType;
        }
        return $rooms;
    }
}
