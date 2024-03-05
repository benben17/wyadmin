<?php

namespace App\Api\Controllers\Operation;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Services\Operation\WorkOrderService;

/**
 *   工单
 */
class WorkOrderController extends BaseController
{
  private $workService;
  public function __construct()
  {
    parent::__construct();
    $this->workService = new WorkOrderService;
  }


  /**
   * @OA\Post(
   *     path="/api/operation/workorder/list",
   *     tags={"工单"},
   *     summary="报修/隐患工单列表",
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
      'status' => 'required|array',
      'work_type' => 'required|gt:0',
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
    $map['work_type'] = $request->work_type;

    $subQuery = $this->workService->workModel()
      ->where($map)
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
        $request->status && $q->whereIn('status', $request->status);
        if ($request->start_date && $request->end_date) {
          $q->whereBetween('open_time', [$request->start_date, $request->end_date]);
        }
        $request->engineering_type && $q->where('engineering_type', 'like', '%' . $request->engineering_type . '%');
        if ($request->has('charge_amount')) {
          if ($request->charge_amount === 0) {
            $q->where('charge_amount', 0);
          } elseif ($request->charge_amount == 1) {
            $q->where('charge_amount', '>', 0);
          }
        }
        $request->maintain_person  && $q->where('maintain_person', 'like', '%' . $request->maintain_person . '%');
      });
    $data = $subQuery->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();

    $data = $this->handleBackData($data);
    // 统计

    if (in_array(4, $request->status) && count($request->status) == 1) {
      $stat = $subQuery->selectRaw(
        'count(*) count,
        sum(charge_amount) amount,
        sum(time_used) time_used,
        avg(feedback_rate) rate'
      )->first();
      $data['stat'] = [
        ['label' => '总工单', 'value' => $stat['count']],
        ['label' => '平均评分', 'value' => numFormat($stat['rate'])],
        ['label' => '总用时', 'value' => numFormat($stat['time_used']) . '小时'],
        ['label' => '总金额', 'value' => $stat['amount'] . '元'],
      ];
    } else {
      $stat = $subQuery->selectRaw(
        'sum(case status when 1 then 1 else 0 end) pending,
            sum(case status when 2 then 1 else 0 end) received,
            sum(case status when 3 then 1 else 0 end) finished,
            sum(case status when 4 then 1 else 0 end) closed,
            sum(case status when 99 then 1 else 0 end) cancel,
            count(*) total_count'
      )
        ->first();

      $data['stat'] = [
        ['label' => '待处理', 'value' => empty($stat['pending']) ? 0 : $stat['pending']],
        ['label' => '已接单', 'value' => empty($stat['received']) ? 0 : $stat['received']],
        ['label' => '已处理', 'value' => empty($stat['finished']) ? 0 : $stat['finished']],
        ['label' => '已关闭', 'value' => empty($stat['closed']) ? 0 : $stat['closed']],
        ['label' => '已取消', 'value' => empty($stat['cancel']) ? 0 : $stat['cancel']],
        ['label' => '总计', 'value' => $stat['total_count']],
      ];
    }
    return $this->success($data);
  }
  /**
   * @OA\Post(
   *     path="/api/operation/workorder/add",
   *     tags={"工单"},
   *     summary="报修/隐患 工单保存",
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
      'work_type'     => 'required|in:1,2'
    ]);
    $DA = $request->toArray();
    if (!isset($DA['tenant_id'])) {
      $DA['tenant_name'] = '公区';
    }
    $res = $this->workService->saveWorkOrder($DA, $this->user);
    if (!$res) {
      return $this->error('提交工单失败！');
    }
    return $this->success('提交工单成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/workorder/edit",
   *     tags={"工单"},
   *     summary="报修/隐患工单编辑",
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
      'work_type'     => 'required|in:1,2',
      'open_time'     => 'required|date',
      'order_source'  => 'required|String',
      'repair_content' => 'required|String'
    ]);
    $DA = $request->toArray();
    if (!$DA['tenant_id']) {
      $DA['tenant_name'] = '公区';
    }
    $res = $this->workService->saveWorkOrder($DA, $this->user);
    if (!$res) {
      return $this->error('工单更新失败！');
    }
    return $this->success('工单更新成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/workorder/cancel",
   *     tags={"保修工单"},
   *     summary="保修工单取消",
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
   *     path="/api/operation/workorder/order",
   *     tags={"工单"},
   *     summary="报修/隐患 工单 派单/接单",
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
    $workOrder = $this->workService->workModel()->find($DA['id']);
    if (compareTime($workOrder->open_time, $request->order_time)) {
      return $this->error('接单时间不允许小于下单时间！');
    }
    $res = $this->workService->orderWork($DA, $this->user);
    if (!$res) {
      return $this->error('接单失败！');
    }
    return $this->success('接单成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/workorder/process",
   *     tags={"工单"},
   *     summary="报修/隐患 工单处理",
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
    $workOrder = $this->workService->workModel()->find($DA['id']);
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
   *     path="/api/operation/workorder/audit",
   *     tags={"工单"},
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
   *       example={"id":1,"audit_stats":"1 审核通过 其他审核不通过","remark":"","audit_time":"审核时间 年-月-日 分:时:秒"}
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
      'work_type' => 'required|gt:0',
    ]);
    $DA = $request->toArray();

    $res = $this->workService->auditWorkOrder($DA, $this->user);
    if (!$res) {
      return $this->error('工单审核失败！');
    } else {
      return $this->success('工单审核成功。');
    }
  }

  /**
   * @OA\Post(
   *     path="/api/operation/workorder/close",
   *     tags={"工单"},
   *     summary="报修/隐患 工单关闭，并提交评价",
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
    if (!$res) {
      return $this->error('工单关闭失败！');
    } else {
      return $this->success('工单关闭成功。');
    }
  }

  /**
   * @OA\Post(
   *     path="/api/operation/workorder/rate",
   *     tags={"工单"},
   *     summary="工单评价",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"feedback_rate"},
   *       @OA\Property(property="feedback_rate",type="int",description="评分"),
   *       @OA\Property(property="feedback",type="String",description="评价内容")
   *     ),
   *       example={"feedback_rate":1,"feedback":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function rate(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required|numeric',
      "feedback_rate" => 'required|numeric',
      // 'is_notice' =>'required|numeric',
    ]);
    $DA = $request->toArray();

    $res = $this->workService->closeWork($DA, $this->user);
    if (!$res) {
      return $this->error('工单评分失败！');
    } else {
      return $this->success('工单评分成功。');
    }
  }
  /**
   * @OA\Post(
   *     path="/api/operation/workorder/show",
   *     tags={"工单"},
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

    $data = $this->workService->workModel()
      ->with('orderLogs')
      ->find($request->id)->toArray();
    return $this->success($data);
  }
}
