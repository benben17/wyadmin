<?php

namespace App\Api\Controllers\Dashboard;

use App\Enums\AppEnum;
use Illuminate\Http\Request;
use App\Api\Models\BuildingRoom;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Support\Facades\DB;
use App\Api\Models\Contract\Contract;
use App\Api\Controllers\BaseController;
use App\Api\Models\Bill\TenantBillDetail;

class DashboardController extends BaseController
{

  public function __construct()
  {
    parent::__construct();
  }

  /**
   * @OA\Get(
   *      path="/api/dashboard/index",
   *      operationId="dashboard",
   *      tags={"Dashboard"},
   *      summary="首页数据",
   *      description="首页数据",
   *      @OA\Parameter(
   *          name="proj_ids",
   *          description="项目id",
   *          required=true,
   *          in="query",
   *          @OA\Schema(
   *              type="array",
   *              @OA\Items(
   *                  type="integer"
   *              )
   *          )
   *      ),
   *      @OA\Response(
   *          response=200,
   *          description="successful operation",
   *          @OA\JsonContent()
   *       ),
   *      @OA\Response(response=400, description="Bad request"),
   *      @OA\Response(response=401, description="Unauthorized"),
   *      @OA\Response(response=403, description="Forbidden"),
   *      @OA\Response(response=404, description="Resource Not Found"),
   *      @OA\Response(response=500, description="Internal Server Error")
   * )
   */
  public function index(Request $request)
  {
    $request->validate([
      'proj_ids' => 'required|array',

    ]);

    $startDate = date('Y-m-01'); // 当月的第一天
    $endDate = date('Y-m-t'); // 当月的最后一天

    $rooms = BuildingRoom::selectRaw('count(*) as total_rooms, 
    sum(case when room_state = 1 then 1 else 0 end) as free_rooms')
      ->whereHas('building', function ($query) use ($request) {
        $query->whereIn('proj_id', $request->proj_ids);
      })
      ->first();

    $customerCount = Tenant::whereIn('type', [1, 3])->whereIn('proj_id', $request->proj_ids)->count();
    $TenantCount = Tenant::where('type', 2)->whereIn('proj_id', $request->proj_ids)->count();

    $contractCount = Contract::whereIn('proj_id', $request->proj_ids)->where('contract_state', AppEnum::contractExecute)->count();
    $currMonthExpireContractCount = Contract::whereIn('proj_id', $request->proj_ids)
      ->where('contract_state', AppEnum::contractExecute)
      ->whereBetween('end_date', [$startDate, $endDate])->count();

    $currMonthReceive = TenantBillDetail::selectRaw('ifnull(sum(amount-discount_amount),"0.00") amt')
      // ->whereIn('proj_id', $request->proj_ids)
      ->whereBetween('bill_date', [$startDate, $endDate])
      ->whereType(1)
      ->first();
    // DB::enableQueryLog();
    $currYearReceive = TenantBillDetail::selectRaw('ifnull(sum(amount-discount_amount),"0.00") amt')
      ->whereIn('proj_id', $request->proj_ids)
      ->whereType(1)
      ->whereYear('bill_date',  date('Y'))->first();

    return $this->success([
      'free_room'                    => $rooms['free_rooms'] ?? 0,
      'total_room'                   => $rooms['total_rooms'],
      'free_room_rate'               => $rooms['total_rooms'] == 0 ? 0 : round($rooms['free_rooms'] / $rooms['total_rooms'] * 100, 2),
      'customerCount'                => $customerCount,
      'TenantCount'                  => $TenantCount,
      'contractCount'                => $contractCount,
      'currMonthExpireContractCount' => $currMonthExpireContractCount,
      'currMonthReceive'             => $currMonthReceive['amt'],
      'currYearReceive'              => $currYearReceive['amt'],
    ]);
  }
}
