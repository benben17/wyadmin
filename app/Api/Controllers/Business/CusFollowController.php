<?php

namespace App\Api\Controllers\Business;

use JWTAuth;
use App\Enums\AppEnum;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use App\Api\Services\CustomerService;
use App\Api\Services\Sys\UserServices;
use App\Api\Controllers\BaseController;
use App\Api\Services\Common\DictServices;

/**
 *
 */
class CusFollowController extends BaseController
{
  protected $parent_type;
  protected $customerService;
  public function __construct()
  {
    parent::__construct();
    $this->parent_type = AppEnum::Tenant;
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

    // $map['company_id'] = $this->company_id;
    $map = array();
    if ($request->tenant_id) {
      $map['tenant_id'] = $request->tenant_id;
    }
    if ($request->follow_type) {
      $map['follow_type'] = $request->follow_type;
    }
    // 排序字段

    $request->orderBy = 'follow_time';

    // 排序方式desc 倒叙 asc 正序


    DB::enableQueryLog();
    $subQuery = $this->customerService->followModel()->where($map)
      ->where(function ($q) use ($request) {
        $request->start_time && $q->where('follow_time', '>=', $request->start_time);
        $request->end_time && $q->where('follow_time', '<=', $request->end_time);
        $request->visit_times && $q->where('visit_times', '>=', $request->visit_times);
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->follow_username && $q->where('follow_username', 'like', $request->follow_username);
        return UserServices::filterByDepartId($q, $this->user, $request->depart_id);
      })
      ->whereHas('tenant', function ($q) use ($request) {
        $request->tenant_name && $q->where('name', 'like', "%" . $request->tenant_name . "%");
      });

    // return response()->json(DB::getQueryLog());
    $data = $this->pageData($subQuery, $request);

    $stat = $subQuery
      ->selectRaw('count(*) as count,follow_type ,count(distinct(tenant_id)) tenant_count')
      ->groupBy('follow_type')->get();

    $dictService = new DictServices;
    $dictKeys = $dictService->getDicts([0, $this->user['company_id']], 'follow_type');
    $followStat = array();
    foreach ($dictKeys as $k => &$v) {
      $followStat[$k] = [
        'label' => $v['dict_value'],
        'count' => 0,
        'tenant_count' => 0
      ];
      foreach ($stat as $k1 => $v1) {
        if ($v['id'] == $v1['follow_type']) {
          $followStat[$k]['count'] = $v1['count'];
          $followStat[$k]['tenant_count'] = $v1['tenant_count'];
          break;
        }
      }
    }
    $data['stat'] = $followStat;
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

    try {
      $this->customerService->saveFollow($DA, $this->user);
      return $this->success('跟进记录保存成功。');
    } catch (\Exception $e) {
      return $this->error('跟进记录保存失败' . $e->getMessage());
    }
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
    return 'not allowed';
    $validatedData = $request->validate([
      'id' => 'required|numeric|gt:0',
    ]);

    $data = $request->toArray();
    unset($data['state']);
    $data['u_uid'] = $this->uid;
    $res = $this->customerService->followModel()->whereId($request->id)->update($data);
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

    $data = $this->customerService->followModel()->find($request->id);
    return $this->success($data);
  }
}
