<?php

namespace App\Api\Controllers\Operation;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Services\Operation\EquipmentService;
use Svg\Tag\Rect;

/**
 *   设备
 */
class EquipmentController extends BaseController
{
  private $equipment;
  public function __construct()
  {
    parent::__construct();
    $this->equipment = new EquipmentService;
  }


  /**
   * @OA\Post(
   *     path="/api/operation/equipment/list",
   *     tags={"设备"},
   *     summary="设备列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"order_type"},
   *       @OA\Property(property="major",type="int",
   *         description="专业"),
   *       @OA\Property(property="pagesize",type="int",description="行数"),
   *       @OA\Property(property="device_name",type="int",description="设备名称"),
   *       @OA\Property(property="proj_ids",type="int",description="项目IDs")
   *     ),
   *       example={"major":1,"proj_ids":"[]","device_name":""}
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
    // $validatedData = $request->validate([
    //     'order_type' => 'required|numeric',
    // ]);
    $pagesize = $request->input('pagesize');
    if (!$pagesize || $pagesize < 1) {
      $pagesize = config('per_size');
    }
    if ($pagesize == '-1') {
      $pagesize = config('export_rows');
    }
    if (!$request->year) {
      $request->year = date('Y');
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
    $data = $this->equipment->equipmentModel()
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->device_name && $q->where('device_name', 'like', '%' . $request->device_name . '%');
        $request->major && $q->where('major', 'like', '%' . $request->major . '%');
        $request->system_name && $q->where('system_name', 'like', '%' . $request->system_name . '%');
      })
      // ->where('year', $request->year)
      ->withCount(['maintainPlan' => function ($q) use ($request) {
        $q->whereYear('plan_date', $request->year);
      }])

      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());
    $data = $this->handleBackData($data);

    foreach ($data['result'] as $k => &$v) {
      $planData = $this->equipment->MaintainPlanModel()->selectRaw('COUNT(*) as total_count, SUM(status = 1) as maintain_count')
        ->where('equipment_id', $v['id'])
        ->whereYear('plan_date', $request->year)
        ->first();
      $v['plan_times'] = $planData['total_count'];
      $v['maintain_times'] = $planData['maintain_count'];
      $v['remaining_times'] = $planData['total_count'] - $planData['maintain_count'];
    }

    return $this->success($data);
  }
  /**
   * @OA\Post(
   *     path="/api/operation/equipment/add",
   *     tags={"设备"},
   *     summary="设备新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"proj_id","system_name","position","major"},
   *       @OA\Property(property="system_name",type="int",description="系统名称"),
   *       @OA\Property(property="proj_id",type="int",description="项目ID"),
   *       @OA\Property(property="position",type="String",description="位置"),
   *       @OA\Property(property="major",type="String",description="专业"),
   *       @OA\Property(property="device_name",type="String",description="设备名称"),
   *       @OA\Property(property="model",type="String",description="设备型号"),
   *       @OA\Property(property="quantity",type="String",description="数量"),
   *       @OA\Property(property="unit",type="String",description="单位"),
   *       @OA\Property(property="maintain_cycle",type="String",description="维护周期"),
   *       @OA\Property(property="maintain_content",type="String",description="维护内容"),
   *       @OA\Property(property="maintain_times",type="String",description="维护次数"),
   *     ),
   *       example={"system_name":"","position":"","major":"","device_name":"","model":"",
   *       "quantity":"","unit":"","maintain_cycle":"",
   *       "maintain_content":"","maintain_times":""}
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
      'proj_id'         => 'required|numeric|gt:0',
      'system_name'     => 'required',
      'maintain_period'     => 'required|numeric|gt:0|lt:6',
      'device_name'     => 'required|String',
      'quantity'        => 'required|numeric',
      'position'        => 'required|String',
      // 'maintain_times'  => 'required|numeric',
      'generate_plan' => 'required',
    ]);
    $DA = $request->toArray();

    $equipmentId = $this->equipment->saveEquipment($DA, $this->user);
    if (!$equipmentId) {

      return $this->error('设备保存失败！');
    }
    if ($DA['generate_plan']) {
      $this->equipment->saveBatchMaintainPlan($equipmentId, $DA['maintain_period'], $this->user, date('Y'));
    }
    return $this->success('设备保存成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/equipment/edit",
   *     tags={"设备"},
   *     summary="设备编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","proj_id","open_time","order_source","repair_content"},
   *       @OA\Property(property="system_name",type="int",description="系统名称"),
   *       @OA\Property(property="proj_id",type="int",description="项目ID"),
   *       @OA\Property(property="position",type="String",description="位置"),
   *       @OA\Property(property="major",type="String",description="专业"),
   *       @OA\Property(property="device_name",type="String",description="设备名称"),
   *       @OA\Property(property="model",type="String",description="设备型号"),
   *       @OA\Property(property="quantity",type="String",description="数量"),
   *       @OA\Property(property="unit",type="String",description="单位"),
   *       @OA\Property(property="maintain_cycle",type="String",description="维护周期"),
   *       @OA\Property(property="maintain_content",type="String",description="维护内容")
   *     ),
   *       example={"system_name":"","position":"","major":"","device_name":"","model":"",
   *       "quantity":"","unit":"","maintain_cycle":"",
   *       "maintain_content":""}
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
      'id'              => 'required|numeric',
      'system_name'     => 'required|String',
      'device_name'     => 'required|String',
      'quantity'        => 'required|numeric',
      'position'        => 'required|String',
      // 'maintain_times'  => 'required|numeric'
    ]);
    $DA = $request->toArray();

    $res = $this->equipment->saveEquipment($DA, $this->user);
    if (!$res) {
      return $this->error('设备更新失败！');
    }
    return $this->success('设备更新成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/equipment/show",
   *     tags={"设备"},
   *     summary="设备查看",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="设备ID")
   *
   *     ),
   *       example={"id":""}
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
    $DA = $request->toArray();
    $data = $this->equipment->equipmentModel()
      ->find($DA['id'])->toArray();
    if ($data) {
      $planData = $this->equipment->MaintainPlanModel()->selectRaw('COUNT(*) as total_count, SUM(status = 1) as maintain_count')
        ->where('equipment_id', $DA['id'])
        ->whereYear('plan_date', $request->year)
        ->first();
      $data['plan_times'] = $planData['total_count'];
      $data['maintain_times'] = $planData['maintain_count'];
      $data['remaining_times'] = $planData['total_count'] - $planData['maintain_count'];
    } else {
      $data['plan_times'] = 0;
      $data['maintain_times'] = 0;
      $data['remaining_times'] = 0;
    }
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/equipment/del",
   *     tags={"设备"},
   *     summary="设备删除",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"Ids"},
   *       @OA\Property(property="Ids",type="list",description="设备ID")
   *
   *     ),
   *       example={"Ids":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */

  public function delete(Request $request)
  {
    $validatedData = $request->validate([
      'Ids' => 'required|array',
    ]);
    $DA = $request->toArray();
    $res = $this->equipment->equipmentModel()->whereIn('id', $request->Ids)->delete();
    if ($res) {
      return $this->success($res);
    }
    return $this->error('删除失败！');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/equipment/maintain/list",
   *     tags={"设备"},
   *     summary="设备设施维护列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"equipment_id","equipment_type","position","major"},
   *       @OA\Property(property="proj_ids",type="String",description="设备ID"),
   *       @OA\Property(property="equipment_type",type="String",description="工程/秩序"),
   *       @OA\Property(property="start_date",type="date",description="日期"),
   *       @OA\Property(property="end_date",type="String",description="维护内容"),
   *       @OA\Property(property="maintain_type",type="String",description="维护类型1 全部2 部分"),
   *       @OA\Property(property="year",type="String",description="年份 默认当年"),
   *       @OA\Property(property="period",type="String",description="维护周期")
   *     ),
   *       example={"equipment_id":"","equipment_type":"","maintain_date":"",
   *       "maintain_content":"","maintain_type":"","maintain_person":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function maintainList(Request $request)
  {
    $pagesize = $request->input('pagesize');
    if (!$pagesize || $pagesize < 1) {
      $pagesize = config('per_size');
    }
    if ($pagesize == '-1') {
      $pagesize = config('export_rows');
    }
    if (!$request->year) {
      $request->year = date('Y');
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


    $data = $this->equipment->maintainModel()
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', str2Array($request->proj_ids));
        $request->device_name && $q->where('device_name', 'like', '%' . $request->tenant_name . '%');
        $request->major && $q->where('major', $request->major);
        $request->start_date && $q->where('maintain_date', '>=', $request->start_date);
        $request->end_date && $q->where('maintain_date', '<=', $request->end_date);
        $request->c_uid && $q->where('c_uid', $request->uid);
        $request->year && $q->whereYear('maintain_date', $request->year);
        // $request->maintain_period && $q->where('maintain_period', $request->maintain_period);
      })->orderBy($orderBy, $order)
      ->paginate($pagesize)
      ->toArray();
    $data = $this->handleBackData($data);
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/equipment/maintain/add",
   *     tags={"设备"},
   *     summary="设备设施维护",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"equipment_id","equipment_type","position","major"},
   *       @OA\Property(property="equipment_id",type="int",description="设备ID"),
   *       @OA\Property(property="equipment_type",type="String",description="工程/秩序"),
   *       @OA\Property(property="maintain_date",type="date",description="日期"),
   *       @OA\Property(property="maintain_period",type="String",description="维护周期"), 
   *       @OA\Property(property="maintain_content",type="String",description="维护内容"),
   *       @OA\Property(property="maintain_person",type="String",description="维护人"),
   *       @OA\Property(property="maintain_type",type="String",description="维护类型1 全部2 部分"),
   *       @OA\Property(property="major",type="String",description="专业"), 
   *       @OA\Property(property="position",type="String",description="位置"), 
   *       @OA\Property(property="maintain_quantity",type="int",description="数量"), 
   *       @OA\Property(property="pic",type="String",description="图片"), 
   *       @OA\Property(property="remark",type="String",description="备注"), 
   *     ),
   *       example={"equipment_id":"","equipment_type":"","maintain_date":"",
   *       "maintain_content":"","maintain_type":"","maintain_person":"","maintain_quantity":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function maintainStore(Request $request)
  {
    $request->validate([
      'equipment_id'    => 'required',
      // 'equipment_type'        => 'required|String', //类型工程秩序
      'maintain_date'   => 'required|date',
      'maintain_content'   => 'required|String',
      'maintain_person'   => 'required|String', // 可多选
      'plan_id' => 'required|gt:0',
      'maintain_quantity' => 'required',
    ]);
    $DA = $request->toArray();
    $maintainId = $this->equipment->saveEquipmentMaintain($DA, $this->user);
    $updatePlanRes = $this->equipment->updateMaintainPlan($maintainId);
    if (!$maintainId || !$updatePlanRes) {
      return $this->error('设备维护保存失败！');
    }

    return $this->success('设备维护保存成功。');
  }


  /**
   * @OA\Post(
   *     path="/api/operation/equipment/maintain/edit",
   *     tags={"设备"},
   *     summary="设备设施维护",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"equipment_id","equipment_type","position","major"},
   *       @OA\Property(property="equipment_id",type="int",description="设备ID"),
   *       @OA\Property(property="equipment_type",type="String",description="工程/秩序"),
   *       @OA\Property(property="maintain_date",type="date",description="日期"),
   *       @OA\Property(property="maintain_content",type="String",description="维护内容"),
   *       @OA\Property(property="maintain_person",type="String",description="维护人"),
   *       @OA\Property(property="maintain_type",type="String",description="维护类型1 全部2 部分")
   *     ),
   *       example={"equipment_id":"","equipment_type":"","maintain_date":"",
   *       "maintain_content":"","maintain_type":"","maintain_person":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function maintainUpdate(Request $request)
  {
    $validatedData = $request->validate([
      'id'            => 'required|numeric|gt:0',
      'equipment_id'    => 'required',
      // 'equipment_type'        => 'required|String', //类型工程秩序
      'maintain_date'   => 'required|date',
      'maintain_content'   => 'required|String',
      'maintain_person'   => 'required|String', // 可多选
    ]);
    $DA = $request->toArray();
    $res = $this->equipment->saveEquipmentMaintain($DA, $this->user);
    if (!$res) {
      return $this->error('设备维护更新失败！');
    }
    return $this->success('设备维护更新成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/equipment/maintain/show",
   *     tags={"设备"},
   *     summary="设备维护查看",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="维护ID")
   *
   *     ),
   *       example={"id":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function maintainShow(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required|numeric|gt:0',
    ]);
    $DA = $request->toArray();
    $data = $this->equipment->maintainModel()->find($DA['id'])->toArray();
    // $data['maintain_type_label'] = getDictName($data['maintain_type']);
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/equipment/maintain/del",
   *     tags={"设备"},
   *     summary="设备维护删除",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"Ids"},
   *       @OA\Property(property="Ids",type="int",description="设备维护IDs")
   *
   *     ),
   *       example={"Ids":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */

  public function maintainDelete(Request $request)
  {
    $validatedData = $request->validate([
      'Ids' => 'required|array',
    ]);
    $res = $this->equipment->maintainModel()->whereIn('id', $request->Ids)->delete();
    if ($res) {
      return $this->success("维护记录删除成功。");
    }
    return $this->error('删除失败！');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/equipment/plan/list",
   *     tags={"设备"},
   *     summary="维护计划列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"equipment_id"},
   *       @OA\Property(property="major",type="int",
   *         description="专业"),
   *       @OA\Property(property="pagesize",type="int",description="行数"),
   *       @OA\Property(property="device_name",type="int",description="设备名称"),
   *       @OA\Property(property="proj_ids",type="int",description="项目IDs"),
   *       @OA\Property(property="start_time",type="date",description="计划开始时间"),
   *        @OA\Property(property="end_time",type="date",description="计划结束时间"),
   *     ),
   *       example={"major":1,"proj_ids":"[]","equipment_id":"","start_time":"","end_time":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function planList(Request $request)
  {
    // $validatedData = $request->validate([
    //     'order_type' => 'required|numeric',
    // ]);
    $pagesize = $request->input('pagesize');
    if (!$pagesize || $pagesize < 1) {
      $pagesize = config('per_size');
    }
    if ($pagesize == '-1') {
      $pagesize = config('export_rows');
    }
    // if (!$request->year) {
    //   // $request->year = date('Y');
    // }
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
    $data = $this->equipment->MaintainPlanModel()
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->device_name && $q->where('device_name', 'like', '%' . $request->tenant_name . '%');
        $request->major && $q->where('major', 'like', '%' . $request->major . '%');
        $request->equipment_id && $q->where('equipment_id', $request->equipment_id);
        if ($request->start_time && $request->end_time) {
          $q->whereBetween('plan_date', [$request->start_time, $request->end_time]);
        }
        $request->year && $q->whereYear('plan_date', $request->year);
        $request->completed &&  $q->whereRaw('plan_quantity=maintain_quantity');
      })
      // ->where('year', $request->year)
      ->withCount(['maintain' => function ($q) use ($request) {
        if ($request->start_time && $request->end_time) {
          $q->whereBetween('maintain_date', [$request->start_time, $request->end_time]);
        }
        $request->year && $q->whereYear('maintain_date', $request->year);
      }])
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());
    $data = $this->handleBackData($data);
    foreach ($data['result'] as $k => &$v) {
      $v['completed'] = $v['status'] === 1 ? 1 : 0;
      $v['completed_label'] =   $v['status'] === 1 ? "是" : "否";
    }
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/equipment/plan/edit",
   *     tags={"设备"},
   *     summary="设备维护计划编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"Ids"},
   *       @OA\Property(property="id",type="int",description="id")
   *
   *     ),
   *       example={"id":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */

  public function planEdit(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required',
    ]);

    $plan = $this->equipment->MaintainPlanModel()->find($request->id);
    if ($plan->status == 1) {
      return $this->error("已维护完成不允许修改");
    }
    $res = $this->equipment->editMaintainPlan($request->toArray());
    if ($res) {
      return $this->success("维护计划更新成功。");
    }
    return $this->error('维护计划更新失败！');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/equipment/plan/del",
   *     tags={"设备"},
   *     summary="设备维护计划删除",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"Ids"},
   *       @OA\Property(property="Ids",type="int",description="设备维护IDs")
   *
   *     ),
   *       example={"Ids":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function planDelete(Request $request)
  {
    $validatedData = $request->validate([
      'Ids' => 'required|array',
    ]);

    $res = $this->equipment->MaintainPlanModel()->whereIn('id', $request->Ids)->delete();
    if ($res) {
      return $this->success("维护计划删除成功。");
    }
    return $this->error('维护计划删除失败！');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/equipment/plan/generate",
   *     tags={"设备"},
   *     summary="设备维护计划生成",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"equipment_ids","year"},
   *       @OA\Property(property="equipment_ids",type="list",description="设备IDs"),
   *       @OA\Property(property="year",type="int",description="计划年份"),
   *     ),
   *       example={"equipment_ids":"","year":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function planGenerate(Request $request)
  {
    $messages = [
      'equipment_ids.required' => '设备ID字段是必填的且必须是数组。',
      'year.required' => '年份字段是必填的。',
      'year.numeric' => '年份必须是数字。',
      'year.digits' => '年份必须是4位数。',
      'year.gte' => '年份必须大于或等于当前年份。',
    ];
    $request->validate([
      'equipment_ids' => 'required|array',
      'year' => 'required|numeric|digits:4|gte:' . date('Y'),
    ], $messages);

    $planNum = 0;

    foreach ($request->equipment_ids as $equipmentId) {
      $planCount = $this->equipment->MaintainPlanModel()->where('equipment_id', $equipmentId)->count();

      if ($planCount > 0) {
        continue; // Fixing the typo here
      }
      $equipment = $this->equipment->equipmentModel()->find($equipmentId);
      if ($equipment) {
        $period = $equipment['maintain_period'];
        $this->equipment->saveBatchMaintainPlan($equipmentId, $period, $this->user, $request->year);
        $planNum++;
      }
    }

    return $this->success("共计生成【" . $planNum . "】个设备计划");
  }

  /**
   * @OA\Post(
   *     path="/api/operation/equipment/plan/show",
   *     tags={"设备"},
   *     summary="设备维护计划查看",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"Ids"},
   *       @OA\Property(property="id",type="int",description="设备维护计划id")
   *
   *     ),
   *       example={"id":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function planShow(Request $request)
  {
    $request->validate([
      'id' => 'required',
    ]);
    $data = $this->equipment->MaintainPlanModel()
      ->whereId($request->id)
      ->withCount('maintain')
      // ->where('year', $request->year)
      ->with('maintain')->first();
    return $this->success($data);
  }
}
