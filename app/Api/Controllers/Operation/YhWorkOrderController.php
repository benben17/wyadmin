<?php

namespace App\Api\Controllers\Operation;

use JWTAuth;
use App\Enums\AppEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Controllers\BaseController;
use App\Api\Services\Operation\YhWorkOrderService;
use Exception;

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
    $pagesize = $request->input('pagesize');
    if (!$pagesize || $pagesize < 1) {
      $pagesize = config('per_size');
    }
    if ($pagesize == '-1') {
      $pagesize = config('export_rows');
    }
    $map = array();
    if ($request->tenant_id) {
      $map['tenant_id'] = $request->tenant_id;
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
        $request->maintain_person  && $q->where('maintain_person', 'like', '%' . $request->maintain_person . '%');
      });
    $data = $subQuery->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();

    // return DB::getQueryLog();

    $data = $this->handleBackData($data);
    // 统计
    $stat = $subQuery->selectRaw(
      'sum(case status when 1 then 1 else 0 end)  as "1",
          sum(case status when 2 then 1 else 0 end) as "2",
          sum(case status when 3 then 1 else 0 end) as "3",
          sum(case status when 4 then 1 else 0 end) as "4",
          sum(case status when 99 then 1 else 0 end) as "99",
          count(*) total_count'
    )
      ->first();

    $statusMap =  $this->workService->yhWorkModel()->statusMap();
    $totalCount = 0;
    foreach ($statusMap as $k => $v) {
      if ($k == '90') {
        continue;
      }
      $value = $stat[$k] ?? 0;
      $data['stat'][] = array('label' => $v, 'value' => $value, 'status' => $k);
      $totalCount += $value;
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
   *          required={"proj_id","open_time","order_source","repair_content"},
   *       @OA\Property(property="open_time",type="int",description="开单时间"),
   *       @OA\Property(property="proj_id",type="int",description="项目ID"),
   *       @OA\Property(property="repair_goods",type="String",description="报修物品"),
   *       @OA\Property(property="tenant_id",type="int",description="租户ID"),
   *       @OA\Property(property="repair_content",type="String",description="保修内容")
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
      'order_source'  => 'required|String',
      'repair_content' => 'required|String',
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
   *          required={"id","proj_id","open_time","order_source","repair_content"},
   *       @OA\Property(property="open_time",type="int",description="开单时间"),
   *       @OA\Property(property="proj_id",type="int",description="项目ID"),
   *       @OA\Property(property="repair_goods",type="String",description="报修物品"),
   *       @OA\Property(property="tenant_id",type="int",description="租户ID"),
   *       @OA\Property(property="repair_content",type="String",description="保修内容")
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
      'order_source'  => 'required|String',
      'repair_content' => 'required|String'
    ]);
    $DA = $request->toArray();
    if (!$DA['tenant_id']) {
      $DA['tenant_name'] = '公区';
    }
    $res = $this->workService->saveYhWorkOrder($DA, $this->user);
    return $res ? $this->success('隐患工单成功。') : $this->error('隐患工单保存失败！');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/workorder/cancel",
   *     tags={"隐患工单"},
   *     summary="隐患工单-工单取消",
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
  public function cancel(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required|numeric|gt:0',

    ]);
    $DA = $request->toArray();
    $res = $this->workService->cancelWorkorder($request->id, $this->user);
    if (!$res) {
      return $this->error('工单取消失败！');
    }
    return $this->success('工单取消成功。');
  }




  /**
   * @OA\Post(
   *     path="/api/operation/workorder/dispatch",
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
   *        @OA\Property(property="order_person",type="String",description="接单人"),
   *        @OA\Property(property="order_uid",type="int",description="接单人uid"),
   *     ),
   *       example={"id":0,"dispatch_user":"","dispatch_time":"","order_time":"","order_uid":"","order_person":""}
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
    $validatedData = $request->validate([
      'id' => 'required|numeric|gt:0',
      'dispatch_time' => 'required|date',
      'dispatch_user' => 'required|string',
      'order_uid' => 'required|numeric|gt:0',
      'order_person' => 'required',
    ]);
    $DA = $request->toArray();
    $workOrder = $this->workService->yhWorkModel()->find($DA['id']);
    if (compareTime($workOrder->dispatch_time, $request->order_time)) {
      return $this->error('派单时间不允许小于下单时间！');
    }
    $workOrder->dispatch_time = $request->dispatch_time;
    $workOrder->dispatch_user = $request->dispatch_user;
    $workOrder->order_uid     = $request->order_uid;
    $workOrder->order_person  = $request->order_person;
    $res = $workOrder->save();
    if (!$res) {
      return $this->error('派单失败！');
    }
    $this->workService->saveYhOrderLog($DA['id'], 2, $this->user);
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
    $validatedData = $request->validate([
      'id' => 'required|numeric|gt:0',
      'order_time' => 'required|date',
      'order_uid' => 'required|numeric|gt:0',
    ]);
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
    ]);


    $DA = $request->toArray();
    $workOrder = $this->workService->yhWorkModel()->find($DA['id']);
    if (compareTime($workOrder->order_time, $request->return_time)) {
      return $this->error('返单时间不允许小于接单时间！');
    }

    $res = $this->workService->processWorkOrder($DA, $this->user);
    if (!$res) {
      return $this->error('工单处理失败！');
    }
    return $this->success('工单处理成功。');
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
   *     path="/api/operation/yhworkorder/close",
   *     tags={"隐患工单"},
   *     summary="隐患 工单关闭，并提交评价",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="工单id"),
   *       @OA\Property(property="feedback_rate",type="int",description="评分")
   *     ),
   *       example={"feedback_rate":1,"feedback":"","id":1}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function close(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required|numeric',
      "feedback_rate" => 'required|numeric',
      // 'is_notice' =>'required|numeric',
    ]);
    $DA = $request->toArray();

    $res = $this->workService->closeWork($DA, $this->user);

    return $res ? $this->success('工单关闭成功。') : $this->error('工单关闭失败！');
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
    $validator = \Validator::make($request->all(), [
      'id' => 'required|numeric|gt:0',
    ]);

    $data = $this->workService->yhWorkModel()
      ->with('orderLogs')
      ->find($request->id)->toArray();
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
    ]);
    try {
      DB::transaction(function () use ($request) {
        $data['status'] = '90';
        $res = $this->workService->yhWorkModel()->where('id', $request->id)->update($data);
        $this->workService->saveYhOrderLog($request->id, '90', $this->user, "转隐患仓库");
      }, 2);
      return  $this->success("转隐患仓库成功");
    } catch (Exception $e) {
      return $this->error("转隐患仓库失败");
    }
  }
}
