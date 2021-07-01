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
  public function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->workService = new WorkOrderService;
    $this->user = auth('api')->user();
  }


  /**
   * @OA\Post(
   *     path="/api/operation/workorder/list",
   *     tags={"工单"},
   *     summary="工单列表",
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
      'status' => 'required|array',
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


    $data = $this->workService->workModel()
      ->where($map)
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
        $q->whereIn('status', $request->status);
        if ($request->start_date && $request->end_date) {
          $q->whereBetween('open_time', [$request->start_date, $request->end_date]);
        }
      })
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();

    $data = $this->handleBackData($data);
    // 统计
    if (count($request->status) != 4) {
      $stat = $this->workService->workModel()
        ->selectRaw('count(*) count,sum(charge_amount) amount ,sum(time_used) time_used,avg(feedback_rate) rate')
        ->where($map)
        ->where(function ($q) use ($request) {
          $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
          $request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
          $q->whereIn('status', $request->status);
          if ($request->start_date && $request->end_date) {
            $q->whereBetween('open_time', [$request->start_date, $request->end_date]);
          }
        })->first();
      $data['stat'] = array(
        ['label' => '总工单', 'value' => $stat['count']],
        ['label' => '平均评分', 'value' => $stat['rate']],
        ['label' => '总用时', 'value' => $stat['time_used'] . '小时'],
        ['label' => '总金额', 'value' => $stat['amount'] . '元'],
      );
    } else {
      $stat = $this->workService->workModel()
        ->selectRaw('sum(case status when 1 then 1 else 0 end) pending,
                  sum(case status when 2 then 1 else 0 end) received,
                  sum(case status when 3 then 1 else 0 end) finished,
                  sum(case status when 99 then 1 else 0 end) cancel,
                  count(*) total_count')
        ->where($map)
        ->where(function ($q) use ($request) {
          $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
          $request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
          $q->whereIn('status', $request->status);
          if ($request->start_date && $request->end_date) {
            $q->whereBetween('open_time', [$request->start_date, $request->end_date]);
          }
        })->first();
      $data['stat'] = array(
        ['label' => '待处理', 'value' => $stat['pending']],
        ['label' => '已接单', 'value' => $stat['received']],
        ['label' => '已处理', 'value' => $stat['finished']],
        ['label' => '已取消', 'value' => $stat['cancel']],
        ['label' => '总计', 'value' => $stat['total_count']]
      );
    }
    return $this->success($data);
  }
  /**
   * @OA\Post(
   *     path="/api/operation/workorder/add",
   *     tags={"工单"},
   *     summary="提交工单",
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
      'repair_content' => 'required|String'
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
   *     summary="工单编辑",
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
    $res = $this->workService->saveWorkOrder($DA, $this->user);
    if (!$res) {
      return $this->error('工单更新失败！');
    }
    return $this->success('工单更新成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/workorder/cancel",
   *     tags={"工单"},
   *     summary="工单取消",
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
   *     path="/api/operation/workorder/order/",
   *     tags={"工单"},
   *     summary="工单派单接单",
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
   *     summary="工单处理",
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
    $res = $this->workService->processWorkorder($DA, $this->user);
    if (!$res) {
      return $this->error('工单处理失败！');
    }
    return $this->success('工单处理成功。');
  }
  /**
   * @OA\Post(
   *     path="/api/operation/workorder/close",
   *     tags={"工单"},
   *     summary="工单关闭，并提交评价",
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
