<?php

namespace App\Api\Controllers\Stat;

use JWTAuth;
use App\Enums\AppEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Api\Controllers\BaseController;
use App\Api\Services\Bill\ChargeService;
use App\Api\Services\Bill\TenantBillService;

class BillStatController extends BaseController
{
  private $billService;
  function __construct()
  {
    parent::__construct();
    $this->billService = new TenantBillService;
  }
  /**
   * @OA\Post(
   *     path="/api/operation/stat/bill",
   *     tags={"费用统计"},
   *     summary="运营费用收费统计",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"type","year"},
   *  				@OA\Property(property="year",type="int",description="2021,默认为本年度"),
   *        @OA\Property(property="month",type="int",description="月份"),
   *        @OA\Property(property="start_ymd",type="str",description="开始日期"),
   *        @OA\Property(property="end_ymd",type="str",description="结束日期"),
   * 				@OA\Property(property="type",type="int",description="1所有 2 租金3管理费 4 其他"),
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
  public function billStat(Request $request)
  {

    if ($request->start_ymd) {
      $startYmd = $request->start_ymd;
    }
    if ($request->end_ymd) {
      $endYmd = $request->end_ymd;
    }

    // if (!$request->year) {
    //   $request->year =  date('Y');
    //   $startYmd = date($request->year . '-01-01');
    //   $endYmd = date($request->year . '-12-t');
    // }
    if ($request->year) {
      if ($request->month) {
        $startYmd = date($request->year . "-" . $request->month . '-01');
        $endYmd = date($request->year . "-" . $request->month . '-t');
      } else {
        $startYmd = date($request->year . '-01-01');
        $endYmd = date($request->year . '-12-t');
      }
    }
    if (!$startYmd || !$endYmd) {
      $endYmd = date('Y-m-01');
      $endYmd = date('Y-m-t');
    }
    DB::enableQueryLog();
    $select = 'ifnull(sum(amount-discount_amount),0.00) amt ,
              ifnull(sum(discount_amount),0.00) discountAmt,
              ifnull(sum(receive_amount),0.00) receiveAmt';

    $subQuery = $this->billService->billDetailModel()
      ->whereBetween('charge_date', [$startYmd, $endYmd])
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $q->where('type', 1);
      });
    // 费用

    $feeTypes = [
      'total' => null,
      'rental' => AppEnum::rentFeeType,
      'manager' => AppEnum::managerFeeType,
      'other' => [AppEnum::rentFeeType, AppEnum::managerFeeType],
    ];

    $stats = [];
    foreach ($feeTypes as $key => $feeType) {
      $query = clone $subQuery;
      if ($key === 'other') {
        $stats[$key] = $query->whereNotIn('fee_type', $feeType)->selectRaw($select)->first();
      } else if ($key === 'total') {
        $stats[$key] = $query->selectRaw($select)->first();
      } else {
        $stats[$key] = $query->where('fee_type', $feeType)->selectRaw($select)->first();
      }
    }
    $reAmt = $received = $unReceived = [];
    foreach ($stats as $key => $stat) {
      $reAmt[$key . 'Amt'] = $stat['amt'];
      $received[$key . 'Amt'] = $stat['receiveAmt'];
      $unReceived[$key . 'Amt'] = $stat['amt'] - $stat['receiveAmt'];
    }

    $overdueSelect = 'ifnull(sum(amount-discount_amount-receive_amount),0.00) totalAmt,
                ifnull(sum(case when fee_type = 101 then amount-discount_amount-receive_amount end),0.00) rentalAmt,
                ifnull(sum(case when fee_type = 102 then amount-discount_amount-receive_amount end),0.00) managerAmt,
                ifnull(sum(case when fee_type not in (101,102) then amount-discount_amount-receive_amount end),0.00) otherAmt';
    $overdue = $this->billService->billDetailModel()
      ->selectRaw($overdueSelect)
      ->where(function ($q) use ($request, $endYmd) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $q->where('charge_date', '<=', $endYmd);
        $q->where('type', 1);
        $q->where('status', AppEnum::feeStatusUnReceive);
      })
      ->first();

    $data = array(
      'reAmt'      => $reAmt,
      'received'   => $received,
      'unReceived' => $unReceived,
      'yuqi'       => $overdue,
    );
    return $this->success($data);
  }




  /**
   * @OA\Post(
   *     path="/api/operation/stat/bill/month/report",
   *     tags={"费用统计"},
   *     summary="运营费用收费统计",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"type","year"},
   *  				@OA\Property(property="year",type="int",description="2021,默认为本年度"),
   *        @OA\Property(property="proj_ids",type="list",description="项目id集合"),
   * 				@OA\Property(property="type",type="int",description="1所有 2 租金3管理费 4 其他"),
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

  public function monthlyStat(Request $request)
  {

    if ($request->year) {
      $year = $request->year;
    } else {
      $year = date('Y');
    }
    $map = array();

    $startYmd = date($year . '-01-01');
    $endYmd = date($year . '-12-t');
    $billService  = new TenantBillService;
    $select = 'ifnull(sum(amount-discount_amount),0.00) amt ,
              ifnull(sum(discount_amount),0.00) discountAmt,
              ifnull(sum(receive_amount),0.00) receiveAmt,
              DATE_FORMAt(charge_date,"%Y-%m") ym';
    DB::enableQueryLog();

    $stat = $billService->billDetailModel()
      ->selectRaw($select)
      ->whereBetween('charge_date', [$startYmd, $endYmd])
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        if ($request->type == 2) {
          $q->where('fee_type', AppEnum::rentFeeType);
        } else if ($request->type == 3) {
          $q->where('fee_type', AppEnum::managerFeeType);
        } else if ($request->type == 4) {
          $q->where('fee_type', '!=', [AppEnum::rentFeeType, AppEnum::managerFeeType]);
          // $q->whereDoesntHave('feetype', function ($q) {
          //   $q->where('type', '!=', 2);
          // });
        }
      })
      ->whereType(1)
      ->groupBy('ym')->orderBy('ym')
      ->get()->toArray();
    $statYm = array();
    foreach ($stat as $value) {
      $statYm[$value['ym']] = [
        'ym'          => $value['ym'],
        'amt'         => floatval($value['amt']),
        'discountAmt' => floatval($value['discountAmt']),
        'receiveAmt'  => floatval($value['receiveAmt'])
      ];
    }

    $statNew = array();
    for ($month = 0; $month < 12; $month++) {
      $ym = getNextMonth($startYmd, $month);
      $emptyMonth = array(
        'ym' => $ym,
        'amt' => '0.00',
        'discountAmt' => '0.00',
        'receiveAmt'  => '0.00'
      );
      $statNew[] = $statYm[$ym] ?? $emptyMonth;
    }
    return $this->success($statNew);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/stat/charge/month/report",
   *     tags={"费用统计"},
   *     summary="运营收支统计",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"type","year"},
   *  				@OA\Property(property="year",type="int",description="2021,默认为本年度"),
   *        @OA\Property(property="proj_ids",type="list",description="项目id集合"),
   * 				@OA\Property(property="type",type="int",description="1收费、2支出"),
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
  public function chargeStat(Request $request)
  {

    if ($request->year) {
      $year = $request->year;
    } else {
      $year = date('Y');
    }
    $map['audit_status'] = 1;
    if ($request->type) {
      $map['type'] = $request->type;
    }
    $map = array();
    $startYmd = date($year . '-01-01');
    $endYmd = date($year . '-12-t');
    // DB::enableQueryLog();
    $chargeService  = new ChargeService;
    $select = 'ifnull(sum(case when type = 1 then amount end),0.00) chargeAmt ,
              ifnull(sum(case when type = 2 then amount end),0.00) payAmt,
              DATE_FORMAt(charge_date,"%Y-%m") ym';

    $chargeStat = $chargeService->model()->selectRaw($select)
      ->where($map)
      ->whereBetween('charge_date', [$startYmd, $endYmd])
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
      })
      ->groupBy('ym')
      ->orderBy('ym')
      ->get()->toArray();

    $chargeStatYm = array();
    foreach ($chargeStat as $key => $value) {
      $chargeStatYm[$value['ym']] = $value;
    }

    $statNew = array();
    for ($month  = 0; $month < 12; $month++) {
      $ym = getNextMonth($startYmd, $month);
      $emptyMonth = array(
        'ym' => $ym,
        'chargeAmt' => 0.00,
        'payAmt' => 0.00
      );
      $statNew[] = $chargeStatYm[$ym] ?? $emptyMonth;
    }
    return $this->success($statNew);
  }


  /**
   * @OA\Post(
   *     path="/api/operation/stat/bill/year/report",
   *     tags={"应收统计"},
   *     summary="应收统计",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"type","year"},
   *  				@OA\Property(property="year",type="int",description="2021,默认为本年度"),
   *        @OA\Property(property="proj_ids",type="list",description="项目id集合"),
   * 				@OA\Property(property="fee_types",type="list",description="费用类型"),
   *        @OA\Property(property="tenant_name",type="str",description="租户名称"),
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

  public function bill_year_stat(Request $request)
  {

    $validatedData = $request->validate([
      // 'year' => 'required', // 0 所有 1 根据租户id生成
      'proj_ids' => 'required|array',
      // 'bill_month' => 'required|String',
      // 'bill_day' => 'required|between:1,31',
      'fee_types' => 'array',

    ]);
    DB::enableQueryLog();
    $request->year = $request->year ?? date('Y');
    $billService  = new TenantBillService;
    $monthlySummaries = $billService->billDetailModel()->select(
      'tenant_id',
      'tenant_name',
      DB::raw('sum(amount - discount_amount) as amount'),
      DB::raw('sum(receive_amount) as receiveAmt'),
      DB::raw('(sum(amount - discount_amount) - sum(receive_amount)) as unreceiveAmt'),
      DB::raw('date_format(charge_date, "m_%c") as ym')
    )->where(function ($q) use ($request) {
      $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
      $request->year && $q->whereYear('charge_date', $request->year);
      $request->tenant_name && $q->where('tenant_name', 'like', '%' . $request->tenant_name . "%");
      $request->tenant_id && $q->whereIn('tenant_id', getTenantIdsByPrimary($request->tenant_id));
      $request->fee_types && $q->whereIn('fee_type', $request->fee_types);
      $q->whereType(1);
    })
      ->groupBy('tenant_id', 'ym')
      ->orderBy('tenant_id')
      ->orderBy('ym')
      ->get();
    // return $monthlySummaries;
    $formattedData = [];

    // Create a template for all months
    $monthsTemplate = array_fill_keys(
      array_map(function ($month) {
        return 'm_' . $month;
      }, range(1, 12)),
      [
        'amount' => 0.00,
        'receive_amount' => 0.00,
        'unreceive_amount' => 0.00,
      ]
    );

    // Create a template for all tenants
    if (!isset($formattedData['total'])) {
      $formattedData['total'] = [
        'tenant_id' => '0',
        'tenant_name' => '总计',
        'total_amt' => 0.00,
        'total_receive_amt' => 0.00,
        'total_unreceive_amt' => 0.00,
        'year' => $request->year,
      ] + $monthsTemplate;
    }
    foreach ($monthlySummaries as $summary) {
      $tenantId        = $summary->tenant_id;
      $tenantName      = $summary->tenant_name;
      $ym              = $summary->ym;
      $amount          = $summary->amount;
      $receiveAmount   = $summary->receiveAmt;
      $unreceiveAmount = $summary->unreceiveAmt;


      // Create an entry for the tenant if not exists
      if (!isset($formattedData[$tenantId])) {
        $formattedData[$tenantId] = [
          'tenant_id'           => $tenantId,
          'tenant_name'         => $tenantName,
          'total_amt'           => 0.00,
          'total_receive_amt'   => 0.00,
          'total_unreceive_amt' => 0.00,
          'year'                => $request->year,
        ] + $monthsTemplate;
      }

      // Add data to the corresponding month
      $formattedData[$tenantId][$ym] = [
        'amount'           => floatval($amount),
        'receive_amount'   => floatval($receiveAmount),
        'unreceive_amount' => floatval($unreceiveAmount),
      ];
      $formattedData['total']['total_amt']           += $amount;
      $formattedData['total']['total_receive_amt']   += $receiveAmount;
      $formattedData['total']['total_unreceive_amt'] += $unreceiveAmount;
      $formattedData['total'][$ym] = array(
        'amount'           => $formattedData['total'][$ym]['amount'] + $amount,
        'receive_amount'   => $formattedData['total'][$ym]['receive_amount'] + $receiveAmount,
        'unreceive_amount' => $formattedData['total'][$ym]['unreceive_amount'] +  $unreceiveAmount,
      );

      // Update total amounts
      // 
      $formattedData[$tenantId]['total_amt'] += $amount;
      $formattedData[$tenantId]['total_receive_amt'] += $receiveAmount;
      $formattedData[$tenantId]['total_unreceive_amt'] += $unreceiveAmount;
    }

    $allTenant = $formattedData['total'];
    unset($formattedData['total']);
    $DA['data'] = array_values($formattedData);
    // 把总计数据 push 到数组最后一行
    $DA['data'][] = $allTenant;
    // 判断是不是本年度
    $monthData = array();
    if ($request->year == date('Y')) {
      $month = date('n', strtotime(nowYmd()));
      $monthData = $allTenant['m_' . $month];
    }

    // 
    $DA['total'] = array(
      ['title' => "本月总金额", "amount"  => $monthData['amount'] ?? 0.00],
      ['title' => "本月已收金额", "amount" => $monthData['receive_amount'] ?? 0.00],
      ['title' => "本月未收金额", "amount" => $monthData['unreceive_amount'] ?? 0.00],
      ["title" => "本年总金额", "amount"   => $allTenant['total_amt']],
      ["title" => "本年已收金额", "amount" => $allTenant['total_receive_amt']],
      ["title" => "本年未收金额", "amount" => $allTenant['total_unreceive_amt']]
    );
    return $this->success($DA);
  }
}
