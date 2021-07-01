<?php
namespace App\Api\Controllers\Business;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

use App\Api\Models\Customer\CustomerFollow;
use App\Api\Services\CustomerInfoService;
use App\Api\Services\CustomerService;
use App\Api\Models\Customer\Customer as CustomerModel;
use App\Api\Models\Customer\CustomerExtra as CustomerExtraModel;

/**
 *
 */
class CusFollowController extends BaseController
{
  public function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if(!$this->uid){
          return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->user = auth('api')->user();
    $this->parent_type = 2;
    $this->customerService = new CustomerService;
  }

  /**
   * @OA\Post(
   *     path="/api/business/customer/follow/list",
   *     tags={"客户"},
   *     summary="客户跟进列表",
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
   *          property="cus_id",
   *          type="int",
   *          description="客户ID，不传默认为所有的跟进"
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
  public function list(Request $request){
      $pagesize = $request->input('pagesize');
      if (!$pagesize || $pagesize < 1) {
          $pagesize = config('per_size');
      }
      if($pagesize == '-1'){
          $pagesize = config('export_rows');
      }
      // $map['company_id'] = $this->company_id;
      $map = array();
      if($request->cus_id && $request->cus_id>0){
          $map['cus_id'] = $request->cus_id;
      }
      if($request->cus_follow_type){
          $map['cus_follow_type'] = $request->cus_follow_type;
      }
      // 排序字段
      if($request->input('orderBy')){
          $orderBy = $request->input('orderBy');
      }else{$orderBy = 'cus_follow_time';}
      // 排序方式desc 倒叙 asc 正序
      if($request->input('order')){
          $order = $request->input('order');
      }else{
          $order = 'desc';
      }

      $cusIds = "";
      if ($request->cus_name) {
        $cus = CustomerModel::select(DB::Raw('group_concat(id) cus_id'))
        ->where('cus_name','like','%'.$request->cus_name.'%')->first();
        $cusIds = explode(',', $cus['cus_id']);
      }

      if ($request->proj_ids) {
        $cus =
        $cus = CustomerModel::select(DB::Raw('group_concat(id) cus_id'))
        ->whereIn('proj_id',$request->proj_ids)->first();
        $cusIds = explode(',', $cus['cus_id']);
      }
      DB::enableQueryLog();
      $result = CustomerFollow::where($map)
      ->with('customer:id,cus_name')
      ->where(function ($q) use($request,$cusIds){
        $request->start_time && $q->where('cus_follow_time','>=', $request->start_time);
        $request->end_time && $q->where('cus_follow_time','<=',$request->end_time);
        $cusIds && $q->whereIn('cus_id',$cusIds);
      })
      ->orderBy($orderBy,$order)
      ->paginate($pagesize)->toArray();
      // return response()->json(DB::getQueryLog());
      $data = $this->handleBackData($result);
      if ($data['result']) {
        $data['stat']  = $this->customerService->followStat($map,$cusIds);
      }
      foreach ($data['result'] as $k => &$v) {
        $v['cus_name'] = $v['customer']['cus_name'];
      }
      return $this->success($data);

  }
  /**
   * @OA\Post(
   *     path="/api/business/customer/follow/add",
   *     tags={"客户"},
   *     summary="跟进记录新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"cus_id","cus_follow_type","cus_state","cus_follow_time","cus_follow_record","cus_contact_id","cus_contact_user"},
   *       @OA\Property(
   *          property="cus_id",
   *          type="int",
   *          description="客户ID"
   *       ),
   *       @OA\Property(
   *          property="cus_follow_type",
   *          type="int",
   *          description="跟进类型"
   *       ),
   *       @OA\Property(
   *          property="cus_state",
   *          type="String",
   *          description="客户状态"
   *       ),
   *       @OA\Property(
   *          property="cus_follow_time",
   *          type="date",
   *          description="跟进时间"
   *       ),
   *       @OA\Property(
   *          property="cus_follow_record",
   *          type="String",
   *          description="跟进记录"
   *       ),
   *       @OA\Property(
   *          property="cus_contact_id",
   *          type="int",
   *          description="跟进客户联系人ID"
   *       ),
   *       @OA\Property(
   *          property="cus_contact_user",
   *          type="int",
   *          description="跟进客户联系人"
   *       )
   *
   *     ),
   *       example={
   *              "cus_id": "","cus_follow_type": "",
   *              "cus_state":"","cus_visit_time":"",
   *              "cus_follow_record","cus_contact_id":"",
   *              "cus_contact_user":""
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
          'cus_follow_type' => 'required|numeric',
          'cus_state' => 'required|String',
          'cus_follow_time' =>'required|date',
          'cus_follow_record' => 'required|String|min:1',
          // 'cus_contact_id' =>'required|numeric|gt:0',
          'cus_contact_user'=>'required|String',
      ]);

      $DA= $request->toArray();
      $user = auth('api')->user();
      $follow = new CustomerService;
      $res = $follow->saveFollow($DA,$user);
      if ($res) {

        return $this->success('跟进记录保存成功。');
      }
      return $this->error('跟进记录保存失败');
  }
  /**
   * @OA\Post(
   *     path="/api/business/customer/follow/edit",
   *     tags={"客户"},
   *     summary="跟进记录编辑，cus_state 不允许编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"followId"},
   *       @OA\Property(
   *          property="followId",
   *          type="int",
   *          description="跟进ID"
   *       )
   *     ),
   *       example={
   *              "followId": ""
   *           }
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
  */
  public function update(Request $request){
      return "disable";
      $validatedData = $request->validate([
              'id' => 'required|numeric|gt:0',
      ]);
      $user = auth('api')->user();
      $data= $request->toArray();
      unset($data['cus_state']);
      $data['u_uid'] = $user->uid;
      $res = CustomerFollow::whereId($request->id)->update($data);
      if($res){
          return $this->success('跟进记录保存成功。');
      }
      return $this->error('跟进记录保存失败');
  }

    /**
   * @OA\Post(
   *     path="/api/business/customer/follow/show",
   *     tags={"客户"},
   *     summary="跟进记录查看",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="跟进记录ID")
   *     ),
   *       example={"id": ""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
  */
  public function show(Request $request){
      $validatedData = $request->validate([
        'id' => 'required|numeric|gt:0',
      ]);

      $data = CustomerFollow::find($request->id);

      if($data){
        $follow = new CustomerService;
        $data['cus_name'] = $follow->getCusNameById($data['cus_id']);
        $data['follow_type'] = $follow->getFollowType($data['cus_follow_type']);
        return $this->success($data);
      }
      return $this->error('无数据');
  }
}