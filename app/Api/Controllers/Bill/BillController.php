<?php

namespace App\Api\Controllers\Bill;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Tenant\TenantBillService;
use App\Api\Services\Tenant\TenantService;
use App\Enums\AppEnum;
use Exception;

/**
 * 租户账单
 */

class BillController extends BaseController
{

  function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->user = auth('api')->user();
    $this->parent_type = AppEnum::Tenant;
  }


  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/list",
   *     tags={"账单"},
   *     summary="租户列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"pagesize","orderBy","order"},
   *       @OA\Property(property="name",type="String",description="客户名称")
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
    DB::enableQueryLog();
    $billService = new TenantBillService;
    $data = $billService->billModel()
      ->where($map)
      ->with(['billDetail' => function ($q) {
        $q->selectRaw('sum(amount) totalAmt,sum(discount_amount) discountAmt');
      }])
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());

    $data = $this->handleBackData($data);
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/create",
   *     tags={"账单"},
   *     summary="租户列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"tenantIds","billMonths","chargeDate","feeTypes"},
   *       @OA\Property(property="tenantIds",type="List",description="客户Id集合")
   *      @OA\Property(property="billMonths",type="String",description="账单年月例如：2021-07"),
   *      @OA\Property(property="chargeDate",type="String",description="应收日"),
   *      @OA\Property(property="feeTypes",type="List",description="费用类型Id列表"),
   *     ),
   *       example={"tenantIds":"[]","billMonths":"2021-07","chargeDate":"2021-07-05","feeTypes":"[101]"}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function createBill(Request $request)
  {

    $validatedData = $request->validate([
      // 'tenantIds' => 'required|array',
      'bill_month' => 'required|String',
      'charge_date' => 'required',
      'fee_types' => 'required|array',
    ]);
    try {
      // DB::transaction(function () use ($request) {
      $billService = new TenantBillService;
      $tenantService = new TenantService;
      $tenants = $tenantService->tenantModel()
        ->where(function ($q) use ($request) {
          $request->tenant_ids && $q->whereIn('id', $request->tenant_ids);
        })->whereType(2)->whereIn('proj_id', $request->proj_ids)->get();

      $startDate = date('Y-m-01', strtotime($request->bill_month));
      $endDate = date('Y-m-t', strtotime($request->bill_month));
      foreach ($tenants as $k => $v) {
        $bill = $billService->billModel()->where('tenant_id', $v['id'])->whereBetween('charge_date', [$startDate, $endDate])->count();
        if ($bill > 0) {
          Log::error("已有账单，租户Id" . $v['id']);
          continue;
        }
        $res = $billService->createBill($v['id'], $request->bill_month, $request->fee_types, $request->charge_date, $this->user);
        if (!$res) {
          Log::error("生成账单日志" . $res);
        }
      }
      // }, 3);
      return $this->success("账单生成成功。");
    } catch (Exception $th) {
      Log::error($th);
      return $this->error("账单生成失败！");
    }
  }
}
