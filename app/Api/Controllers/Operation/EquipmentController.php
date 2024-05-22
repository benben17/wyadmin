<?php

namespace App\Api\Controllers\Operation;

use JWTAuth;
use Exception;
use App\Enums\AppEnum;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Controllers\BaseController;
use App\Api\Services\Common\AttachmentService;
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

    // if ($this->user == 'null' || !$this->user) {
    //   return $this->error('用户信息错误');
    // }
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
    if (!$request->year) {
      $request->year = date('Y');
    }

    DB::enableQueryLog();
    $subQuery = $this->equipment->equipmentModel()
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->device_name && $q->where('device_name', 'like', columnLike($request->device_name));
        $request->major && $q->where('major', 'like', '%' . $request->major . '%');
        $request->system_name && $q->where('system_name', 'like', columnLike($request->system_name));
        isset($request->is_valid) && $q->where('is_valid', $request->is_valid);
        $request->third_party && $q->where('third_party', $request->third_party);
        isset($request->tenant_id) && $q->where('tenant_id', $request->tenant_id);
      })
      // ->where('year', $request->year)
      ->withCount(['maintainPlan' => function ($q) use ($request) {
        $q->whereYear('plan_date', $request->year);
      }]);

    $data = $this->pageData($subQuery, $request);

    foreach ($data['result'] as $k => &$v) {
      $planData = $this->equipment->MaintainPlanModel()
        ->selectRaw('COUNT(*) as total_count, IFNULL(sum(status = 1),0) as maintain_count')
        ->where('equipment_id', $v['id'])
        ->whereYear('plan_date', $request->year)
        ->first();
      // $v['year'] = $request->year;
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
    // 是否生成维护计划
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
    $year = $request->year;
    if (!$year) {
      $year = date('Y');
    }
    $DA = $request->toArray();
    $data = $this->equipment->equipmentModel()
      ->find($DA['id'])->toArray();
    if ($data) {
      DB::enableQueryLog();
      $planData = $this->equipment->MaintainPlanModel()
        ->selectRaw('COUNT(*) as total_count, IFNULL(sum(status = 1),0) as maintain_count')
        ->where('equipment_id', $DA['id'])
        ->whereYear('plan_date', $year)
        ->first();
      $data['plan_times'] =  $planData->total_count ?? 0;
      $data['maintain_times'] = $planData->maintain_count ?? 0;
      $data['remaining_times'] = ($planData->total_count - $planData->maintain_count) ?? 0;
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
    try {
      DB::transaction(function () use ($DA) {
        $this->equipment->equipmentModel()->whereIn('id', $DA['Ids'])->delete();
        $this->equipment->MaintainPlanModel()->whereIn('equipment_id', $DA['Ids'])->delete();
        $this->equipment->maintainModel()->whereIn('equipment_id', $DA['Ids'])->delete();
      });
      return $this->success("设备删除成功。");
    } catch (Exception $e) {
      Log::error("设备删除失败！" . $e->getMessage());
      return $this->error('设备删除失败！');
    }
  }

  /**
   * @OA\Post(
   *     path="/api/operation/equipment/enable",
   *     tags={"设备"},
   *     summary="设备启用停用",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"Ids","is_valid"},
   *       @OA\Property(property="Ids",type="list",description="设备ID"),
   *       @OA\Property(property="is_valid",type="int",description="启用停用")
   *     ),
   *       example={"Ids":"","is_valid":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function enable(Request $request)
  {
    $request->validate([
      'Ids' => 'required|array',
      'is_valid' => 'required|numeric|in:0,1'
    ]);
    $this->equipment->equipmentModel()
      ->whereIn('id', $request->Ids)
      ->update(['is_valid' => $request->is_valid]);
    $msg = $request->is_valid == 1 ? '设备启用' : '设备停用';

    return $this->success($msg . "成功。");
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


    DB::enableQueryLog();
    $subQuery = $this->equipment->maintainModel()
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', str2Array($request->proj_ids));
        $request->device_name && $q->where('device_name', 'like', '%' . $request->device_name . '%');
        $request->major && $q->where('major', $request->major);
        $request->start_date && $q->where('maintain_date', '>=', $request->start_date);
        $request->end_date && $q->where('maintain_date', '<=', $request->end_date);
        $request->c_uid && $q->where('c_uid', $request->uid);
        $request->year && $q->whereYear('maintain_date', $request->year);
        $request->equipment_id && $q->where('equipment_id', $request->equipment_id);
        $request->plan_id && $q->where('plan_id', $request->plan_id);
        // $request->maintain_period && $q->where('maintain_period', $request->maintain_period);
      })
      ->with('equipment:id,third_party,maintain_period,tenant_id')
      ->whereHas('equipment', function ($q) use ($request) {
        $request->third_party && $q->where('third_party', $request->third_party);
        isset($request->tenant_id) && $q->where('tenant_id', $request->tenant_id);
      })
      ->with('maintainPlan')
      ->whereHas('maintainPlan', function ($q) use ($request) {
        $request->plan_start_date && $q->where('plan_date', '>=', $request->plan_start_date);
        $request->plan_end_date && $q->where('plan_date', '<=', $request->plan_end_date);
      });

    // return response()->json(DB::getQueryLog());
    $data = $this->pageData($subQuery, $request);
    foreach ($data['result'] as $k => &$v) {
      $v['plan_date']             = $v['maintain_plan']['plan_date'] ?? "";
      $v['plan_quantity']         = $v['maintain_plan']['plan_quantity'] ?? 0;
      $v['third_party_label']     = $v['equipment']['third_party_label'] ?? "";
      $v['maintain_period_label'] = $v['equipment']['maintain_period_label'] ?? "";
      $v['tenant_name']           = $v['equipment']['tenant_name'] ?? "";
      unset($v['maintain_plan']);
    }
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
      'attachment' => 'String',
    ]);
    $DA = $request->toArray();
    try {
      $maintainId = $this->equipment->saveEquipmentMaintain($DA, $this->user);
      $this->equipment->updateMaintainPlan($maintainId);


      $attrService = new AttachmentService;

      $attrService->saveAttachment($DA['attachment'], $maintainId, AppEnum::EquipmentMaintain, $this->user);
      return $this->success('设备维护保存成功。');
    } catch (Exception $e) {
      return $this->error('设备维护保存失败！' . $e->getMessage());
    }
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
    try {
      $DA = $request->toArray();
      $this->equipment->saveEquipmentMaintain($DA, $this->user);

      $attrService = new AttachmentService;
      $attrService->saveAttachment($DA['attachment'], $DA['id'], AppEnum::EquipmentMaintain, $this->user);
      return $this->success('设备维护更新成功。');
    } catch (Exception $e) {
      return $this->error('设备维护更新失败！' . $e->getMessage());
    }
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
    $data = $this->equipment->maintainModel()
      ->with('maintainPlan')
      ->with('attachment')
      ->with('equipment:id,third_party,maintain_period,tenant_id')
      ->find($request->id);
    if ($data) {

      $attrs = $data->attachment->pluck('file_path')->toArray();
      $data->attachment = $attrs;
      $data->attachment_full = array_map(function ($v) {
        return picFullPath($v);
      }, $attrs);
    }
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
    foreach ($DA['Ids'] as $maintainId) {
      $res = $this->equipment->deleteMaintain($maintainId);
    }

    if ($res) {
      return $this->success("维护记录删除成功。");
    }
    return $this->error('维护记录删除失败！');
  }
}
