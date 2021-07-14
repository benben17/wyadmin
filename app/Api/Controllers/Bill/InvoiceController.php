<?php

namespace App\Api\Controllers\Bill;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Services\Bill\InvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Tenant\TenantBillService;
use App\Enums\AppEnum;

/**
 * 租户账单
 */

class InvoiceController extends BaseController
{

  function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->user = auth('api')->user();
    $this->invoiceService = new InvoiceService;
  }

  public function createBill(Request $request)
  {
    if (!$request->tenantId) {
      $tenantId = 0;
    }
  }
  /**
   * @OA\Post(
   *     path="/api/operation/invoice/list",
   *     tags={"发票"},
   *     summary="发票列表",
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
    $data = $this->invoiceService->invoiceRecordModel()
      ->where($map)
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());

    $data = $this->handleBackData($data);
    return $this->success($data);
  }
  /**
   * @OA\Post(
   *     path="/api/operation/invoice/add",
   *     tags={"发票"},
   *     summary="发票新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"invoce_id","amount","invoice_no","status","bill_detail_id"},
   *       @OA\Property(property="invoce_id",type="int",description="发票titleID"),
   *       @OA\Property(property="amount",type="float",description="开票金额"),
   *       @OA\Property(property="invoice_no",type="String",description="发票no"),
   *       @OA\Property(property="status",type="int",description="0未开 1 已开"),
   *       @OA\Property(property="bill_detail_id",type="String",description="费用id集合多个id逗号隔开"),
   *       @OA\Property(property="invoice_type",type="String",description="发票类型")
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
    $validatedData = $request->validate([
      'invoce_id'        => 'required|numeric|gt:0',
      'invoice_no' => 'required|numeric|gt:0',
      'status'      => 'requird|in:0,1',
      'amount'    => 'required',
      'proj_id'    => 'required|numeric|gt:0',
      'bill_detail_id' => 'required|String',
    ]);
  }
}
