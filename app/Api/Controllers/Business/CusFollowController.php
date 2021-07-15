<?php

namespace App\Api\Controllers\Business;

use JWTAuth;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

use App\Api\Models\Tenant\Follow;
use App\Api\Services\CustomerService;
use App\Api\Services\Tenant\TenantService;

/**
 *
 */
class CusFollowController extends BaseController
{
  public function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->user = auth('api')->user();
    $this->parent_type = 5;
    $this->tenant = new TenantService;
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
   *          property="id",
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
  public function list(Request $request)
  {
    $pagesize = $request->input('pagesize');
    if (!$pagesize || $pagesize < 1) {
      $pagesize = config('per_size');
    }
    if ($pagesize == '-1') {
      $pagesize = config('export_rows');
    }
    // $map['company_id'] = $this->company_id;
    $map = array();
    if ($request->id && $request->id > 0) {
      $map['id'] = $request->id;
    }
    if ($request->follow_type) {
      $map['follow_type'] = $request->follow_type;
    }
    // 排序字段
    if ($request->input('orderBy')) {
      $orderBy = $request->input('orderBy');
    } else {
      $orderBy = 'follow_time';
    }
    // 排序方式desc 倒叙 asc 正序
    if ($request->input('order')) {
      $order = $request->input('order');
    } else {
      $order = 'desc';
    }

    DB::enableQueryLog();
    $result = Follow::where($map)
      ->where(function ($q) use ($request) {
        $request->start_time && $q->where('follow_time', '>=', $request->start_time);
        $request->end_time && $q->where('follow_time', '<=', $request->end_time);
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
      })
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());
    $data = $this->handleBackData($result);
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
   *          required={"tenant_id","follow_type","state","follow_time","follow_record","contact_id","contact_user"},
   *       @OA\Property(
   *          property="tenant_id",
   *          type="int",
   *          description="客户ID"
   *       ),
   *       @OA\Property(
   *          property="follow_type",
   *          type="int",
   *          description="跟进类型"
   *       ),
   *       @OA\Property(
   *          property="state",
   *          type="String",
   *          description="客户状态"
   *       ),
   *       @OA\Property(
   *          property="follow_time",
   *          type="date",
   *          description="跟进时间"
   *       ),
   *       @OA\Property(
   *          property="follow_record",
   *          type="String",
   *          description="跟进记录"
   *       ),
   *       @OA\Property(
   *          property="contact_id",
   *          type="int",
   *          description="跟进客户联系人ID"
   *       ),
   *       @OA\Property(
   *          property="contact_user",
   *          type="int",
   *          description="跟进客户联系人"
   *       )
   *
   *     ),
   *       example={
   *              "id": "","follow_type": "",
   *              "state":"","visit_time":"",
   *              "follow_record","contact_id":"",
   *              "contact_user":""
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
      // 'id' => 'required|numeric|gt:0',
      'follow_type' => 'required|numeric',
      'state' => 'required|String',
      'follow_time' => 'required|date',
      'follow_record' => 'required|String|min:1',
      // 'contact_id' =>'required|numeric|gt:0',
      'contact_user' => 'required|String',
      'proj_id'      => 'required',
    ]);

    $DA = $request->toArray();
    $user = auth('api')->user();
    $follow = new CustomerService;
    $res = $follow->saveFollow($DA, $user);
    if ($res) {

      return $this->success('跟进记录保存成功。');
    }
    return $this->error('跟进记录保存失败');
  }
  /**
   * @OA\Post(
   *     path="/api/business/customer/follow/edit",
   *     tags={"客户"},
   *     summary="跟进记录编辑，state 不允许编辑",
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
  public function update(Request $request)
  {
    return "disable";
    $validatedData = $request->validate([
      'id' => 'required|numeric|gt:0',
    ]);
    $user = auth('api')->user();
    $data = $request->toArray();
    unset($data['state']);
    $data['u_uid'] = $user->uid;
    $res = Follow::whereId($request->id)->update($data);
    if ($res) {
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
  public function show(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required|numeric|gt:0',
    ]);

    $data = Follow::find($request->id);
    return $this->success($data);
  }
}
