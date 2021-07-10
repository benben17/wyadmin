<?php

namespace App\Api\Controllers\Operation;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Contract\ChargeService;

class ChargeController extends BaseController
{
  public function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->charge = new ChargeService;
    $this->user = auth('api')->user();
  }

  /**
   * @OA\Post(
   *     path="/api/operation/charge/list",
   *     tags={"预充值"},
   *     summary="预充值列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={""},
   *       @OA\Property(property="tenant_id",type="int",description="租户id"),
   *       @OA\Property(property="pagesize",type="int",description="行数"),
   *       @OA\Property(property="tenant_name",type="String",description="租户名称"),
   *       @OA\Property(property="start_date",type="date",description="开始时间"),
   *       @OA\Property(property="end_date",type="date",description="结束时间"),
   *       @OA\Property(property="charge_type",type="String",description="充值类型 数组"),
   *       @OA\Property(property="audit_status",type="String",description="1待审核2已审核3 拒绝"),
   *        @OA\Property(property="proj_ids",type="array",description="")
   *     ),
   *       example={"tenant_id":1,"tenant_name":"","start_date":"","end_date":"","audit_status":"1,2,3"}
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
    DB::enableQueryLog();
    $data = $this->charge->model()
      ->where(function ($q) use ($request) {
        $request->tenant_id && $q->whereIn('tenant_id', $request->tenant_id);
        $request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
        $request->start_date && $q->where('charge_date', '>=',  $request->start_date);
        $request->end_date && $q->where('charge_date', '<=',  $request->end_date);
        $request->charge_type && $q->whereIn('charge_type',  $request->charge_type);
        $request->audit_status && $q->whereIn('audit_status', str2Array($request->audit_status));
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
      })
      ->with('detail')
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());
    $data = $this->handleBackData($data);
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/charge/add",
   *     tags={"预充值"},
   *     summary="预充值新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"tenant_id,amount,charge_date","charge_type"},
   *       @OA\Property(property="tenant_id",type="int",description="租户id"),
   *       @OA\Property(property="amount",type="double",description="充值金额"),
   *       @OA\Property(property="charge_type",type="int",description="费用类型"),
   *       @OA\Property(property="charge_date",type="date",description="充值日期"),
   *       @OA\Property(property="proj_id",type="int",description="项目id")
   *     ),
   *       example={"tenant_id":1,"tenant_name":"2","amount":"","charge_date":"","charge_type":""}
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
      'tenant_id' => 'required|numeric|gt:0',
      'amount'    => 'required',
      'charge_type' => 'required',
      'proj_id'    => 'required|numeric|gt:0',
      'charge_date'    => 'required|date',
    ]);

    $res = $this->charge->save($request->toArray(), $this->user);
    if (!$res) {
      return $this->error("充值失败！");
    }
    return $this->success("充值成功。");
  }

  /**
   * @OA\Post(
   *     path="/api/operation/charge/edit",
   *     tags={"预充值"},
   *     summary="预充值编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"tenant_id,amount,charge_date","id","charge_type"},
   *       @OA\Property(property="id",type="int",description="id"),
   *       @OA\Property(property="tenant_id",type="int",description="租户id"),
   *       @OA\Property(property="amount",type="double",description="充值金额"),
   *       @OA\Property(property="charge_type",type="int",description="费用类型"),
   *       @OA\Property(property="charge_date",type="date",description="充值日期"),
   * @OA\Property(property="proj_id",type="int",description="项目id")
   *     ),
   *       example={"tenant_id":1,"tenant_name":"2","amount":"","charge_date":"","proj_id":""}
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
      'id'        => 'required|numeric|gt:0',
      'tenant_id' => 'required|numeric|gt:0',
      'amount'    => 'required',
      'proj_id'    => 'required|numeric|gt:0',
      'charge_date' => 'required|date',
    ]);

    $count = $this->charge->model()->where('audit_status', '!=', 2)->where('id', $request->id)->count();
    if (!$count) {
      return $this->error("不允许修改！");
    }
    $res = $this->charge->save($request->toArray(), $this->user);
    if (!$res) {
      return $this->error("更新失败！");
    }
    return $this->success("更新成功。");
  }
  /**
   * @OA\Post(
   *     path="/api/operation/charge/audit",
   *     tags={"预充值"},
   *     summary="预充值审核",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="充值ID"),
   *       @OA\Property(property="audit_status",type="int",description="3 拒绝2通过")
   *     ),
   *       example={"id":1,"audit_status":"2"}
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
      'id'           => 'required|numeric|gt:0',
      'audit_status' => 'required|numeric|in:2,3',
    ]);
    $charge = $this->charge->model()->where('id', $request->id)->where('audit_status', '!=', 2)->first();
    if (!$charge) {
      Log::error("不符合");
      return $this->error("不符合审核!");
    }
    $res = $this->charge->audit($request->id, $this->user, $request->audit_status);
    if ($res) {
      return $this->success("审核完成.");
    }
    return $this->error("审核失败!");
  }

  /**
   * @OA\Post(
   *     path="/api/operation/charge/del",
   *     tags={"预充值"},
   *     summary="预充值删除",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"ids"},
   *       @OA\Property(property="ids",type="List",description="id集合")
   *     ),
   *       example={"ids":"[1,2]"}
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
      'ids' => 'required|array',
    ]);

    $res = $this->charge->model()->where('audit_status', '!=', 2)->whereIn('id', $request->ids)->delete();
    if (!$res) {
      return $this->error("删除失败！");
    }
    return $this->success("删除成功。");
  }
  /**
   * @OA\Post(
   *     path="/api/operation/charge/show",
   *     tags={"预充值"},
   *     summary="预充值详细",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="id")
   *     ),
   *       example={"ids":"1"}
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
      'id' => 'required',
    ]);

    $data = $this->charge->model()
      ->with('detail')
      ->find($request->id);
    return $this->success($data);
  }
}
