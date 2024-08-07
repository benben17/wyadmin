<?php

namespace App\Api\Controllers\Business;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Models\Tenant\Follow;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Support\Facades\DB;

use App\Api\Controllers\BaseController;
use App\Api\Services\Business\CustomerService;
use App\Api\Models\Tenant\Remind as RemindModel;

/**
 * @OA\Tag(
 *     name="客户跟进提醒",
 *     description="客户跟进提醒管理"
 * )
 */
class CustomerRemindController extends BaseController
{
     private $remindService;
     public function __construct()
     {
          parent::__construct();
          $this->remindService = new CustomerService;
     }

     /**
      * @OA\Post(
      *     path="/api/business/customer/remind/list",
      *     tags={"客户"},
      *     summary="获取跟进提醒",
      *    @OA\RequestBody(
      *       @OA\MediaType(
      *           mediaType="application/json",
      *       @OA\Schema(
      *          schema="UserModel",
      *          required={},
      *       @OA\Property(
      *          property="start_time",
      *          type="date",
      *          description="开始时间"
      *       ),
      *       @OA\Property(
      *          property="end_time",
      *          type="date",
      *          description="结束时间"
      *       ),
      *       @OA\Property(
      *          property="c_uid",
      *          type="int",
      *          description="跟进人"
      *       )
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
     public function list(Request $request)
     {
          $DA = $request->toArray();
          // DB::enableQueryLog();
          $data = RemindModel::select(DB::Raw("group_concat(concat_ws('-',tenant_id,tenant_name,
               DATE_FORMAT(remind_date,'%H:%i'),remind_content,remind_user)) as remind_info ,
               DATE_FORMAT(remind_date,'%Y-%m-%d') as remind_date"))
               ->where(function ($q) use ($request) {
                    if (isset($request->start_time)) {
                         $q->where('remind_date', '>=', $request->start_time);
                    } else {
                         $q->where('remind_date', '>=', nowYmd());
                    }
                    if (isset($request->end_time)) {
                         $q->where('remind_date', '<=', $request->end_time);
                    }
                    return $this->applyUserPermission($q, $request->depart_id, $this->user);
               })->whereHas('customer', function ($q) use ($request) {
                    $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
               })
               ->groupBy("remind_date")->get()->toArray();

          return $this->success($data);
     }

     /**
      * @OA\Post(
      *     path="/api/business/customer/remind/wxlist",
      *     tags={"客户"},
      *     summary="获取跟进提醒一周内",
      *    @OA\RequestBody(
      *       @OA\MediaType(
      *           mediaType="application/json",
      *       @OA\Schema(
      *          schema="UserModel",
      *          required={"proj_id"},
      *       @OA\Property(
      *          property="start_time",
      *          type="date",
      *          description="开始时间"
      *       ),
      *       @OA\Property(
      *          property="end_time",
      *          type="date",
      *          description="结束时间"
      *       ),
      *       @OA\Property(
      *          property="proj_id",
      *          type="int",
      *          description="项目id"
      *       )
      *     ),
      *       example={"proj_id":1,"start_date":"","end_date":"","tenant_name":""}
      *       )
      *     ),
      *     @OA\Response(
      *         response=200,
      *         description=""
      *     )
      * )
      */
     public function wxList(Request $request)
     {
          // $validatedData = $request->validate([
          //      'proj_id' => 'required|numeric|gt:0',
          // ]);
          $DA = $request->toArray();
          if (!isset($DA['start_time'])) {
               $DA['start_time'] =  nowYmd();
          }
          if (!isset($DA['end_time'])) {

               $DA['end_time'] = getYmdPlusDays(nowYmd(), 7);
          }

          DB::enableQueryLog();
          $data = RemindModel::where('c_uid', $this->uid)
               ->where(function ($q) use ($DA) {
                    if (isset($DA['start_time'])) {
                         $q->where('remind_date', '>=', $DA['start_time']);
                    }
                    if (isset($DA['end_time'])) {
                         $q->where('remind_date', '<=', $DA['end_time']);
                    }
                    if (isset($DA['tenant_name'])) {
                         $q->where('tenant_name', $DA['tenant_name']);
                    }
               })
               ->with('customer')
               ->whereHas('customer', function ($q) use ($request) {
                    $request->proj_id && $q->where('proj_id', $request->proj_id);
               })
               ->get()->toArray();
          // return response()->json(DB::getQueryLog());
          foreach ($data as $k => &$v) {
               $v['follow'] = Follow::where('tenant_id', $v['tenant_id'])->first();
          }
          return $this->success($data);
     }
     /**
      * @OA\Post(
      *     path="/api/business/customer/remind/add",
      *     tags={"客户"},
      *     summary="添加客户跟进提醒",
      *    @OA\RequestBody(
      *       @OA\MediaType(
      *           mediaType="application/json",
      *       @OA\Schema(
      *          schema="UserModel",
      *          required={"tenant_id","cus_remind_date","cus_name"},
      *       @OA\Property(
      *          property="tenant_id",
      *          type="int",
      *          description="客户ID"
      *       ),
      *       @OA\Property(
      *          property="客户名称",
      *          type="String",
      *          description="结束时间"
      *       ),
      *       @OA\Property(
      *          property="提醒时间",
      *          type="date",
      *          description="提醒时间"
      *       )
      *
      *     ),
      *       example={
      *              "tenant_id": "","cus_name":""
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
               'tenant_id' => 'required|numeric|gt:0',
               'tenant_name' => 'required|String|min:1',
               'remind_date' => 'required|date',
          ]);
          $checkCustomer = Tenant::whereId($request->tenant_id)->exists();
          if (!$checkCustomer) {
               return $this->error('客户不存在');
          }
          $BA = $request->toArray();
          DB::enableQueryLog();
          $user = auth('api')->user();
          $res = $this->remindService->saveRemind($BA['tenant_id'], $BA['remind_date'], $user);
          // return response()->json(DB::getQueryLog());
          if ($res) {
               return $this->success('客户跟进提醒保存成功。');
          }
          return $this->error('客户跟进提醒保存失败！');
     }

     /**
      * @OA\Post(
      *     path="/api/business/customer/remind/edit",
      *     tags={"客户"},
      *     summary="编辑客户跟进提醒",
      *    @OA\RequestBody(
      *       @OA\MediaType(
      *           mediaType="application/json",
      *       @OA\Schema(
      *          schema="UserModel",
      *          required={"id","tenant_id"},
      *       @OA\Property(
      *          property="id",
      *          type="int",
      *          description="id"
      *       ),
      *       @OA\Property(
      *          property="tenant_id",
      *          type="int",
      *          description="客户ID"
      *       )
      *     ),
      *       example={
      *              "id": "","tenant_id":""
      *           }
      *       )
      *     ),
      *     @OA\Response(
      *         response=200,
      *         description=""
      *     )
      * )
      */
     public function edit(Request $request)
     {
          $validatedData = $request->validate([
               'id' => 'required|numeric|gt:0',
               'tenant_id' => 'required|numeric|gt:0',
               'remind_date' => 'required|date',

          ]);
          $checkCustomer = Tenant::whereId($request->tenant_id)->exists();
          if (!$checkCustomer) {
               return $this->error('客户不存在');
          }

          $BA = $request->toArray();
          DB::enableQueryLog();
          $user = auth('api')->user();
          $res = $this->remindService->saveRemind($BA['tenant_id'], $BA['remind_date'], $user);
          if ($res) {
               return $this->success('客户跟进提醒编辑成功');
          }
          return $this->error('客户跟进提醒编辑失败');
     }
}
