<?php

namespace App\Api\Controllers\Bill;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Models\Bill\ReceiveBill;
use App\Api\Models\Company\FeeType;
use App\Api\Services\Bill\InvoiceService;
use App\Api\Services\Tenant\ChargeService;
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
    if (!$request->start_date) {
      $request->start_date = date('Y-01-01', strtotime(nowYmd()));
    }
    if (!$request->end_date) {
      $request->end_date = date('Y-m-t', strtotime(nowYmd()));
    }
    DB::enableQueryLog();
    $map['type'] =  AppEnum::feeType;
    $subQuery = $this->billService->billDetailModel()
      ->where($map)
      ->where(function ($q) use ($request) {
        $request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . '%');
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->start_date && $q->where('charge_date', '>=', $request->start_date);
        $request->end_date && $q->where('charge_date', '<=', $request->end_date);
      });

    $result = $subQuery->orderBy($orderBy, $order)->paginate($pagesize)->toArray();
    // return response()->json(DB::getQueryLog());
    $feeStat =  FeeType::selectRaw('fee_name,id,type')->where('type', 1)
      ->whereIn('company_id', getCompanyIds($this->uid))->get();
    foreach ($feeStat as $k => &$v) {
      $count = $subQuery->selectRaw('sum(amount) total_amt,sum(receive_amount) receive_amt,fee_type')
        ->where('fee_type', $v['id'])
        ->groupBy('fee_type')->first();
      $v['total_amt'] =  $count['total_amt'] ? $count['total_amt'] : 0.00;
      $v['receive_amt'] =  $count['receive_amt'] ? $count['receive_amt'] : 0.00;
      $v['unreceive_amt'] = $count['total_amt'] - $count['receive_amt'];
    }
    $data = $this->handleBackData($result);
    $data['stat'] = $feeStat;
    return $this->success($data);
  }
  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/fee/show",
   *     tags={"费用"},
   *     summary="费用收款",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="费用 id")
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
    $validatedData = $request->validate([
      'id' => 'required|numeric|gt:0',

    ]);
    DB::enableQueryLog();
    $data = $this->billService->billDetailModel()
      ->with(['chargeBillRecord' => function ($q) {
        $q->with('charge:id,charge_date,flow_no,amount,c_uid');
      }])
      ->with('contract:id,contract_no')
      ->find($request->id);
    $invoiceService = new InvoiceService;
    $invoiceRecord = $invoiceService->invoiceRecordModel()->find($request->id);
    if (!$invoiceRecord) {
      $invoiceRecord =   (object)array();
    }
    $data['invoice_record'] = $invoiceRecord;
    // return response()->json(DB::getQueryLog());
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/tenant/bill/fee/verify",
   *     tags={"费用"},
   *     summary="费用收款",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"bill_detail_id","charge_id"},
   *       @OA\Property(property="bill_detail_id",type="int",description="账单费用id"),
   *       @OA\Property(property="charge_id",type="float",description="收款单ID"),
   *      
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
  public function verify(Request $request)
  {
    $validatedData = $request->validate([
      'bill_detail_id' => 'required|numeric|gt:0',
      'charge_id' => 'required|gt:0'
    ]);

    $billDetail = $this->billService->billDetailModel()->where('status', 0)->findOrFail($request->bill_detail_id);
    if (!$billDetail) {
      return $this->error("未发账单现数据！");
    }
    $chargeService = new ChargeService;
    $chargeBill =  $chargeService->model()->where(function ($q) {
      $q->where('unverify_amount', '>', 0.00);
    })
      ->findOrFail($request->charge_id);
    if (!$chargeBill) {
      return $this->error("未发现充值数据！");
    }
    $verifyDate = nowYmd();
    $chargeService = new ChargeService;
    $res =  $chargeService->detailBillVerify($billDetail->toArray(), $chargeBill->toArray(), $verifyDate, $this->user);
    if ($res) {
      return $this->success("核销成功");
    } else {
      return $this->error("核销失败");
    }
  }
}
