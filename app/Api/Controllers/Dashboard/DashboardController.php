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
      ->whereIn('proj_id', $request->proj_ids)
      ->first();

    $customerCount = Tenant::whereIn('type', [1, 3])->whereIn('proj_id', $request->proj_ids)->count();
    $TenantCount = Tenant::where('type', 2)->whereIn('proj_id', $request->proj_ids)->count();

    $contractCount = Contract::whereIn('proj_id', $request->proj_ids)->where('contract_state', AppEnum::contractExecute)->count();
    $currMonthExpireContractCount = Contract::whereIn('proj_id', $request->proj_ids)
      ->where('contract_state', AppEnum::contractExecute)
      ->whereRaw('date_format(end_date, "%Y-%m") = date_format(now(), "%Y-%m")')->count();

    $currMonthRecive = TenantBillDetail::whereIn('proj_id', $request->proj_ids)->where('bill_date', 'like', date('Y-m') . '%')->sum('amount');
    $currYearRecive = TenantBillDetail::whereIn('proj_id', $request->proj_ids)->where('bill_date', 'like', date('Y') . '%')->sum('amount');

    return $this->success([
      'free_room' => $rooms['free_rooms'] ?? 0,
      'total_room' => $rooms['total_rooms'],
      'free_room_rate' => $rooms['total_rooms'] == 0 ? 0 : round($rooms['free_rooms'] / $rooms['total_rooms'] * 100, 2),
      'customerCount' => $customerCount,
      'TenantCount' => $TenantCount,
      'contractCount' => $contractCount,
      'currMonthExpireContractCount' => $currMonthExpireContractCount,
      'currMonthRecive' => $currMonthRecive,
      'currYearRecive' => $currYearRecive,
    ]);
  }
}
