<?php

namespace App\Api\Controllers\Stat;

use JWTAuth;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Services\Bill\TenantBillService;
use App\Enums\AppEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillStatController extends BaseController
{
  function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
  }

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
    Log::error("startymd" . $startYmd . $endYmd);
    $billService  = new TenantBillService;
    $select = 'ifnull(sum(amount-discount_amount),"0.00") amt ,
              ifnull(sum(discount_amount),0.00) discountAmt,
              ifnull(sum(receive_amount),0.00) receiveAmt';
    $totalStat = $billService->billDetailModel()
      ->selectRaw($select)
      ->whereBetween('charge_date', [$startYmd, $endYmd])
      ->first();
    $rentalStat = $billService->billDetailModel()
      ->selectRaw($select)
      ->whereBetween('charge_date', [$startYmd, $endYmd])
      ->where('fee_type', AppEnum::rentFeeType)
      ->first();
    $managerStat = $billService->billDetailModel()
      ->selectRaw($select)
      ->whereBetween('charge_date', [$startYmd, $endYmd])
      ->where('fee_type', AppEnum::managerFeeType)
      ->first();
    $otherStat = $billService->billDetailModel()
      ->selectRaw($select)
      ->whereBetween('charge_date', [$startYmd, $endYmd])
      ->whereNotIn('fee_type', [AppEnum::rentFeeType, AppEnum::managerFeeType])
      ->whereDoesntHave('feetype', function ($q) {
        $q->where('type', '!=', 2);
      })
      ->first();

    $yuqiOtherStat = $billService->billDetailModel()
      ->selectRaw('sum(amount-discount_amount-receive_amount) otherAmt')
      ->where('charge_date', '<', getPreYmd(date('Y-m-t'), 1))
      ->whereNotIn('fee_type', [AppEnum::rentFeeType, AppEnum::managerFeeType])
      ->whereDoesntHave('feetype', function ($q) {
        $q->where('type', '!=', 2);
      })
      ->where('status', 0)->first();
    DB::enableQueryLog();
    $yuqiSelect = 'sum(amount-discount_amount-receive_amount) totalAmt,
                    sum(case when fee_type = 101 then amount-discount_amount-receive_amount end) rentalAmt,
                    sum(case when fee_type = 102 then amount-discount_amount-receive_amount end) manageAmt';
    $yuqiStat = $billService->billDetailModel()
      ->selectRaw($yuqiSelect)
      ->where('charge_date', '<', getPreYmd(date('Y-m-t'), 1))
      ->where('status', 0)
      ->first();
    // return response()->json(DB::getQueryLog());
    $reAmt = array(
      'totalAmt' => $totalStat['amt'],
      'rentalAmt' => $rentalStat['amt'],
      'managerAmt' => $managerStat['amt'],
      'otherAmt' => $otherStat['amt']
    );
    $received = array(
      'totalAmt' => $totalStat['receiveAmt'],
      'rentalAmt' => $rentalStat['receiveAmt'],
      'managerAmt' => $managerStat['receiveAmt'],
      'otherAmt' => $otherStat['receiveAmt']
    );
    $unReceived = array(
      'totalAmt' => numFormat($totalStat['amt'] - $totalStat['receiveAmt']),
      'rentalAmt' => numFormat($rentalStat['amt'] - $rentalStat['receiveAmt']),
      'managerAmt' => numFormat($managerStat['amt'] - $managerStat['receiveAmt']),
      'otherAmt' => numFormat($otherStat['amt'] - $otherStat['receiveAmt'])
    );
    $yuqiStat['otherAmt'] = numFormat($yuqiOtherStat['otherAmt']);
    $data = array(
      'reAmt' => $reAmt,
      'received' => $received,
      'unReceived' => $unReceived,
      'yuqi' => $yuqiStat,
    );
    return $this->success($data);
  }


  /**
   * @OA\Post(
   *     path="/api/operation/stat/bight",
   *     tags={"费用统计"},
   *     summary="运营费用收费统计",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"type","year"},
   *  				@OA\Property(property="year",type="int",description="2021,默认为本年度"),
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

  public function bightStat(Request $request)
  {

    if ($request->year) {
      $year = $request->year;
    } else {
      $year = date('Y');
    }
    $startYmd = date($year . '-01-01');
    $endYmd = date($year . '-12-t');
    $billService  = new TenantBillService;
    $select = 'ifnull(sum(amount-discount_amount),"0.00") amt ,
    ifnull(sum(discount_amount),0.00) discountAmt,
    ifnull(sum(receive_amount),0.00) receiveAmt,
    DATE_FORMAt(charge_date,"%Y-%m") ym';
    DB::enableQueryLog();

    $stat = $billService->billDetailModel()
      ->selectRaw($select)
      ->whereBetween('charge_date', [$startYmd, $endYmd])
      ->where(function ($q) use ($request) {
        if ($request->type == 1) {
        } else if ($request->type == 2) {
          $q->where('fee_type', AppEnum::rentFeeType);
        } else if ($request->type == 3) {
          $q->where('fee_type', AppEnum::managerFeeType);
        } else if ($request->type == 4) {
          $q->where('fee_type', '!=', [AppEnum::rentFeeType, AppEnum::managerFeeType]);
          $q->whereDoesntHave('feetype', function ($q) {
            $q->where('type', '!=', 2);
          });
        }
      })
      ->groupBy('ym')->orderBy('ym')
      ->get()->toArray();
    $month = 0;
    $statNew = array();
    while ($month < 12) {
      // $v = 
      foreach ($stat as $k => &$v) {
        if ($v['ym'] == getNextMonth($startYmd, $month)) {
          $statNew[$month] = $v;
          $month++;
          break;
        }
      }
      $statNew[$month]['ym'] = getNextMonth($startYmd, $month);
      $statNew[$month]['amt'] = 0.00;
      $statNew[$month]['discountAmt'] = 0.00;
      $statNew[$month]['receiveAmt']  = 0.00;
      $month++;
    }
    return $this->success($statNew);
  }
}
