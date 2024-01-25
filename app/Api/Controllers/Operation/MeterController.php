<?php

namespace App\Api\Controllers\Operation;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Services\Energy\EnergyService;
use App\Api\Services\Common\QrcodeService;
use App\Api\Services\CustomerService;
use App\Api\Services\Tenant\TenantService;

/**
 *   能耗管理。水表电表管理
 */
class MeterController extends BaseController
{

  private $meterService;
  public function __construct()
  {
    parent::__construct();
    $this->meterService = new EnergyService;
  }


  /**
   * @OA\Post(
   *     path="/api/operation/meter/list",
   *     tags={"能耗管理"},
   *     summary="水表电表列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"type"},
   *       @OA\Property(property="type",type="int",description="1 水表2 电表"),
   *       @OA\Property(property="pagesize",type="int",description="行数"),
   *       @OA\Property(property="proj_id",type="int",description="项目ID"),
   *       @OA\Property(property="build_no",type="String",description="楼号"),
   *       @OA\Property(property="cus_id",type="int",description="客户ID"),
   *       @OA\Property(property="master_slave",type="String",description="总表字表"),
   *       @OA\Property(property="meter_type",type="int",description="1物理表2虚拟表 账单表"),
   *       @OA\Property(property="is_vaild",type="int",description="1 启用 0 禁用")
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
  public function list(Request $request)
  {
    $validator = \Validator::make($request->all(), [
      'type' => 'required|numeric|in:1,2',   //"1水表 2 电表"
    ]);
    $pagesize = $request->input('pagesize');
    if (!$pagesize || $pagesize < 1) {
      $pagesize = config('per_size');
    }
    if ($pagesize == '-1') {
      $pagesize = config('export_rows');
    }
    if ($request->type) {
      $map['type'] = $request->type;
    } else {
      $map['type'] = 1;
    }

    if ($request->meter_no) {
      $map['meter_no'] = $request->meter_no;
    }
    if ($request->master_slave) {
      $map['master_slave'] = $request->master_slave;
    }
    // 通过租户查询
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
    $currentDate = date('Y-m-01');
    $data = $this->meterService->meterModel()
      ->where($map)
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->tenant_id && $q->where('tenant_id',  $request->tenant_id);
      })
      ->withCount(['meterRecord' => function ($q) {
        $q->where('record_date', '>', date('Y-m-01'));
      }])
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());
    $data = $this->handleBackData($data);
    foreach ($data['result'] as $k => &$v) {
      $record = $this->meterService->getNewMeterRecord($v['id']);
      $v['last_record']  = $record->meter_value;
      $v['last_date'] = $record->record_date;
      $v['tenant_name'] = $this->meterService->getTenantByRoomId($v['room_id']);
    }
    return $this->success($data);
  }
  /**
   * @OA\Post(
   *     path="/api/operation/meter/add",
   *     tags={"能耗管理"},
   *     summary="水表电表新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"type","meter_no","master_slave","proj_id"},
   *       @OA\Property(property="type",type="int",description="1 水表2 电表"),
   *       @OA\Property(property="meter_no",type="int",description="表编号"),
   *       @OA\Property(property="proj_id",type="int",description="项目ID"),
   *       @OA\Property(property="build_no",type="String",description="楼号"),
   *       @OA\Property(property="tenant_id",type="int",description="组户ID"),
   *       @OA\Property(property="master_slave",type="String",description="总表字表"),
   *       @OA\Property(property="is_vaild",type="int",description="1 启用 0 禁用")
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
    $validator = \Validator::make($request->all(), [
      'meter_no' => 'required|String|max:64',
      'type' => 'required|numeric|in:1,2',   //"1水表 2 电表"
      'multiple' =>  'required|numeric|gt:0',
      'proj_id' =>  'required|numeric|gt:0',
      'master_slave' => 'required|String|in:1,0',
      'meter_type' => 'required|numeric|in:1,2', // 实际表 虚拟表
    ]);
    $DA = $request->toArray();
    if ($this->meterService->isRepeat($DA, $this->user)) {
      return $this->error('表编号重复');
    }
    $res = $this->meterService->saveMeter($DA, $this->user);
    if (!$res) {
      return $this->error('新增能源表失败！');
    }
    return $this->success('新增能源表成功。');
  }
  /**
   * @OA\Post(
   *     path="/api/operation/meter/edit",
   *     tags={"能耗管理"},
   *     summary="水表电表编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","meter_no","master_slave","proj_id"},
   *       @OA\Property(property="id",type="int",description="表ID"),
   *       @OA\Property(property="type",type="int",description="1 水表2 电表"),
   *       @OA\Property(property="meter_no",type="int",description="表编号"),
   *       @OA\Property(property="proj_id",type="int",description="项目ID"),
   *       @OA\Property(property="build_no",type="String",description="楼号"),
   *       @OA\Property(property="tenant_id",type="int",description="客户ID"),
   *       @OA\Property(property="master_slave",type="String",description="总表字表"),
   *       @OA\Property(property="is_vaild",type="int",description="1 启用 0 禁用")
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

    $validator = \Validator::make($request->all(), [
      'id' => 'required|numeric|gt:0',
      'meter_no' => 'required|String|max:64',
      'type' => 'required|numeric|in:1,2',
    ]);

    $DA = $request->toArray();
    if ($this->meterService->isRepeat($DA, $this->user)) {
      return $this->error('表编号重复');
    }
    $user = auth('api')->user();
    $res = $this->meterService->saveMeter($DA, $this->user);
    if (!$res) {
      return $this->error('更新能源表失败！');
    }
    return $this->success('更新能源表成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/meter/enable",
   *     tags={"能耗管理"},
   *     summary="水表电表启用禁用",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"Ids","is_vaild"},
   *       @OA\Property(property="Ids",type="list",description="ID集合"),
   *       @OA\Property(property="is_vaild",type="int",description="1 启用 0 禁用")
   *     ),
   *       example={"Ids":"[1,2]","is_vaild":1}
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
    $validator = \Validator::make($request->all(), [
      'Ids' => 'required|array',
      'is_vaild' => 'required|numeric|in:0,1',
    ]);
    $DA = $request->toArray();
    $res = $this->meterService->enableMeter($DA);
    if (!$res) {
      return $this->error('更新能源表失败！');
    }
    return $this->success('更新能源表成功。');
  }
  /**
   * @OA\Post(
   *     path="/api/operation/meter/show",
   *     tags={"能耗管理"},
   *     summary="水表电表详细信息查看，包含上次最新的读数",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *       @OA\Property(property="id",type="int",description="ID集合"),
   *       @OA\Property(property="meter_no",type="String",description="表编号")
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
    // $validator = \Validator::make($request->all(), [
    //   'id' => 'required|numeric|gt:0',
    // ]);

    if (!$request->id && !$request->meter_no) {
      return $this->error('参数错误，id或者表编号至少有一个！');
    }
    $data = $this->meterService->meterModel()
      ->where(function ($q) use ($request) {
        $request->id && $q->whereId($request->id);
        $request->meter_no && $q->whereId($request->meter_no);
      })
      ->with('remark')
      ->first();
    if ($data) {
      $data['tenant_name'] = $this->meterService->getTenantByRoomId($data['room_id']);
      $record = $this->meterService->getNewMeterRecord($request->id);
      $data['last_record']  = $record->meter_value;
      $data['last_date'] = $record->record_date;
    }

    return $this->success($data);
  }

  /** 抄表 */
  /**
   * @OA\Post(
   *     path="/api/operation/meter/record/add",
   *     tags={"能耗管理"},
   *     summary="水电表抄表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *       @OA\Property(property="meter_id",type="int",description="表ID"),
   *       @OA\Property(property="meter_no",type="String",description="表编号"),
   *       @OA\Property(property="meter_value",type="int",description="表数"),
   *       @OA\Property(property="record_date",type="date",description="抄录日期")
   *     ),
   *       example={"meter_id":"4","meter_value":"","record_date":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function addMeterRecord(Request $request)
  {
    $validator = \Validator::make($request->all(), [
      'meter_id' => 'required|numeric|gt:0',
      // 'meter_no' => 'required|numeric|gt:0',
      'meter_value' => 'required|numeric|gt:0',
      'record_date' => 'required|date',
    ]);
    $DA = $request->toArray();
    $res = $this->meterService->saveMeterRecord($DA, $this->user);
    if ($res) {
      return $this->success('抄表成功.');
    } else {
      return $this->error('抄表失败！');
    }
  }


  /**
   * @OA\Post(
   *     path="/api/operation/meter/record/edit",
   *     tags={"能耗管理"},
   *     summary="水电表记录更新",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","meter_value"},
   *       @OA\Property(property="id",type="int",description="抄表记录ID"),
   *       @OA\Property(property="meter_value",type="int",description="表数"),
   *       @OA\Property(property="pic",type="String",description="图片地址"),
   *       @OA\Property(property="remark",type="String",description="备注"),
   *       @OA\Property(property="record_date",type="date",description="抄录时间")
   *     ),
   *       example={"id":"4","meter_value":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function editMeterRecord(Request $request)
  {
    $validator = \Validator::make($request->all(), [
      'id' => 'required|numeric|gt:0',
      'meter_value' => 'required|numeric|gt:0',
    ]);

    $DA = $request->toArray();
    $res = $this->meterService->editMeterRecord($DA, $this->user);
    if ($res) {
      return $this->success('更新成功.');
    } else {
      return $this->error('更新失败！');
    }
  }

  /**
   * @OA\Post(
   *     path="/api/operation/meter/record/audit",
   *     tags={"能耗管理"},
   *     summary="水表电表审核",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"Ids"},
   *       @OA\Property(property="Ids",type="list",description="ID集合")
   *
   *     ),
   *       example={"Ids":"[1,2]"}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function auditMeterRecord(Request $request)
  {
    $validator = \Validator::make($request->all(), [
      'Ids' => 'required|array',
    ]);
    $DA = $request->toArray();
    $res = $this->meterService->auditMeterRecord($DA['Ids'], $this->user);
    if ($res) {
      return $this->success('审核成功.');
    } else {
      return $this->error('审核失败！');
    }
  }


  /**
   * @OA\Post(
   *     path="/api/operation/meter/record/show",
   *     tags={"能耗管理"},
   *     summary="水电表记录更新",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="抄表记录ID")
   *     ),
   *       example={"id":"4"}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function showMeterRecord(Request $request)
  {
    $validator = \Validator::make($request->all(), [
      'id' => 'required|numeric|gt:0',
    ]);

    $data = $this->meterService->meterRecordModel()
      ->with('meter')
      ->find($request->id);

    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/meter/record/list",
   *     tags={"能耗管理"},
   *     summary="抄表列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *       @OA\Property(property="proj_ids",type="list",description="项目IDs"),
   *       @OA\Property(property="meter_no",type="int",description="表编号"),
   *       @OA\Property(property="type",type="int",description="水表，2电表"),
   *
   *     ),
   *       example={"proj_ids":"4","meter_no":"","type":1}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function listMeterRecord(Request $request)
  {
    $validatedData = $request->validate([
      'type' => 'required|numeric|in:1,2',   // "1水表 2 电表"
    ]);
    $pagesize = $request->input('pagesize');
    if (!$pagesize || $pagesize < 1) {
      $pagesize = config('per_size');
    }
    if ($pagesize == '-1') {
      $pagesize = config('export_rows');
    }
    $map = array();
    if (isset($request->audit_status) && !$request->audit_status) {
      $map['audit_status'] = $request->audit_status;
    }
    if ($request->meter_id) {
      $map['meter_id'] = $request->meter_id;
    }
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
    $DA = $request->toArray();
    DB::enableQueryLog();
    $data = $this->meterService->meterRecordModel()
      ->where($map)
      ->whereHas('meter', function ($q) use ($request) {
        $q->where('type', $request->type);
        $request->meter_no && $q->where('meter_no', $request->meter_no);
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
      })
      ->with('meter:id,meter_no,proj_id,parent_id,type,master_slave,build_no,floor_no,room_no,room_id')
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    $data = $this->handleBackData($data);
    foreach ($data['result'] as $k => &$v) {
      $v['meter_no'] = $v['meter']['meter_no'];
      $v['proj_name'] = $v['meter']['proj_name'];
      $v['tenant_name'] = $this->meterService->getTenantByRoomId($v['meter']['room_id']);
      $v['is_virtual'] = $v['meter']['is_virtual'];
      $v['room_info']  = $v['meter']['build_no'] . "-" . $v['meter']['floor_no'] . "-" . $v['meter']['room_no'];
      if (empty($v['audit_user']) && $v['pre_used_value'] > 0) {
        $used = abs($v['used_value'] - $v['pre_used_value']) / $v['pre_used_value'] * 100;
        // Log::error($used);
        if ($used >= 50) {
          $v['unusual'] = 0;
        } else {
          $v['unusual'] = 1;
        }
      } else {
        $v['unusual'] = 1;
      }
      unset($v['meter']);
    }
    // return response()->json(DB::getQueryLog());
    return $this->success($data);
  }


  public function qrcode()
  {
    $qrcode = new QrcodeService;
    return $qrcode->create('https://app.stararea.cn', $this->company_id);
  }
}
