<?php

namespace App\Api\Controllers\Dashboard;

use Svg\Tag\Rect;
use App\Models\Area;
use App\Enums\AppEnum;
use App\Api\Models\Project;
use Illuminate\Http\Request;
use App\Api\Models\BuildingRoom;
use App\Api\Models\Tenant\Follow;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Support\Facades\DB;
use App\Api\Models\Common\Maintain;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Tenant\ExtraInfo;
use App\Api\Models\Contract\Contract;
use App\Api\Controllers\BaseController;
use App\Api\Models\Operation\WorkOrder;
use App\Api\Models\Bill\TenantBillDetail;
use App\Api\Models\Operation\YhWorkOrder;
use App\Api\Models\Equipment\EquipmentMaintain;

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
    DB::enableQueryLog();
    $currMonthReceive = TenantBillDetail::selectRaw('ifnull(sum(amount-discount_amount),"0.00") amt')
      ->whereIn('proj_id', $request->proj_ids)
      ->whereBetween('charge_date', [$startDate, $endDate])
      ->whereType(1)
      ->first();
    // DB::enableQueryLog();
    $currYearReceive = TenantBillDetail::selectRaw('ifnull(sum(amount-discount_amount),"0.00") amt')
      ->whereIn('proj_id', $request->proj_ids)
      ->whereType(1)
      ->whereYear('charge_date',  date('Y'))->first();

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

  /**
   * @OA\Get(
   *      path="/api/dashboard/tenant",
   *      operationId="tenant",
   *      tags={"Dashboard"},
   *      summary="租客数据",
   *      description="租客数据",
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
  public function tenantStat(Request $request)
  {
    // $request->validate([
    //   'proj_ids' => 'required|array',

    // ]);

    // 租客数据
    DB::enableQueryLog();
    $tenantInfo = Tenant::selectRaw('count(*) as total,
    sum(worker_num) worker_num,
    sum(cpc_number) cpc_number')
      ->where(function ($query) use ($request) {
        return $this->subQuery($query, $request);
      })
      ->first();
    // return DB::getQueryLog();
    $tenantWorker = [
      'total'      => $tenantInfo['total'],
      'worker_num' => $tenantInfo['worker_num'],
      'cpc_number' => $tenantInfo['cpc_number'],
      'cpc_rate'   => $tenantInfo['worker_num'] == 0 ? 0 : round($tenantInfo['cpc_number'] / $tenantInfo['worker_num'] * 100, 2),
    ];

    // return $this->success($tenantWorker);
    $data['tenant_worker'] = $tenantWorker;
    DB::enableQueryLog();
    // 租客级别数据
    $tenantLevel = Tenant::selectRaw('count(*) as total,level')
      ->where(function ($query) use ($request) {
        return $this->subQuery($query, $request);
      })
      ->groupBy('level')
      ->get()->toArray();
    // return DB::getQueryLog();
    // return $tenantLevel;
    $tenantLevelNull = 0;
    $totalTenants = $tenantLevel ? array_sum(array_column($tenantLevel, 'total')) : 0;
    // Log::info($totalTenants);
    $data['tenant_level'] =  array_map(function ($item) use ($totalTenants, &$tenantLevelNull) {
      if (empty($item['level'])) {
        $tenantLevelNull += $item['total'];
        return null;
      }
      $item['percentage'] = ($item['total'] == 0 ||  $totalTenants == 0) ? 0 : ($item['total'] / $totalTenants) * 100;
      if ($item['percentage'] <= 0.3) {
        $tenantLevelNull += $item['total'];
        return null;
      }
      return $item;
    }, $tenantLevel);
    // return 
    $data['tenant_level'][] = [
      'total' => $tenantLevelNull,
      'level' => '未知',
      'percentage' => $tenantLevelNull == 0 ? 0 : ($tenantLevelNull / $totalTenants)  * 100
    ];
    $data['tenant_level'] = array_values(array_filter($data['tenant_level']));
    // 租客行业数据

    $tenantIndustry = Tenant::selectRaw('count(*) as total,industry')
      ->where(function ($query) use ($request) {
        return $this->subQuery($query, $request);
      })
      ->groupBy('industry')
      ->get()->toArray();
    $tenantIndustryNull = 0;
    $data['tenant_industry'] = array_map(function ($item) use ($totalTenants, &$tenantIndustryNull) {
      if (empty($item['industry']) || !$item['industry']) {
        $tenantIndustryNull += $item['total'];
        return null;
      }
      $item['percentage'] = ($item['total'] == 0 || $totalTenants == 0) ? 0 : ($item['total'] / $totalTenants) * 100;
      return $item;
    }, $tenantIndustry);
    $data['tenant_industry'][] = ['total' => $tenantIndustryNull, 'industry' => '未知', 'percentage' => ($tenantIndustryNull / $totalTenants) * 100];
    $data['tenant_industry'] = array_values(array_filter($data['tenant_industry']));

    // 租户维护
    $tenantMaintain = Maintain::selectRaw('count(*) as total,maintain_type')
      ->where(function ($query) use ($request) {
        $request->proj_ids && $query->whereIn('proj_id', $request->proj_ids);
        $query->where('parent_type', AppEnum::Tenant);
      })
      ->groupBy('maintain_type')->get()->toArray();
    $maintainSum = array_sum(array_column($tenantMaintain, 'total'));
    foreach ($tenantMaintain as &$item) {
      $item['percentage'] = ($maintainSum == 0 || $item['total'] == 0) ? 0 : round($maintainSum / $item['total'] * 100, 2);
    }
    $data['tenant_maintain'] = $tenantMaintain;

    return $this->success($data);
  }

  private function subQuery($query, Request $request)
  {
    $query->where('type', 2);
    $request->proj_ids && $query->whereIn('proj_id', $request->proj_ids);
    return $query;
  }


  /**
   * @OA\Get(
   *      path="/api/dashboard/project",
   *      operationId="project",
   *      tags={"Dashboard"},
   *      summary="项目数据",
   *      description="项目数据",
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
  public function project(Request $request)
  {

    $projects = Project::selectRaw('count(*) as total,proj_city_id,proj_province_id')
      ->groupBy('proj_city_id')->get()->toArray();
    // $total = array_sum(array_column($data, 'total'));
    // $data[] = ['total' => $total, 'proj_city_id' => 0];

    foreach ($projects as &$item) {
      $city = Area::find($item['proj_city_id']);
      $province = Area::find($item['proj_province_id']);
      if ($city) {
        $item['province_name']  = $province->name;
        $item['city_code'] = $city->code;
        $item['city_name'] = $city->name;
      } else {
        $item['province_name']  = '未知';
        $item['city_code'] = '';
        $item['city_name'] = '未知';
      }
    }
    $data['yh_work_count'] = YhWorkOrder::where(function ($query) use ($request) {
      $request->proj_ids && $query->whereIn('proj_id', $request->proj_ids);
    })->count();

    $data['work_count'] = WorkOrder::where(function ($query) use ($request) {
      $request->proj_ids && $query->whereIn('proj_id', $request->proj_ids);
    })->count();
    $data['maintain_count'] = EquipmentMaintain::where(function ($query) use ($request) {
      $request->proj_ids && $query->whereIn('proj_id', $request->proj_ids);
    })->count();
    $data['project'] = $projects;
    return $this->success($data);
  }

  /**
   * @OA\Get(
   *      path="/api/dashboard/customer",
   *      operationId="customer",
   *      tags={"Dashboard"},
   *      summary="客户数据",
   *      description="客户数据",
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
  public function customer(Request $request)
  {
    DB::enableQueryLog();
    $tenantIndustry = Tenant::selectRaw('count(*) as total,industry')
      ->where(function ($query) use ($request) {
        // $query->where('type', 1);
        $request->proj_ids && $query->whereIn('proj_id', $request->proj_ids);
      })
      ->groupBy('industry')
      ->get()->toArray();

    $tenantIndustryNull = 0;
    $totalTenants = $tenantIndustry ? array_sum(array_column($tenantIndustry, 'total')) : 0;
    $data['tenant_industry'] = array_map(function ($item) use ($totalTenants, &$tenantIndustryNull) {
      if (empty($item['industry']) || !$item['industry']) {
        $tenantIndustryNull += $item['total'];
        return null;
      }
      $item['percentage'] = ($item['total'] / $totalTenants) * 100;
      return $item;
    }, $tenantIndustry);

    $areaRequirement = ExtraInfo::selectRaw('count(*) as total,demand_area')
      ->whereHas('tenant', function ($query) use ($request) {
        $request->proj_ids && $query->whereIn('proj_id', $request->proj_ids);
      })
      ->where('demand_area', '!=', "")
      ->groupBy('demand_area')
      ->get()->toArray();
    $data['area_requirement'] = $areaRequirement;
    $visit =  Follow::selectRaw('count(*) as total,follow_type as follow_type')
      ->whereHas('tenant', function ($query) use ($request) {
        $request->proj_ids && $query->whereIn('proj_id', $request->proj_ids);
      })
      ->groupBy('follow_type')
      ->get()->toArray();

    $data['visit'] = $visit;

    $tenantSource = Tenant::selectRaw('count(*) as total, channel_id')
      ->where(function ($query) use ($request) {
        if ($request->proj_ids) {
          $query->whereIn('proj_id', $request->proj_ids);
        }
      })
      ->groupBy('channel_id') // Group by channel_id
      ->get();
    $tenantSourceCount = array_sum(array_column($tenantSource->toArray(), 'total'));
    // Eager load channel data after grouping
    $tenantSource->load('channel:id,channel_type');
    // Group by channel_type using Collection methods
    $groupedResults = $tenantSource->groupBy('channel.channel_type')->toArray();
    $tenantFrom = [];
    foreach ($groupedResults as $key => $value) {
      $channelType = $value[0]['channel']['channel_type'] ?? "未知";
      $total = $value[0]['total'] ?? 0;
      $tenantFrom[] = [
        'channel_type' => $channelType,
        'total' => $total,
        'percentage' => $tenantSourceCount == 0 ? 0 : round(($total / $tenantSourceCount) * 100, 2)
      ];
    }
    $data['tenant_source'] = $tenantFrom;
    return $this->success($data);
  }

  /**
   * @OA\Get(
   *      path="/api/dashboard/workOrderData",
   *      operationId="workOrderData",
   *      tags={"Dashboard"},
   *      summary="工单数据",
   *      description="工单数据",
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
  public function workOrderData(Request $request)
  {
    $workOrder = WorkOrder::where(function ($query) use ($request) {
      $request->proj_ids && $query->whereIn('proj_id', $request->proj_ids);
    })
      ->where('status', '>=', AppEnum::workorderProcess)
      ->orderBy('open_time', 'desc')
      ->limit(20)->get()->toArray();

    foreach ($workOrder as &$item) {
      $item['tenant_name'] = getTenantNameById($item['tenant_id']);
    }

    $equipmentMaintain = EquipmentMaintain::where(function ($query) use ($request) {
      $request->proj_ids && $query->whereIn('proj_id', $request->proj_ids);
    })
      ->orderBy('created_at')->limit(20)->get()->toArray();

    $yhWorkOrder = YhWorkOrder::where(function ($query) use ($request) {
      $request->proj_ids && $query->whereIn('proj_id', $request->proj_ids);
    })
      ->where('status', '>=', AppEnum::workorderProcess)
      ->orderBy('open_time', 'desc')
      ->limit(20)->get()->toArray();
    foreach ($yhWorkOrder as &$item) {
      $item['tenant_name'] = getTenantNameById($item['tenant_id']);
    }


    $data['work_order'] = $workOrder;
    $data['equipment_maintain'] = $equipmentMaintain;
    $data['yh_work_order'] = $yhWorkOrder;
    return $this->success($data);
  }

  /**
   * @OA\Get(
   *      path="/api/dashboard/tenantMaintain",
   *      operationId="tenantMaintain",
   *      tags={"Dashboard"},
   *      summary="租客维修数据",
   *      description="租客维修数据",
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
  public function tenantMaintain(Request $request)
  {

    $tenantMaintain = Maintain::selectRaw('count(*) as total,maintain_type')
      ->where(function ($query) use ($request) {
        $request->proj_ids && $query->whereIn('proj_id', $request->proj_ids);
        $query->where('parent_type', AppEnum::Tenant);
      })
      ->groupBy('maintain_type')->get()->toArray();


    $maintainSum = array_sum(array_column($tenantMaintain, 'total'));

    foreach ($tenantMaintain as &$item) {
      $item['percentage'] = $maintainSum == 0 ? 0 : round($maintainSum / $item['total'] * 100, 2);
    }

    return $this->success($tenantMaintain);
  }
}
