<?php

namespace App\Api\Controllers\Operation;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Services\Operation\EquipmentService;

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
    $data = $this->equipment->equipmentModel()
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->device_name && $q->where('device_name', 'like', '%' . $request->tenant_name . '%');
        $request->major && $q->where('major', 'like', '%' . $request->major . '%');
      })
      ->where('year', $request->year)
      ->withCount(['maintain' => function ($q) use ($request) {
        $q->whereYear('maintain_date', $request->year);
      }])
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    $data = $this->handleBackData($data);
    if ($data['result']) {
      foreach ($data['result'] as $k => &$v) {
        $v['remainte_times'] = $v['maintain_times'] - $v['maintain_count'];
      }
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
      'device_name'     => 'required|String',
      'quantity'        => 'required|numeric',
      'position'        => 'required|String',
      'maintain_times'  => 'required|numeric'
    ]);
    $DA = $request->toArray();

    $res = $this->equipment->saveEquipment($DA, $this->user);
    if (!$res) {
      return $this->error('设备保存失败！');
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
   *       @OA\Property(property="maintain_content",type="String",description="维护内容"),
   *       @OA\Property(property="maintain_times",type="String",description="维护次数")
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
  public function update(Request $request)
  {

    $validatedData = $request->validate([
      'id'              => 'required|numeric',
      'system_name'     => 'required|String',
      'device_name'     => 'required|String',
      'quantity'        => 'required|numeric',
      'position'        => 'required|String',
      'maintain_times'  => 'required|numeric'
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
      ->with('maintain')
      ->find($DA['id'])->toArray();
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
      ->whereYear('maintain_date', $request->year)
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', str2Array($request->proj_ids));
        $request->device_name && $q->where('device_name', 'like', '%' . $request->tenant_name . '%');
        $request->major && $q->where('major', $request->major);
        $request->start_date && $q->where('maintain_date', '>=', $request->start_date);
        $request->end_date && $q->where('maintain_date', '<=', $request->end_date);
        $request->c_uid && $q->where('c_uid', $request->uid);
        $request->maintain_period && $q->where('maintain_period', $request->maintain_period);
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
   *       @OA\Property(property="quantity",type="int",description="数量"), 
   *       @OA\Property(property="pic",type="String",description="图片"), 
   *       @OA\Property(property="remark",type="String",description="备注"), 
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
  public function maintainStore(Request $request)
  {
    $validatedData = $request->validate([
      'equipment_id'    => 'required',
      'equipment_type'        => 'required|String', //类型工程秩序
      'maintain_date'   => 'required|date',
      'maintain_content'   => 'required|String',
      'maintain_person'   => 'required|String', // 可多选
    ]);
    $DA = $request->toArray();

    $res = $this->equipment->saveEquipmentMaintain($DA, $this->user);
    if (!$res) {
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
      'equipment_type'        => 'required|String', //类型工程秩序
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
    $DA = $request->toArray();
    $res = $this->equipment->maintainModel()->whereIn('id', $request->Ids)->delete();
    if ($res) {
      return $this->success("维护记录删除成功。");
    }
    return $this->error('删除失败！');
  }
}
