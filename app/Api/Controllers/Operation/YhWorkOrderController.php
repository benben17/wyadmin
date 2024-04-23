<?php

namespace App\Api\Controllers\Operation;

use JWTAuth;
use Exception;
use App\Enums\AppEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;
use App\Api\Controllers\BaseController;
use Illuminate\Support\Facades\Validator;
use App\Api\Services\Common\BseRemarkService;
use App\Api\Services\Operation\YhWorkOrderService;

/**
 *   工单
 */
class YhWorkOrderController extends BaseController
{
  private $workService;
  public function __construct()
  {
    parent::__construct();
    $this->workService = new YhWorkOrderService;
  }


  /**
   * @OA\Post(
   *     path="/api/operation/yhworkorder/list",
   *     tags={"工单"},
   *     summary="隐患工单列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"order_type"},
   *       @OA\Property(property="status",type="int",
   *         description="1 待处理 2已接单 3 处理完成 4 关闭"),
   *       @OA\Property(property="pagesize",type="int",description="行数"),
   *       @OA\Property(property="proj_id",type="int",description="项目ID"),
   *       @OA\Property(property="tenant_id",type="int",description="客户ID")
   *     ),
   *       example={"status":1,"proj_id":"","tenant_id":"","start_date":"","end_date":""}
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
    $validatedData = $request->validate([
      'proj_ids' => 'required|array',
      // 'status' => 'array'
    ]);

    $map = array();
    if ($request->tenant_id) {
      $map['tenant_id'] = $request->tenant_id;
    }

    DB::enableQueryLog();
    $subQuery = $this->workService->yhWorkModel()
      ->where($map)
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
        $request->status && $q->whereIn('status', $request->status);
        if ($request->start_date && $request->end_date) {
          $q->whereBetween('open_time', [$request->start_date, $request->end_date]);
        }
        $request->hazard_type && $q->where('hazard_type', $request->hazard_type);
        isset($request->tenant_id) && $q->where('tenant_id', $request->tenant_id);
        $request->hazard_level && $q->where('hazard_level', $request->hazard_level);
        $request->check_type && $q->where('check_type', $request->check_type);
        $request->process_person  && $q->where('process_person', 'like', '%' . $request->process_person . '%');
        $request->order_no  && $q->where('order_no', 'like', '%' . $request->order_no . '%');
      });
    $pageQuery = clone $subQuery;
    $data = $this->pageData($pageQuery->with('tenant:id,name'), $request);
    // 统计
    $stat = $subQuery->selectRaw('status, COUNT(*) as count')
      ->groupBy('status')
      ->get()
      ->pluck('count', 'status')
      ->toArray();

    $statusMap =  $this->workService->yhWorkModel()->statusMap();
    $totalCount = 0;
    foreach ($statusMap as $k => $label) {
      if ($k == '90') { // 转隐患库不统计
        continue;
      }
      $value = $stat[$k] ?? 0;
      $data['stat'][] = [
        'label' => $label,
        'value' => $value,
        'status' => $k
      ];
      $totalCount += $value;
    }
    foreach ($data['result'] as $k => &$v) {
      $v['tenant_name'] = $v['tenant']['name'] ?? '公区';
    }
    $data['stat'][] = array('label' => "总计", 'value' => $totalCount, 'status' => '');
    return $this->success($data);
  }
  /**
   * @OA\Post(
   *     path="/api/operation/yhworkorder/add",
   *     tags={"隐患工单"},
   *     summary="隐患工单-保存",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"proj_id","open_time","order_source"},
   *       @OA\Property(property="open_time",type="int",description="开单时间"),
   *       @OA\Property(property="proj_id",type="int",description="项目ID"),
   *       @OA\Property(property="tenant_id",type="int",description="租户ID"),
   *       @OA\Property(property="hazard_issues",type="String",description="隐患内容")
   *     ),
   *       example={}
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
      'proj_id'       => 'required|numeric',
      'open_time'     => 'required|date',
      // 'check_type'  => 'required|String',
      'hazard_issues' => 'required|String',
    ]);
    $DA = $request->toArray();
    if (!isset($DA['tenant_id'])) {
      $DA['tenant_name'] = '公区';
    }
    $res = $this->workService->saveYhWorkOrder($DA, $this->user);
    return $res ? $this->success('隐患工单成功。') : $this->error('隐患工单保存失败！');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/yhworkorder/edit",
   *     tags={"隐患工单"},
   *     summary="隐患工单编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","proj_id","open_time","order_source","hazard_issues"},
   *       @OA\Property(property="open_time",type="int",description="开单时间"),
   *       @OA\Property(property="proj_id",type="int",description="项目ID"),
   *       @OA\Property(property="tenant_id",type="int",description="租户ID"),
   *       @OA\Property(property="hazard_issues",type="String",description="隐患内容")
   *     ),
   *       example={}
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
      'id'            => 'required|numeric|gt:0',
      'proj_id'       => 'required|numeric',
      // 'tenant_id'        => 'required|numeric',
      'open_time'     => 'required|date',
      'check_type'  => 'required|String',
      'hazard_issues' => 'required|String'
    ]);
    $DA = $request->toArray();
    if (!isset($DA['tenant_id'])) {
      $DA['tenant_name'] = '公区';
    }
    $res = $this->workService->saveYhWorkOrder($DA, $this->user);
    return $res ? $this->success('隐患工单成功。') : $this->error('隐患工单保存失败！');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/yhworkorder/del",
   *     tags={"隐患工单"},
   *     summary="隐患工单-工单取消/删除",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="ID")
   *
   *     ),
   *       example={"id":"1"}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function del(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required|numeric|gt:0',
    ]);
    if ($this->user['is_manager'] || $this->user['is_admin']) {
      $res = $this->workService->delWorkorder($request->id);
      return $res ? $this->success('工单删除成功。') : $this->error('工单删除失败！');
    } else {
      return $this->error('当前用户无权限删除！需要用户为部门管理员。');
    }
  }




  /**
   * @OA\Post(
   *     path="/api/operation/yhworkorder/dispatch",
   *     tags={"隐患工单"},
   *     summary="隐患工单-派单",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","order_time"},
   *        @OA\Property(property="id",type="int",description="ID"),
   *        @OA\Property(property="dispatch_time",type="date",description="接单时间"),
   *        @OA\Property(property="dispatch_user",type="String",description="派单人"),
   *        @OA\Property(property="process_user",type="String",description="接单人"),
   *        @OA\Property(property="process_user_id",type="int",description="接单人uid"),
   *     ),
   *       example={"id":0,"dispatch_user":"","dispatch_time":"","process_user_id":"","process_user":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */

  public function orderDispatch(Request $request)
  {
    $request->validate([
      'id' => 'required|numeric|gt:0',
      // 'dispatch_time' => 'required|date',
      // 'dispatch_user' => 'required|string',
      'pick_user_id' => 'required|numeric|gt:0',
      'pick_user' => 'required|string',
    ]);
    $DA = $request->toArray();
    $yhWorkOrder = $this->workService->yhWorkModel()->find($DA['id']);
    if (compareTime($yhWorkOrder->open_time, nowTime())) {
      return $this->error('派单时间不允许小于下单时间！');
    }
    if ($yhWorkOrder->status != 1) {
      return $this->error('不是可派单状态！');
    }
    $res = $this->workService->orderDispatch($DA, $this->user);
    if (!$res) {
      return $this->error('派单失败！');
    }
    return $this->success('派单成功。');
  }


  /**
   * @OA\Post(
   *     path="/api/operation/yhworkorder/order",
   *     tags={"隐患工单"},
   *     summary="隐患 工单 派单/接单",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","order_time"},
   *        @OA\Property(property="id",type="int",description="ID"),
   *        @OA\Property(property="order_time",type="date",description="接单时间"),
   *        @OA\Property(property="order_uid",type="String",description="接单人"),
   *        @OA\Property(property="order_person",type="String",description="接单人")
   *     ),
   *       example={"id":0,"order_time":"","order_uid":"","order_person":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */

  public function order(Request $request)
  {
    $msg =  [
      'id.required' => 'ID 字段是必需的。',
      'id.numeric' => 'ID 必须是数字。',
      'id.gt' => 'ID 必须大于 0。',
      'order_time.required' => '订单时间字段是必需的。',
      'order_time.date' => '订单时间必须是一个有效的日期。',
      'order_uid.required' => '订单用户ID字段是必需的。',
      'order_uid.numeric' => '订单用户ID必须是数字。',
      'order_uid.gt' => '订单用户ID必须大于 0。',
    ];
    $request->validate([
      'id' => 'required|numeric|gt:0',
      'order_time' => 'required|date',
      'order_uid' => 'required|numeric|gt:0',
    ], $msg);

    $DA = $request->toArray();
    $workOrder = $this->workService->yhWorkModel()->find($DA['id']);
    if (compareTime($workOrder->open_time, $request->order_time)) {
      return $this->error('接单时间不允许小于下单时间！');
    }
    $res = $this->workService->orderWork($DA, $this->user);

    return $res ? $this->success('接单成功。') : $this->error('接单失败！');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/yhworkorder/process",
   *     tags={"隐患工单"},
   *     summary="隐患工单-处理",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *        @OA\Property(property="id",type="int",description="ID"),
   *        @OA\Property(property="return_time",type="date",description="返单时间"),
   *        @OA\Property(property="process_result",type="String",description="处理结果"),
   *        @OA\Property(property="time_used",type="String",description="处理用时")
   *     ),
   *       example={"id":0,"return_time":"","process_result":"","time_used":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function process(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required|numeric|gt:0',
      'process_time' => 'required|date'
    ]);

    $DA = $request->toArray();
    if ($request->process_status == '2') {
      $this->workService->yhWorkModel()->where('id', $request->id)->update(['status' => '90']);
      $this->workService->saveYhOrderLog($request->id, '90', $this->user, "转隐患仓库");
      return $this->success('隐患转隐患库处理成功。');
    }

    $workOrder = $this->workService->yhWorkModel()->find($DA['id']);
    if (compareTime($workOrder->dispatch_time, $request->process_time)) {
      return $this->error('处理时间不允许小于接单时间！');
    }

    $res = $this->workService->processWorkOrder($DA, $this->user);
    return  $res ? $this->success('工单处理成功。') : $this->error('工单处理失败！');
  }


  /**
   * @OA\Post(
   *     path="/api/operation/yhworkorder/audit",
   *     tags={"隐患工单"},
   *     summary="隐患工单 审核",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="工单id"),
   *       @OA\Property(property="work_type",type="int",description="工单类型 2 隐患工单")
   *     ),
   *       example={"id":1,"audit_status":"1 审核通过 其他审核不通过","remark":"","audit_time":"审核时间 年-月-日 分:时:秒"}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function audit(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required|numeric',
      // "feedback_rate" => 'required|numeric',
    ]);
    $DA = $request->toArray();
    $res = $this->workService->auditWorkOrder($DA, $this->user);
    return $res ? $this->success('工单审核成功。') : $this->error('工单审核失败！');
  }



  /**
   * @OA\Post(
   *     path="/api/operation/yhworkorder/show",
   *     tags={"隐患工单"},
   *     summary="工单查看",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *       @OA\Property(property="id",type="int",description="ID")
   *     ),
   *       example={}
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
    Validator::make($request->all(), [
      'id' => 'required|numeric|gt:0',
    ]);

    $data = $this->workService->yhWorkModel()
      ->with('orderLogs')
      ->with('remarks')
      ->find($request->id);
    return $this->success($data);
  }


  /**
   * @OA\Post(
   *     path="/api/operation/yhworkorder/toWarehouse",
   *     tags={"隐患工单"},
   *     summary="隐患工单-转隐患仓库",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *       @OA\Property(property="id",type="int",description="ID")
   *     ),
   *       example={}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function toWarehouse(Request $request)
  {
    $request->validate([
      'id' => 'required|numeric|gt:0',
    ], ['id.required' => '工单ID必须']);
    try {
      DB::transaction(function () use ($request) {
        $this->workService->yhWorkModel()->where('id', $request->id)->update(['status' => '90']);
        $this->workService->saveYhOrderLog($request->id, '90', $this->user, "转隐患仓库");
      }, 2);
      return  $this->success("转隐患仓库成功。");
    } catch (Exception $e) {
      return $this->error("转隐患仓库失败！");
    }
  }

  /**
   * @OA\Post(
   *     path="/api/operation/yhworkorder/addRemark",
   *     tags={"隐患工单"},
   *     summary="隐患工单-隐患仓库添加备注",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *       @OA\Property(property="id",type="int",description="ID")
   *     ),
   *       example={}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function addRemark(Request $request)
  {
    $request->validate([
      'id' => 'required|numeric|gt:0',
      'remark' => 'required|String'
    ]);
    $remarkService = new BseRemarkService;
    $parentType = AppEnum::YhWorkOrder; // 隐患工单parent_type

    $data = [
      'parent_id' => $request->id,
      'parent_type' => $parentType,
      'remark' => $request->remark,
      'add_date' => $request->add_date ?? nowTime(),
    ];
    try {
      DB::transaction(function () use ($data, $request, $remarkService) {
        $remarkService->save($data, $this->user);
        // 更新隐患工单状态
        $yhWorkOrder = $this->workService->yhWorkModel()->find($request->id);
        if ($request->process_status) {
          $yhWorkOrder->process_status = $request->process_status;
          $yhWorkOrder->process_time = nowTime();
        }

        $yhWorkOrder->save();
      });
      return $this->success("隐患跟踪添加成功");
    } catch (Exception $e) {
      return $this->error("隐患添加失败！");
    }
  }
}
