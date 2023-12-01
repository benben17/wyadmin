<?php

namespace App\Api\Controllers\Operation;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Services\Operation\InspectionService;
use PhpParser\Node\Stmt\TryCatch;

/**
 *   巡检
 */
class InspectionController extends BaseController
{
  private $inspection;
  public function __construct()
  {
    parent::__construct();
    $this->inspection = new InspectionService;
  }
  /**
   * @OA\Post(
   *     path="/api/operation/inspection/list",
   *     tags={"巡检"},
   *     summary="巡检点列表",
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
    $data = $this->inspection->inspectionModel()
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->device_name && $q->where('device_name', 'like', '%' . $request->device_name . '%');
        $request->name && $q->where('name', 'like', '%' . $request->name . '%');
      })
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    $data = $this->handleBackData($data);
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/inspection/add",
   *     tags={"巡检"},
   *     summary="巡检新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"proj_id","device_name","name","model"},
   *       @OA\Property(property="device_name",type="int",description="巡检设备名称"),
   *       @OA\Property(property="proj_id",type="int",description="项目ID"),
   *       @OA\Property(property="type",type="String",description="工程/秩序"),
   *       @OA\Property(property="check_cycle",type="String",description="巡检周期")
   *
   *     ),
   *       example={"position":"","major":"","device_name":"","model":"","check_cycle":""}
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
      'type'         => 'required|numeric|in:1,2',
      'proj_id'         => 'required|numeric|gt:0',
      'device_name'     => 'required|String',
      'position'        => 'required|String',
    ]);
    $DA = $request->toArray();

    $res = $this->inspection->saveInspection($DA, $this->user);
    if (!$res) {
      return $this->error('巡检点保存失败！');
    }
    return $this->success('巡检点保存成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/inspection/edit",
   *     tags={"巡检"},
   *     summary="巡检编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","proj_id","device_name","name","model"},
   *       @OA\Property(property="id",type="int",description="id"),
   *       @OA\Property(property="device_name",type="int",description="巡检设备名称"),
   *       @OA\Property(property="proj_id",type="int",description="项目ID"),
   *       @OA\Property(property="type",type="String",description="工程/秩序"),
   *       @OA\Property(property="check_cycle",type="String",description="巡检周期")
   *
   *     ),
   *       example={"id":"","position":"","major":"","device_name":"","model":"","check_cycle":""}
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
      'proj_id'         => 'required|numeric|gt:0',
      'device_name'     => 'required|String',
      'position'        => 'required|String',
    ]);
    $DA = $request->toArray();

    $res = $this->inspection->saveInspection($DA, $this->user);
    if (!$res) {
      return $this->error('巡检更新失败！');
    }
    return $this->success('巡检点更新成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/inspection/show",
   *     tags={"巡检"},
   *     summary="巡检查看",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="ID")
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
    $data = $this->inspection->inspectionModel()
      ->find($DA['id'])->toArray();
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/inspection/del",
   *     tags={"巡检"},
   *     summary="巡检删除",
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
    $res = $this->inspection->inspectionModel()->whereIn('id', $request->Ids)->delete();
    if ($res) {
      return $this->success("删除成功");
    }
    return $this->error('删除失败！');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/inspection/record/list",
   *     tags={"巡检"},
   *     summary="巡检记录列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"end_date","start_date","position","model"},
   *       @OA\Property(property="proj_ids",type="list",description="设备ID"),
   *       @OA\Property(property="model",type="String",description="工程/秩序"),
   *       @OA\Property(property="start_date",type="date",description="日期"),
   *       @OA\Property(property="end_date",type="String",description="维护内容")
   *
   *     ),
   *       example={"end_date":"","start_date":"","position":"",
   *       "model":"","uid":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function recordList(Request $request)
  {
    $pagesize = $request->input('pagesize');
    if (!$pagesize || $pagesize < 1) {
      $pagesize = config('per_size');
    }
    if ($pagesize == '-1') {
      $pagesize = config('export_rows');
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
    $data = $this->inspection->inspectionRecordModel()
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->start_date && $q->where('created_at', '>=', $request->start_date);
        $request->end_date && $q->where('created_at', '<=', $request->end_date);
        $request->c_uid && $q->where('c_uid', $request->uid);
        $request->inspection_id && $q->where('inspection_id', $request->inspection_id);
      })->with('inspection:id,name,device_name,proj_id,check_cycle')
      ->paginate($pagesize)->toArray();
    $data = $this->handleBackData($data);
    if ($data['result']) {
      foreach ($data['result'] as $k => &$v) {
        $v['name'] = $v['inspection']['name'];
        $v['device_name'] = $v['inspection']['device_name'];
      }
    }

    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/inspection/record/add",
   *     tags={"巡检"},
   *     summary="巡检记录新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"inspection_id","is_unusual"},
   *       @OA\Property(property="inspection_id",type="int",description="巡检ID"),
   *       @OA\Property(property="is_unusual",type="int",description="1 正常 0 不正常")
   *
   *     ),
   *       example={"inspection_id":"","is_unusual":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function recordStore(Request $request)
  {
    $validatedData = $request->validate([
      'inspection_id'    => 'required',
      'is_unusual'        => 'required|numeric|in:0,1', //0 异常 1 正常

    ]);
    $DA = $request->toArray();
    $res = $this->inspection->saveInspectionRecord($DA, $this->user);
    if (!$res) {
      return $this->error('设备维护保存失败！');
    }
    return $this->success('设备维护保存成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/inspection/qrcode",
   *     tags={"巡检"},
   *     summary="巡检点生成二维码",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"inspection_id"},
   *       @OA\Property(property="inspection_id",type="int",description="巡检ID")
   *     ),
   *       example={"inspection_id":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function inspectionCreateQr(Request $request)
  {
    $validatedData = $request->validate([
      'id'            => 'required|numeric|gt:0',
    ]);
    $res = $this->inspection->createQr($request->id, $request->id, $this->company_id);

    if (!$res) {
      return $this->error('生成二维码失败！');
    }
    return $this->success('生成二维码成功。');
  }
  /**
   * @OA\Post(
   *     path="/api/operation/inspection/record/edit",
   *     tags={"巡检"},
   *     summary="巡检记录编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"inspection_id","is_unusual"},
   *       @OA\Property(property="inspection_id",type="int",description="巡检ID"),
   *       @OA\Property(property="is_unusual",type="int",description="1 正常 0 不正常")
   *
   *     ),
   *       example={"inspection_id":"","is_unusual":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function recordUpdate(Request $request)
  {
    $validatedData = $request->validate([
      'id'            => 'required|numeric|gt:0',
      'inspection_id'    => 'required',
      'is_unusual'        => 'required|int',

    ]);
    $DA = $request->toArray();
    $res = $this->inspection->saveInspectionRecord($DA, $this->user);
    if (!$res) {
      return $this->error('设备维护更新失败！');
    }
    return $this->success('设备维护更新成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/inspection/record/show",
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
  public function recordShow(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required|numeric|gt:0',
    ]);
    $DA = $request->toArray();
    $data = $this->inspection->inspectionRecordModel()
      ->with('inspection')
      ->find($DA['id'])
      ->toArray();
    if ($data['is_unusual'] == 1) {
      $data['is_unusual_label'] = '正常';
    } else {
      $data['is_unusual_label'] = '异常';
    }
    $data['name'] = $data['inspection']['name'];
    $data['device_name'] = $data['inspection']['device_name'];
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/inspection/record/del",
   *     tags={"巡检"},
   *     summary="巡检记录删除",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"Ids"},
   *       @OA\Property(property="Ids",type="int",description="记录IDs")
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

  public function recordDelete(Request $request)
  {
    $validatedData = $request->validate([
      'Ids' => 'required|array',
    ]);
    $DA = $request->toArray();
    $res = $this->inspection->inspectionRecordModel()->whereIn('id', $request->Ids)->delete();
    if ($res) {
      return $this->success("记录删除成功。");
    }
    return $this->error('删除失败！');
  }
}
