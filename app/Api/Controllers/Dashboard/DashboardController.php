<?php

namespace App\Api\Controllers\Dashboard;

use App\Enums\AppEnum;
use Illuminate\Http\Request;
use App\Api\Models\BuildingRoom;
use App\Api\Models\Tenant\Tenant;
use App\Api\Models\Contract\Contract;
use App\Api\Controllers\BaseController;
use App\Api\Models\Bill\TenantBillDetail;

class DashboardController extends BaseController
{

  public function __construct()
  {
    parent::__construct();
  }


  public function index(Request $request)
  {
    $request->validate([
      'proj_ids' => 'required|array',

    ]);


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
      ->whereRaw('date_format(end_date, "%Y-%m") = date_format(now(), "%Y-%m")')->count();

    $startDate = date('Y-m-01'); // 当月的第一天
    $endDate = date('Y-m-t'); // 当月的最后一天

    $currMonthReceive = TenantBillDetail::whereIn('proj_id', $request->proj_ids)
      ->whereBetween('bill_date', [$startDate, $endDate])
      ->sum('amount');
    $currYearReceive = TenantBillDetail::whereIn('proj_id', $request->proj_ids)->whereYear('bill_date',  date('Y'))->sum('amount');

    return $this->success([
      'free_room'                    => $rooms['free_rooms'] ?? 0,
      'total_room'                   => $rooms['total_rooms'],
      'free_room_rate'               => $rooms['total_rooms'] == 0 ? 0 : round($rooms['free_rooms'] / $rooms['total_rooms'] * 100, 2),
      'customerCount'                => $customerCount,
      'TenantCount'                  => $TenantCount,
      'contractCount'                => $contractCount,
      'currMonthExpireContractCount' => $currMonthExpireContractCount,
      'currMonthReceive'             => $currMonthReceive,
      'currYearReceive'              => $currYearReceive,
    ]);
  }
}
