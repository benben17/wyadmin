<?php

namespace App\Api\Controllers\Operation;

use JWTAuth;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

use App\Api\Services\Tenant\TenantContractService;

/**
 *  租户合同管理
 */
class TenantContractController extends BaseController
{
  public function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->user = auth('api')->user();
    // $this->parent_type = AppEnum::Tenant;
  }


  /**
   * @OA\Post(
   *     path="/api/operation/tenant/contract/list",
   *     tags={"租户"},
   *     summary="租户合同列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"pagesize","orderBy","order"},
   *       @OA\Property(property="tenant_id",type="int",description="租户id")
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
    $pagesize = $request->input('pagesize');
    if (!$pagesize || $pagesize < 1) {
      $pagesize = config('per_size');
    }
    if ($pagesize == '-1') {
      $pagesize = config('export_rows');
    }
    $map = array();
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
    $contractService = new TenantContractService;
    DB::enableQueryLog();
    $result = $contractService->model()->where($map)
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    return $this->success($result);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/contract/edit",
   *     tags={"租户"},
   *     summary="租户合同列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"pagesize","orderBy","order"},
   *       @OA\Property(property="id",type="int",description="合同ID")
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
  public function edit(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required',
    ]);
    $contractService = new TenantContractService;
    $res = $contractService->save($request->toArray, $this->user, 'edit');
    if ($res) {
      return $this->success("租户合同保存成功.");
    } else {
      return $this->error("租户合同保存失败！");
    }
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/contract/add",
   *     tags={"租户"},
   *     summary="租户合同列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"pagesize","orderBy","order"},
   *       @OA\Property(property="tenant_id",type="int",description="租户ID")
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

  public function add(Request $request)
  {
    $validatedData = $request->validate([
      'tenant_id' => 'required',
    ]);

    $contractService = new TenantContractService;
    $res = $contractService->save($request->toArray, $this->user, 'add');
    if ($res) {
      return $this->success("租户合同保存成功.");
    } else {
      return $this->error("租户合同保存失败！");
    }
  }
}
