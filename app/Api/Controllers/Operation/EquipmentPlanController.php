<?php

namespace App\Api\Controllers\Operation;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Api\Controllers\BaseController;
use App\Api\Services\Operation\EquipmentService;

class EquipmentPlanController extends BaseController
{
  private $equipment;
  public function __construct()
  {
    parent::__construct();
    $this->equipment = new EquipmentService;
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
  public function list(Request $request)
  {
    // $validatedData = $request->validate([
    //     'order_type' => 'required|numeric',
    // ]);
    $pagesize = $this->setPagesize($request);
    // if (!$request->year) {
    //   // $request->year = date('Y');
    // }
    // 排序字段
    if ($request->input('orderBy')) {
      $orderBy = $request->input('orderBy');
    } else {
      $orderBy = 'maintain_date';
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

  public function store(Request $request)
  {
    $request->validate([
      'equipment_id'    => 'required',
      'plan_date'   => 'required|date',
      'plan_quantity' => 'required',
    ]);
    $DA = $request->toArray();
    $planExists  = $this->equipment->MaintainPlanModel()
      ->where('equipment_id', $DA['equipment_id'])
      ->whereYear('plan_date', dateFormat("Y", $DA['plan_date']))
      ->whereMonth('plan_date', dateFormat('m', $DA['plan_date']))->exists();
    if ($planExists) {
      return $this->error('该月份已存在维护计划');
    }
    $res = $this->equipment->saveMaintainPlan($DA, $this->user);
    if ($res) {
      return $this->success('维护计划保存成功。');
    }
    return $this->error('维护计划保存失败！');
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

  public function edit(Request $request)
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
  public function delete(Request $request)
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
  public function show(Request $request)
  {
    $request->validate([
      'id' => 'required',
    ]);
    $data = $this->equipment->MaintainPlanModel()
      ->withCount('maintain')
      // ->where('year', $request->year)
      ->with('maintain')->find($request->id);
    $data['equipment_quantity'] = $this->equipment->equipmentModel()
      ->find($data['equipment_id'])->pluck('quantity')->first();
    return $this->success($data);
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
   *       example={"equipment_ids":"[1,2,3]","year":"2024"}
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
}
