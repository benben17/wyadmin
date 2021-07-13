<?php

namespace App\Api\Controllers\Bill;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Models\Company\FeeType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

use App\Api\Services\Tenant\TenantBillService;
use App\Enums\AppEnum;

/**
 * 租户账单
 */

class BillDetailController extends BaseController
{

  function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->user = auth('api')->user();
    $this->billService = new TenantBillService;
  }


  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/fee/list",
   *     tags={"费用"},
   *     summary="费用详细列表",
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
    if ($request->tenant_name) {
      $map['tenant_name'] = $request->tenant_name;
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

    // $ok =  FeeType::where('type', 1)
    //   ->with('feeStat')
    //   ->get();

    // return $ok;

    $data = $this->billService->billDetailModel()
      ->where($map)
      ->where('type', AppEnum::feeType)
      ->where(function ($q) use ($request) {
        $request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
      })
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    $data = $this->handleBackData($data);
    return $this->success($data);
  }
}
