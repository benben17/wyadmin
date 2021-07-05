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

use App\Api\Models\Customer\Customer as CustomerModel;
use App\Api\Models\Customer\CustomerExtra as CustomerExtraModel;
use App\Api\Models\Common\Contact as ContactModel;
use App\Api\Services\CustomerInfoService;
use App\Api\Services\CustomerService;
use App\Api\Models\Customer\CustomerRoom as CustomerRoomModel;
use App\Api\Services\Common\DictServices;
use App\Api\Models\Customer\CustomerFollow;

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
        $this->parent_type = 2;
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
        //         'list_type' => 'required|int|in:1,2,3', // 1 客户列表 2 在租户 3 退租租户
        //     ]);
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

        // if ($request->list_type ==1){
        //     $cus_type = [1,2,3];
        // } else if ($request->list_type ==2) {   // 只查询租户
        //     $cus_type = [2];
        // } else if ($request->list_type ==3) {   // 查询退租
        //     $cus_type = [3];
        // }


        DB::enableQueryLog();
        $result = CustomerModel::where($map)
            ->where(function ($q) use ($request) {
                // $cus_type && $q->whereIn('cus_type', $cus_type);
                $request->cus_name && $q->where('cus_name', 'like', '%' . $request->cus_name . '%');
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->belong_uid && $q->where('belong_uid', $request->belong_uid);
                $request->room_type && $q->where('room_type', $request->room_type);
            })
            ->with('customer_contact')
            ->with('customerExtra')
            // ->with('customerRoom')
            ->withCount('customerFollow')
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();

        // return response()->json(DB::getQueryLog());
        $cusState = "";
        // if ($request->list_type == 1) {
        $customerStat = CustomerModel::select('cus_state', DB::Raw('ifnull(count(*),0) as count'))
            ->where($map)
            ->where(function ($q) use ($request) {
                // $cus_type && $q->whereIn('cus_type', $cus_type);
                $request->belong_uid && $q->where('belong_uid', $request->belong_uid);
                $request->room_type && $q->where('room_type', $request->room_type);
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
            })
            ->groupBy('cus_state')->get()->toArray();

        $dict = new DictServices;  // 根据ID 获取字典信息
        $cusState = $dict->getByKey([0, $this->company_id], 'cus_state');

        $total = 0;
        foreach ($cusState as $kt => &$vt) {
            $vt['count'] = 0;
            $vt['cus_state'] = $vt['value'];
            foreach ($customerStat as $k => $v) {
                if ($v['cus_state'] == $vt['value']) {
                    $vt['cus_state'] = $vt['value'];
                    $vt['count'] = $v['count'];
                    $total += $cusState[$kt]['count'];
                    break;
                } else {
                    $vt['count'] = 0;
                    $vt['cus_state'] = $vt['value'];
                }
            }
        }
        $cus_total[sizeof($cusState)]['cus_state'] = '客户总计';
        $cus_total[sizeof($cusState)]['count'] = $total;
        $cusState = array_merge($cusState, $cus_total);
        // }
        // return response()->json(DB::getQueryLog());
        $data = $this->handleBackData($result);
        foreach ($data['result'] as $k => &$v) {
            $v['demand_area'] = $v['customer_extra']['demand_area'];
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
     *          required={"channel_id","cus_type","cus_name"},
     *       @OA\Property(
     *          property="cus_type",
     *          type="int",
     *          description="客户类型 1:公司 2 个人"
     *       ),
     *       @OA\Property(
     *          property="cus_name",
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
     *              "channel_id":1,"cus_type":1,"cus_name":"公司客户","contact_name":"","contact_phone":""
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
            // 'cus_type' => 'required|in:1,2,3',
            'cus_name' => 'required|String|max:64',
            'channel_id' => 'required|numeric|gt:0',
            'customer_contact' => 'array',
            'customer_extra' => 'array',
            'customer_room' => 'array'
        ]);
        $DA = $request->toArray();

        // $checkRepeat = CustomerModel::where(['company_id' =>$this->company_id],['cus_name' => $request->cus_name])->exists();
        // if ($checkRepeat) {
        //     return $this->error('客户名称重复');
        // }

        try {
            DB::transaction(function () use ($DA) {
                $customer = $this->formatCustomer($DA); //格式化客户数据
                $user = auth('api')->user();
                // DB::enableQueryLog();
                $res = CustomerModel::Create($customer);
                //写入联系人
                $parent_id = $res->id;
                $cusExtra = $this->formatCusExtra($DA['customer_extra']);
                $cusExtra['cus_id'] = $parent_id;
                $cusExtra['c_uid'] = $this->uid;
                $customerExtra = CustomerExtraModel::Create($cusExtra);
                // 写入联系人 支持多联系人写入
                $contacts = $DA['customer_contact'];
                if ($contacts) {
                    $user['parent_type'] = $this->parent_type;
                    $contacts = formatContact($contacts, $parent_id, $user);
                    $contact = new contactModel;
                    $contact->addAll($contacts);
                }
                // 房间
                if (!empty($DA['customer_room']) && $DA['customer_room']) {
                    $roomList = $this->formatRoom($DA['customer_room'], $res->id, $DA['room_type']);
                    $rooms = new CustomerRoomModel;
                    $rooms->addAll($roomList);
                }
                // 工商信息
                $businessInfo = $DA['business_info'];
                if ($businessInfo) {
                    $businessInfo['name'] = $DA['cus_name'];
                    $info = new CustomerInfoService;
                    $business = $info->save($businessInfo, 1);   // 1 新增
                    if ($business) {
                        $businessData['business_info_id'] = $business->id;
                        $updateCus = CustomerModel::whereId($parent_id)->update($businessData);
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
                $cusLog['content'] = '创建客户【' . $res->cus_name . '】';
                $cusLog['cus_id'] = $parent_id;
                $cusLog = $this->saveLog($cusLog);
            });

            return $this->success('客户保存成功！');
        } catch (Exception $e) {
            return $this->success('客户保存失败！');
        }
        /** 调用skyeye */
        //
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
     *          required={"id","cus_type","cus_name"},
     *       @OA\Property(property="id",type="int",description="客户id"),
     *       @OA\Property(property="cus_type",type="int",description="客户类型 1:公司 2 个人"),
     *       @OA\Property(property="cus_name",type="String",description="客户名称")
     *     ),
     *       example={
     *              "id":1,"cus_type":1,"cus_name":"公司客户"
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
            // 'cus_type' => 'required|in:1,2',
            'cus_name' => 'required|String|max:64',
            'customer_contact' => 'array',
            'customer_extra' => 'array',
            'customer_room' => 'array'
        ]);
        $DA = $request->toArray();
        $map['company_id'] = $this->company_id;
        $map['cus_name'] = $request->cus_name;
        $checkRepeat = CustomerModel::where($map)->where('id', '!=', $DA['id'])->exists();
        if ($checkRepeat) {
            return $this->error('客户名称重复');
        }
        try {
            DB::transaction(function () use ($DA) {
                $customer = $this->formatCustomer($DA, 2);
                DB::enableQueryLog();
                $res = CustomerModel::whereId($DA['id'])->update($customer);

                $cusExtra = $this->formatCusExtra($DA['customer_extra']);
                $cusExtra['cus_id'] = $DA['id'];
                $cusExtra['u_uid'] = $this->uid;
                $customerExtra = CustomerExtraModel::where('cus_id', $DA['id'])->update($cusExtra);
                // 写入联系人 支持多联系人写入
                $user = auth('api')->user();
                $contacts = $DA['customer_contact'];
                if ($contacts) {
                    $user['parent_type'] = $this->parent_type;
                    $res = ContactModel::where('parent_id', $DA['id'])->delete();
                    $contacts = formatContact($contacts, $DA['id'], $user, 2);
                    $contact = new ContactModel;
                    $contact->addAll($contacts);
                }
                // 房间
                if ($DA['customer_room'] && !empty($DA['customer_room'])) {
                    $res = CustomerRoomModel::where('cus_id', $DA['id'])->delete();  // 删除
                    $roomList = $this->formatRoom($DA['customer_room'], $DA['id'], $DA['room_type']);
                    $rooms = new CustomerRoomModel;
                    $rooms->addAll($roomList);
                }

                // 更新工商信息
                $businessInfo = $DA['business_info'];
                if ($businessInfo) {
                    $businessInfo['business_info_id'] = $DA['business_info_id'];
                    $businessInfo['name'] = $DA['cus_name'];
                    $info = new CustomerInfoService;
                    $business = $info->save($businessInfo, 2);
                    // if ($business) {
                    //     $businessData['business_info_id'] = $business->id;
                    //     $updateCus = CustomerModel::whereId($DA['id'])->update($businessData);
                    // }
                }
                $cusLog['content'] = '编辑客户【' . $DA['cus_name'] . '】';
                $cusLog['cus_id'] = $DA['id'];
                $cusLog = $this->saveLog($cusLog);
            });
            return $this->success('客户更新成功。');
        } catch (Exception $e) {
            return $this->error('客户更新失败');
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
        $data = CustomerModel::with('customer_contact')
            ->with('customerExtra')
            ->with('customerRoom')
            ->find($request->id)->toArray();

        $info = new CustomerInfoService;
        $business_info  = $info->getById($data['business_info_id']);
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
     *          property="cus_name",
     *          type="Strig",
     *          description="公司名字，全名"
     *       ),
     *       @OA\Property(
     *          property="cus_id",
     *          type="Strig",
     *          description="客户ID"
     *       )
     *     ),
     *       example={
     *              "cus_name":"北京玄墨信息科技有限公司","cus_id":"1"
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
        if (!$request->cus_name) {
            return $this->error('客户名称必须传！');
        }
        $user = auth('api')->user();
        $skyeyeService = new CustomerInfoService;
        $data = $skyeyeService->getCompanyInfo($request->cus_name, $user->toArray());
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
     *          description="公司名字，全名"
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
        $info = new CustomerInfoService;
        $res = $info->edit($DA);

        if ($res) {
            return $this->success('公司工商信息编辑成功。');
        }
        return $this->error('公司工商信息编辑失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/business/customer/no/edit",
     *     tags={"客户"},
     *     summary="编辑客户编号前缀",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required = {"no_prefix"},
     *       @OA\Property(
     *          property="no_prefix",
     *          type="String",
     *          description="公司编号前缀"
     *       )
     *     ),
     *       example={
     *              "no_prefix":"CUS"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function cusNoEdit(Request $request)
    {
        $validatedData = $request->validate([
            'no_prefix' => 'required|String|min:1|max:4',
        ]);
        $DA['no_prefix'] = strtoupper($request->no_prefix);
        $DA['company_id'] = $this->company_id;
        $customerNo = new CustomerService;
        $res = $customerNo->saveCusNo($DA);
        $data['no_prefix'] = $request->no_prefix;
        if ($res) {
            return $this->success($data);
        }
        return $this->error('客户编号前缀更新失败！');
    }



    /**
     * 插入编辑客户日志
     * @Author   leezhua
     * @DateTime 2020-06-06
     * @param    [type]     $DA [description]
     * @return   [type]         [description]
     */
    private function saveLog($DA)
    {
        $user = $this->user;
        $cusLog['content'] = $DA['content'];
        $cusLog['customer_id'] = $DA['cus_id'];
        $cusLog['c_uid'] = $user->id;
        $cusLog["c_username"] = $user->realname;
        $cusLog["company_id"] = $user->company_id;
        $this->customerService->createCustomerLog($cusLog);
    }

    private function formatCustomer($DA, $type = 1)
    {
        $user = $this->user;
        if ($type == 1) {
            $customer = new CustomerService;
            $BA['cus_no'] = $customer->getCustomerNo($user['company_id']);
            $BA['c_uid'] = $user->id;
            $BA['c_user'] = $user->username;
            $BA['cus_state'] = $DA['cus_state'];
            $BA['company_id'] = $this->company_id;
            $BA['cus_type'] = 1;
        } else {
            $BA['u_uid'] = $this->uid;
        }

        $BA['cus_name'] = $DA['cus_name'];
        $BA['room_type'] = isset($DA['room_type']) ? $DA['room_type'] : 1;
        $BA['proj_id'] = isset($DA['proj_id']) ? $DA['proj_id'] : 0;
        $BA['proj_name'] = isset($DA['proj_name']) ? $DA['proj_name'] : "";
        $BA['cus_industry'] = isset($DA['cus_industry']) ? $DA['cus_industry'] : "";
        $BA['cus_level'] = isset($DA['cus_level']) ? $DA['cus_level'] : "";
        $BA['cus_nature'] = isset($DA['cus_nature']) ? $DA['cus_nature'] : "";
        $BA['cus_worker_num'] = isset($DA['cus_worker_num']) ? $DA['cus_worker_num'] : 0;
        $BA['cus_visit_date'] = isset($DA['cus_visit_date']) ? $DA['cus_visit_date'] : "";
        $BA['cus_addr'] = isset($DA['cus_addr']) ? $DA['cus_addr'] : "";
        $BA['belong_uid'] = isset($DA['belong_uid']) ? $DA['belong_uid'] : 0;
        $BA['belong_person'] = isset($DA['belong_person']) ? $DA['belong_person'] : "";
        $BA['channel_id'] = isset($DA['channel_id']) ? $DA['channel_id'] : 0;
        $BA['channel_name'] = isset($DA['channel_name']) ? $DA['channel_name'] : "";
        $BA['channel_contact'] = isset($DA['channel_contact']) ? $DA['channel_contact'] : "";
        $BA['channel_contact_phone'] = isset($DA['channel_contact_phone']) ? $DA['channel_contact_phone'] : "";
        $BA['cus_rate'] = isset($DA['cus_rate']) ? $DA['cus_rate'] : "";
        $BA['deal_rate'] = isset($DA['deal_rate']) ? $DA['deal_rate'] : 0;
        $BA['cus_tags'] = isset($DA['cus_tags']) ? $DA['cus_tags'] : "";
        $BA['remark'] = isset($DA['remark']) ? $DA['remark'] : "";

        return $BA;
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

    private function formatRoom(array $DA, $cusId, $roomType)
    {
        foreach ($DA as $k => $v) {
            $BA[$k]['created_at']   = nowTime();
            $BA[$k]['updated_at']   = nowTime();
            $BA[$k]['cus_id']       = $cusId;
            $BA[$k]['proj_id']      = $v['proj_id'];
            $BA[$k]['proj_name']    = $v['proj_name'];
            $BA[$k]['build_id']     = $v['build_id'];
            $BA[$k]['build_no']     = $v['build_no'];
            $BA[$k]['floor_id']     = $v['floor_id'];
            $BA[$k]['floor_no']     = $v['floor_no'];
            $BA[$k]['room_id']      = $v['room_id'];
            $BA[$k]['room_no']      = $v['room_no'];
            $BA[$k]['room_area']    = $v['room_area'];
            $BA[$k]['room_type']    = isset($v['room_type']) ? $v['room_type'] : $roomType;
            $BA[$k]['station_no']   = isset($v['room_area']) ? $v['room_area'] : "";
        }
        return $BA;
    }
}
