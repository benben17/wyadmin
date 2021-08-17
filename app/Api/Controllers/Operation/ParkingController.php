<?php

namespace App\Api\Controllers\Operation;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Services\Operation\ParkingService;
use App\Api\Services\Tenant\TenantService;

/**
 *   车位管理
 */
class ParkingController extends BaseController
{
  public function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->parking = new ParkingService;
    $this->user = auth('api')->user();
  }


  /**
   * @OA\Post(
   *     path="/api/operation/parking/list",
   *     tags={"车位管理"},
   *     summary="设备列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={},
   *       @OA\Property(property="tenant_name",type="String",
   *         description="租户名字"),
   *       @OA\Property(property="pagesize",type="int",description="行数"),
   *       @OA\Property(property="renter_name",type="int",description="租户"),
   *       @OA\Property(property="proj_ids",type="int",description="项目IDs")
   *     ),
   *       example={"renter_name":1,"proj_ids":"[]","tenant_name":""}
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
    $data = $this->parking->parkingModel()
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
        $request->renter_name && $q->where('renter_name', 'like', '%' . $request->renter_name . '%');
      })
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    $data = $this->handleBackData($data);
    $stat = ['vaild_count' => 0, 'vaild_amount' => 0, 'total_amount' => 0];
    $tenant = new TenantService;
    foreach ($data['result'] as $k => &$v) {
      $v['tenant_name'] = $tenant->getTenantById($v['tenant_id']);
      if (strtotime($v['rent_end']) >= strtotime(nowYmd())) {
        $stat['vaild_count'] += 1;
        $stat['vaild_amount'] += $v['amount'];
      }
      $stat['total_amount'] += $v['amount'];
    }

    return $this->success($data);
  }


  /**
   * @OA\Post(
   *     path="/api/operation/parking/add",
   *     tags={"车位管理"},
   *     summary="车位新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"proj_id","renter_name","renter_phone","car_no"},
   *       @OA\Property(property="renter_name",type="int",description="租赁人"),
   *       @OA\Property(property="renter_phone",type="int",description="租赁人联系电话"),
   *       @OA\Property(property="car_no",type="String",description="车牌号")
   *     ),
   *       example={"renter_name":"","renter_phone":"","car_no":"","rent_start":"","rent_end":"",
   *       "month_price":"","amount":"","charge_date":""}
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
      'renter_name'     => 'required',
      'renter_phone'     => 'required|String',
      'car_no'        => 'required',
      'rent_start'        => 'required|date',
      'rent_end'      => 'required|date',
      'rent_month'        => 'required',
      'month_price'        => 'required',
      'amount'        => 'required',
      'charge_date'      => 'required|date'
    ]);
    $DA = $request->toArray();

    $res = $this->parking->saveParking($DA, $this->user);
    if (!$res) {
      return $this->error('车位保存失败！');
    }
    return $this->success('车位保存成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/parking/edit",
   *     tags={"工单"},
   *     summary="工单编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *           required={"id","proj_id","renter_name","renter_phone","car_no"},
   *       @OA\Property(property="renter_name",type="int",description="租赁人"),
   *       @OA\Property(property="renter_phone",type="int",description="租赁人联系电话"),
   *       @OA\Property(property="car_no",type="String",description="车牌号")
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
      'id'              => 'required',
      'proj_id'         => 'required|numeric|gt:0',
      'renter_name'     => 'required',
      'renter_phone'     => 'required|String',
      'car_no'        => 'required',
      'rent_start'        => 'required|date',
      'rent_end'      => 'required|date',
      'month_price'        => 'required',
      'amount'        => 'required',
      'charge_date'      => 'required|date'
    ]);
    $DA = $request->toArray();

    $res = $this->parking->saveParking($DA, $this->user);
    if (!$res) {
      return $this->error('更新失败！');
    }
    return $this->success('更新成功。');
  }



  /**
   * @OA\Post(
   *     path="/api/operation/parking/show",
   *     tags={"工单"},
   *     summary="接单",
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
    $data = $this->parking->parkingModel()
      ->find($DA['id'])->toArray();
    $tenant = new TenantService;
    $data['tenant_name'] = $tenant->getTenantById($data['tenant_id']);
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/parking/del",
   *     tags={"车位管理"},
   *     summary="车位删除",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="ID")
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
      'id' => 'required|numeric|gt:0',
    ]);
    $DA = $request->toArray();
    $res = $this->parking->parkingModel()->whereIn('id', $request->Ids)->delete();
    if ($res) {
      return $this->success("删除成功。");
    }
    return $this->error('删除失败！');
  }
}
