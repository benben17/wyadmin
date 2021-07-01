<?php
namespace App\Api\Controllers\Business;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use Illuminate\Support\Facades\DB;

use App\Api\Models\Customer\Customer;
use App\Api\Models\Customer\CustomerRemind;
use App\Api\Services\CustomerService;

/**
 * 项目管理
*/
class CustomerRemindController extends BaseController
{
	public function __construct()
	{
		$this->uid  = auth()->payload()->get('sub');
		if(!$this->uid){
	        return $this->error('用户信息错误');
	    }
	    $this->company_id = getCompanyId($this->uid);
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
	public function list(Request $request){
          $DA = $request->toArray();
          $now = date('Y-m-d H:i:s');

          // DB::enableQueryLog();
		$data = CustomerRemind::select(DB::Raw("group_concat(concat_ws('-',cus_id,cus_name,
               DATE_FORMAT(cus_remind_date,'%H:%i'),cus_remind_content,remind_user)) as remind_info ,
               DATE_FORMAT(cus_remind_date,'%Y-%m-%d') as remind_date"))
          ->where(function ($q) use($DA){
               if(isset($DA['start_time'])){
                    $q->where('cus_remind_date','>=',$DA['start_time']);
               }
               if(isset($DA['end_time'])){
                    $q->where('cus_remind_date','<=',$DA['end_time']);
               }
               isset($DA['c_uid']) && $q->where('c_uid',$DA['c_uid']);
               isset($DA['c_uid']) || $q->where('c_uid',$this->uid);
          })->whereHas('customer' ,function ($q) use($request){
               $request->proj_ids && $q->whereIn('proj_id',$request->proj_ids);
          })
          ->groupBy("remind_date")->get()->toArray();

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
     *          required={"cus_id","cus_remind_date","cus_name"},
     *       @OA\Property(
     *          property="cus_id",
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
     *              "cus_id": "","cus_name":""
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
    */
	public function store(Request $request){
		$validatedData = $request->validate([
			'cus_id' => 'required|numeric|gt:0',
               'cus_name' => 'required|String|min:1',
			'cus_remind_date' => 'required|date',
	    ]);
	    $checkCustomer = Customer::whereId($request->cus_id)->exists();
	    if(!$checkCustomer){
	    	return $this->error('客户不存在');
	    }
         $BA = $request->toArray();
	    DB::enableQueryLog();
	    $user = auth('api')->user();
         $res = $this->remindService->saveRemind($BA,$user);
	    // return response()->json(DB::getQueryLog());
	    if($res){
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
     *          required={"id","cus_id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="id"
     *       ),
     *       @OA\Property(
     *          property="cus_id",
     *          type="int",
     *          description="客户ID"
     *       )
     *     ),
     *       example={
     *              "id": "","cus_id":""
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
    */
	public function edit(Request $request){
		$validatedData = $request->validate([
          	'id' => 'required|numeric|gt:0',
          	'cus_id' => 'required|numeric|gt:0',
               'cus_remind_date' => 'required|date',

          ]);
          $checkCustomer = Customer::whereId($request->cus_id)->exists();
          if(!$checkCustomer){
              return $this->error('客户不存在');
          }

          $BA = $request->toArray();
          DB::enableQueryLog();
          $user = auth('api')->user();
          $res = $this->remindService->saveRemind($BA,$user);
          if($res){
              return $this->success('客户跟进提醒编辑成功');
          }
	    return $this->error('客户跟进提醒编辑失败');
	}
}