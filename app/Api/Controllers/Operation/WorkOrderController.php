<?php

namespace App\Api\Controllers\Operation;

use JWTAuth;
use Exception;
use App\Enums\AppEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;
use App\Api\Controllers\BaseController;
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
      'status' => 'array',
    ]);

    $map = array();
    if ($request->tenant_id) {
      $map['tenant_id'] = $request->tenant_id;
    }

    DB::enableQueryLog();
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
    $pageSubQuery = clone $subQuery;
    $data = $this->pageData($pageSubQuery, $request);

    $data['stat'] = $this->workService->listStat($subQuery);
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
    $order = $this->workService->workModel()->find($DA['id']);
    if ($order->status >= 3) {
      return $this->error('工单状态不允许取消！');
    }

    $res = $this->workService->cancelWorkorder($request->id, $this->user);
    if (!$res) {
      return $this->error('工单取消失败！');
    }
    return $this->success('工单取消成功。');
  }


  /**
   * @OA\Post(
   *     path="/api/operation/workorder/del",
   *     tags={"保修工单"},
   *     summary="保修工单删除",
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
    $DA = $request->toArray();
    $order = $this->workService->workModel()->find($DA['id']);
    if ($order->status != AppEnum::workorderOpen) {
      return $this->error('工单状态不允许删除！');
    }
    $res = $order->delete();

    if (!$res) {
      return $this->error('工单删除失败！');
    }
    return $this->success('工单删除成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/workorder/dispatch",
   *     tags={"工单"},
   *     summary="报修/隐患 工单 派单",
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
    $workOrder = $this->workService->workModel()->find($DA['id']);
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
    $this->workService->saveOrderLog($DA['id'], 2, $this->user);
    return $this->success('派单成功。');
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
   *     path="/api/operation/workorder/close",
   *     tags={"工单"},
   *     summary="报修 工单关闭，并提交评价",
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
    $workOrder = $this->workService->workModel()->find($DA['id']);
    if ($workOrder->charge_amount > 0) {
      $bankId = getBankIdByFeeType(AppEnum::maintenanceFeeType, $workOrder['proj_id']);
      if ($bankId == 0) {
        return $this->error('工单处理失败！未找到【工程费】收款银行账户，请联系管理员处理！');
      }
      $DA['bank_id'] = $bankId;
    }
    try {
      $res = $this->workService->closeWork($DA, $this->user);
      return $res ? $this->success('工单关闭成功。') : $this->error('工单关闭失败！');
    } catch (Exception $e) {
      return $this->error('工单关闭失败！' . $e->getMessage());
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
