<?php

namespace App\Api\Controllers\Business;

use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Api\Models\Business\CusClue;
use App\Api\Controllers\BaseController;
use App\Api\Services\Common\DictServices;
use App\Api\Models\Building as BuildingModel;
use App\Api\Services\Business\CustomerService;
use App\Api\Services\Contract\ContractService;
use App\Api\Models\Contract\Contract as ContractModel;
use App\Api\Models\Tenant\ExtraInfo as TenantExtraInfo;

/**
 * @OA\Tag(
 *     name="招商统计",
 *     description="招商统计管理"
 * )
 */
class StatController extends BaseController
{

    private $customerService;
    private $contractService;
    public function __construct()
    {
        parent::__construct();
        $this->customerService = new CustomerService;
        $this->contractService = new ContractService;
    }

    /**
     * @OA\Post(
     *    path="/api/business/stat/dashboard",
     *   tags={"招商统计"},
     *  summary="统计面板数据",
     * @OA\RequestBody(
     *   @OA\MediaType(
     *      mediaType="application/json",
     *   @OA\Schema(
     *      schema="UserModel",
     *      required={"proj_ids"},
     *      @OA\Property(property="proj_ids",type="list",description="项目ID"),
     *      @OA\Property(property="year",type="int",description="年份"),
     *  ),
     *   example={"proj_ids":"[]"}
     *  )
     * ),
     * @OA\Response(
     *    response=200,
     *  description=""
     * )
     * )
     */
    public function dashboard(Request $request)
    {
        $validatedData = $request->validate([
            'proj_ids' => 'required|array',
        ], [
            'proj_ids.required' => '项目ID不允许为空！',
            'proj_ids.array' => '项目ID必须是数组！'
        ]);
        $DA = $request->toArray();

        $thisYear = $DA['year'] ? $DA['year'] . '-01-01' : date('Y-01-01', time());
        DB::enableQueryLog();
        $data = BuildingModel::select('proj_id')
            ->where(function ($q) use ($DA) {
                $q->whereIn('proj_id', $DA['proj_ids']);
            })
            //统计所有房间
            ->withCount(['buildRoom as manager_room_count' => function ($q) {
                $q->where('room_type', 1);
            }])
            // 统计空闲房间
            ->withCount(['buildRoom as free_room_count' => function ($q) {
                $q->where('room_state', 1);
                $q->where('room_type', 1);
            }])
            //统计管理房间面积
            ->withCount(['buildRoom as manager_area' => function ($q) {
                $q->select(DB::raw("sum(room_area)"));
                $q->where('room_type', 1);
            }])
            //统计空闲房间面积
            ->withCount(['buildRoom as free_area' => function ($q) {
                $q->select(DB::raw("sum(room_area)"));
                $q->where('room_state', 1);
                $q->where('room_type', 1);
            }])
            ->get()->toArray();
        // return response()->json(DB::getQueryLog());
        $room = array(
            'manager_room_count' => 0,
            'free_room_count' => 0,
            'manager_area' => 0.00,
            'free_area' => 0.00
        );

        foreach ($data as $v) {
            $room['manager_room_count'] += $v['manager_room_count'];
            $room['free_room_count']    += $v['free_room_count'];
            $room['manager_area']       += $v['manager_area'];
            $room['free_area']          += $v['free_area'];
        }

        $room['rental_rate'] =  numFormat(($room['free_area'] / $room['manager_area']) * 100);
        //统计客户
        DB::enableQueryLog();
        $customer = $this->customerService->tenantModel()
            ->select('state', DB::Raw('count(*) as cus_count'))
            ->where(function ($q) use ($request, $thisYear) {
                $q->whereIn('proj_id', $request->proj_ids);
                $q->where('created_at', '>=', $thisYear);
            })
            ->groupBy('state')->get();

        // return response()->json(DB::getQueryLog());
        $channel = $this->customerService->tenantModel()
            ->select('channel_id', 'state', DB::Raw('count(*) as cus_count'))
            ->with('channel:id,channel_type')
            ->where(function ($q) use ($request, $thisYear) {
                $q->whereIn('proj_id', $request->proj_ids);
                $q->where('created_at', '>=', $thisYear);
            })
            ->groupBy('channel_id', 'state')->get();


        $BA['room'] = $room;
        $BA['customer'] = $customer;
        $BA['channel'] = $channel;
        return $this->success($BA);
    }


    /**
     * @OA\Post(
     *     path="/api/business/stat/customerstat",
     *     tags={"招商统计"},
     *     summary="按照时间段、项目ID 统计客户数据",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={},
     *        @OA\Property(property="start_date",type="date",description="开始时间"),
     *        @OA\Property(property="end_date",type="date",description="结束时间"),
     *        @OA\Property(property="proj_id",type="int",description="项目ID")
     *     ),
     *       example={"start_date":"","end_date":""}
     *     )
     *    ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function getCustomerStat(Request $request)
    {

        // $validatedData = $request->validate([
        //     'proj_ids' => 'required|array',
        // ]);
        //如果没有传值，默认统计最近一年的数据
        $BA = $request->toArray();
        if (!isset($BA['start_date']) && !isset($BA['end_date'])) {
            $BA['end_date']     = date('Y-m-d', time());
            $BA['start_date']   = getPreYmd($BA['end_date'], 12);
        }
        // $BA['end_date']     = getNextYmdByDay($BA['end_date'], 1);

        $BA['end_date'] = getNextYmdByDay($BA['end_date'], 1);
        // DB::enableQueryLog();
        // 来电数量

        $clueStatPie = CusClue::selectRaw('clue_type,count(*) cus_count')
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('clue_time', [$BA['start_date'], $BA['end_date']]);
            })
            ->groupBy('clue_type')->get()->toArray();
        $cluePieTotal = 0;

        foreach ($clueStatPie as $k1 => &$v1) {
            $v1['clue_type_label'] = getDictName($v1['clue_type']);
            // $cluePieTotal += $v['cus_count'];
        }
        // return $clueStatPie;
        // return response()->json(DB::getQueryLog());
        /** 客户来源分析  */
        $cusBySource = $this->customerService->tenantModel()
            ->selectRaw('count(*) as cus_count,source_type')
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('created_at', [$BA['start_date'], $BA['end_date']]);
                !empty($BA['proj_ids']) &&  $q->whereIn('proj_id', $BA['proj_ids']);
            })
            ->where('parent_id', 0)
            ->groupBy('source_type')->get();
        foreach ($cusBySource as $k2 => &$v2) {
            $v2['source_type_label'] = getDictName($v2['source_type']);
        }
        /** 统计每种状态下的客户  */
        // DB::enableQueryLog();
        $customerByState = $this->customerService->tenantModel()
            ->where('parent_id', 0)
            ->selectRaw('state, count(*) as cus_count')
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('created_at', [$BA['start_date'], $BA['end_date']]);
                if (!empty($BA['proj_ids'])) {
                    $BA['proj_ids'] &&  $q->whereIn('proj_id', $BA['proj_ids']);
                }
            })
            ->groupBy('state')->get();

        // return response()->json(DB::getQueryLog());
        // 按照行业进行客户统计
        DB::enableQueryLog();
        $customerByIndustry = $this->customerService->tenantModel()->select('industry', DB::Raw('count(*) as cus_count'))
            ->where('parent_id', 0)
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('created_at', [$BA['start_date'], $BA['end_date']]);
                !empty($BA['proj_ids']) &&  $q->whereIn('proj_id', $BA['proj_ids']);
            })
            ->groupBy('industry')->get();

        $customerCount = $this->customerService->tenantModel()
            ->where('parent_id', 0)
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('created_at', [$BA['start_date'], $BA['end_date']]);
                !empty($BA['proj_ids']) &&  $q->whereIn('proj_id', $BA['proj_ids']);
            })
            ->count();
        // return response()->json(DB::getQueryLog());
        // 按照渠道统计统计每种渠道带来的客户
        $customerByChannel = $this->customerService->tenantModel()->select('channel_id', DB::Raw('count(*) as cus_count'))
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('created_at', [$BA['start_date'], $BA['end_date']]);
                !empty($BA['proj_ids']) &&  $q->whereIn('proj_id', $BA['proj_ids']);
                $q->where('parent_id', 0);
            })
            ->with('channel:id,channel_name')
            ->groupBy('channel_id')->get();

        // 按照渠道统计成交的客户
        $customerByChannelDeal = $this->customerService->tenantModel()->select('channel_id', DB::Raw('count(*) as cus_count'))
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('created_at', [$BA['start_date'], $BA['end_date']]);
                $q->where('state', '成交客户');
                !empty($BA['proj_ids']) &&  $q->whereIn('proj_id', $BA['proj_ids']);
                $q->where('parent_id', '>', 0);
            })
            ->with('channel:id,channel_name')
            ->groupBy('channel_id')->get();

        // 统计客户需求面积
        $demandStat = TenantExtraInfo::select(DB::Raw('count(*) as count'), 'demand_area')
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('created_at', [$BA['start_date'], $BA['end_date']]);
            })
            ->whereHas('tenant', function ($q) use ($BA) {
                !empty($BA['proj_ids']) &&  $q->whereIn('proj_id', $BA['proj_ids']);
                $q->where('parent_id', 0);
            })

            ->groupBy('demand_area')->get();

        $dict = new DictServices;  // 根据ID 获取字典信息
        $demandArea = $dict->getByKey(getCompanyIds($this->uid), 'demand_area');
        $Stat = array();
        foreach ($demandArea as $k3 => $v3) {
            foreach ($demandStat as $ks => $vs) {
                if ($v3['value'] == $vs['demand_area']) {
                    $Stat[$k3] = $vs;
                    break;
                } else {
                    $Stat[$k3]['demand_area'] = $v3['value'];
                    $Stat[$k3]['count'] = 0;
                }
            }
        }

        foreach ($customerByIndustry as $k3 => &$v3) {
            $v3['cus_rate'] = numFormat($v3['cus_count'] / $customerCount * 100);
        }
        // return $Stat;
        $data['cusBySource'] =  $cusBySource->toArray();
        $data['customerCluepie'] =  $clueStatPie;
        $data['customerByState'] = $customerByState->toArray();
        $data['customerByIndustry'] = $customerByIndustry;
        $data['customerByChannel'] = $customerByChannel->toArray();
        $data['customerByChannelDeal'] = $customerByChannelDeal->toArray();
        $data['demandArea'] = $Stat;

        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/business/stat/contract",
     *     tags={"招商统计"},
     *     summary="按月统计合同数、成交客户数、成交均价",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={},
     *       @OA\Property(property="proj_id",type="int",description="项目ID")
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
    public function getContractStat(Request $request)
    {
        $validatedData = $request->validate([
            // 'start_date' => 'required|date',
            // 'end_date' => 'required|date',
            'proj_ids' => 'required|array',
        ]);

        $year = $request->year ?? date('Y');
        $startDate = $request->year . '-01-01';
        // return $startDate.'+++++++'.$endDate;
        // 如果是月单价（rental_price_type 2 ）乘以12除以365 获取日金额
        // DB::enableQueryLog();
        $contract = $this->contractService->model()
            ->select(DB::Raw('count(*) contract_total,
            count(distinct(tenant_id)) cus_total,
            sum(case rental_price_type when 1 then rental_price*sign_area else rental_price*sign_area*12/365 end) amount,
            sum(sign_area) area, DATE_FORMAT(sign_date,"%Y-%m") as ym'))
            ->where('contract_state', 2)
            ->where(function ($q) use ($request) {
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->room_type && $q->where('room_type', $request->room_type);
            })
            ->whereYear('sign_date', $year)
            ->groupBy('ym')->get();
        // return response()->json(DB::getQueryLog());
        $i = 0;
        $stat = array();
        while ($i < 12) {
            // Log::info(getNextMonth($startDate,$i)."----".$i);
            foreach ($contract as $k => $v) {
                if ($v['ym'] == getNextMonth($startDate, $i)) {
                    $stat[$i]['ym'] = $v['ym'];
                    $stat[$i]['contract_total'] = $v['contract_total'];
                    $stat[$i]['cus_total'] = $v['cus_total'];
                    $stat[$i]['area'] = numFormat($v['area']);
                    $stat[$i]['avg_price'] = numFormat($v['amount'] / $v['area']);
                    $i++;
                }
            }

            $stat[$i]['ym'] = getNextMonth($startDate, $i);
            $stat[$i]['contract_total'] = 0;
            $stat[$i]['cus_total'] = 0;
            $stat[$i]['area'] = 0.00;
            $stat[$i]['avg_price'] = 0.00;
            $i++;
        }
        return $this->success($stat);
    }

    /**
     * @OA\Post(
     *     path="/api/business/stat/staffkpi",
     *     tags={"招商统计"},
     *     summary="按月统计招商人员",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={},
     *       @OA\Property(property="proj_id",type="int",description="项目ID")
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

    public function getStaffKpi(Request $request)
    {
        // $validatedData = $request->validate([
        //     'start_date'=> 'required|date',
        //     'end_date'=> 'required|date',
        //     // 'proj_name'=> 'required',
        // ]);
        $DA = $request->toArray();
        if ($request->start_date && $request->end_date) {
            $DA['start_date'] = date('Y-01-01', $request->start_date);
            $DA['end_date'] = date('Y-12-t', strtotime($DA['start_date']));
        } else {
            $thisMonth = date('Y-m-01', time());
            $DA['start_date'] = getPreYmd($thisMonth, 11);
            $DA['end_date'] = getNextYmd($thisMonth, 1);
        }
        $dict = new DictServices;
        $cusState = $dict->getByKeyGroupBy('0,' . $this->company_id, 'cus_state');
        $StateList = str2Array($cusState['value']);
        // Log::error(json_encode($cusState['value']));
        DB::enableQueryLog();
        $belongPerson = $this->customerService->tenantModel()
            ->select(DB::Raw('group_concat(distinct belong_person) as user,count(*) total'))
            ->where(function ($q) use ($DA) {
                $q->whereBetween('created_at', [$DA['start_date'], $DA['end_date']]);
                isset($DA['proj_ids']) && $q->whereIn('proj_id', $DA['proj_ids']);
            })
            ->groupBy('belong_uid')
            ->get();
        // return response()->json(DB::getQueryLog());

        foreach ($belongPerson as $k => &$v) {
            $v['followCount'] = 0;
            foreach ($StateList as $ks => $vs) {
                $cusCount = $this->customerService->tenantModel()
                    ->where('state', $vs)
                    ->where('belong_person', $v['user'])
                    ->where(function ($q) use ($DA) {
                        $q->whereBetween('created_at', [$DA['start_date'], $DA['end_date']]);
                        isset($DA['proj_ids']) && $q->whereIn('proj_id', $DA['proj_ids']);
                    })
                    ->count();

                switch ($vs) {
                    case '成交客户':
                        $dealArea = ContractModel::where('room_type', 1)
                            ->where('belong_person', $v['user'])
                            ->whereBetween('sign_date', [$DA['start_date'], $DA['end_date']])
                            ->when(isset($DA['proj_ids']), function ($q) use ($DA) {
                                return $q->whereIn('proj_id', $DA['proj_ids']);
                            })
                            ->sum('sign_area');

                        $v['dealArea'] = $dealArea;
                        $v['deal'] = $cusCount;
                        break;
                    case '潜在客户':
                        $v['potential'] = $cusCount;
                        break;
                    case '初次接触':
                        $v['first'] = $cusCount;
                        break;
                    case '意向客户':
                        $v['intention'] = $cusCount;
                        break;
                    case '流失客户':
                        $v['lose'] = $cusCount;
                        break;
                }
                // 总计
                $v['followCount'] += $v['total'];
            }
        }
        return $this->success($belongPerson);
    }


    /**
     * @OA\Post(
     *     path="/api/business/stat/forecast/income",
     *     tags={"招商统计"},
     *     summary="统计2年的收入预测（只统计租金和管理费不统计押金）",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={},
     *       @OA\Property(property="proj_id",type="int",description="项目ID"),
     *       @OA\Property(property="room_type",type="int",description="1房源2工位")
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
    public function incomeForecast(Request $request)
    {
        // $validatedData = $request->validate([
        //     'room_type' => 'required|int|in:1,2',
        // ]);
        if ($request->start_date && $request->end_date) {
            $startDate = date('Y-01-01', $request->start_date);
            // $endDate = getNextYmd(date('Y-m-t', strtotime($startDate)), 24);
        } else {
            $startDate = date('Y-m-01', time());
        }
        $endDate = getNextYmd(date('Y-m-t',  strtotime($startDate)), 24);

        DB::enableQueryLog();
        $res = $this->contractService->contractBillModel()
            ->select(DB::Raw('DATE_FORMAT(charge_date,"%Y-%m") as ym,
            count(distinct(tenant_id)) cus_count,
            sum(case when fee_type = 101  then amount else 0.00 end) rental_amount,           
            sum(case when  fee_type = 102  then amount else 0.00 end) manager_amount'))
            ->whereBetween('charge_date', [$startDate, $endDate])
            ->whereHas('contract', function ($q)  use ($request) {
                $q->where('contract_state', 2);
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->room_type && $q->where('room_type', $request->room_type);
            })
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();
        // return response()->json(DB::getQueryLog());

        $stat = [];
        $nextMonth = $startDate;

        for ($i = 0; $i < 24; $i++) {
            $nextMonth = getNextMonth($startDate, $i);
            $found = false;

            foreach ($res as $k => $v) {
                if ($v['ym'] == $nextMonth) {
                    $stat[$i] = $v;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $stat[$i] = [
                    'ym' => $nextMonth,
                    'cus_count' => 0,
                    'rental_amount' => 0.00,
                    'manager_amount' => 0.00
                ];
            }
        }

        return $this->success($stat);
    }

    /**
     * @OA\Post(
     *     path="/api/business/stat/month/receive",
     *     tags={"招商统计"},
     *     summary="统计当月应收的各种费用（租金，租金押金管理费押金 管理费）",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"year"},
     *     @OA\Property(property="year",type="int",description="年份")
     *    ),
     *       example={"year":2020}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function getMonthReceive(Request $request)
    {

        $year = $request->year ?? date('Y');
        $data = $this->contractService->contractBillModel()
            ->select(DB::Raw('sum(amount) amount,type,count(distinct tenant_id) cus_count'))
            ->whereHas('contract', function ($q)  use ($request, $year) {
                $q->where('contract_state', 2);
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->room_type && $q->where('room_type', $request->room_type);
                $q->whereYear('charge_date', $year);
            })
            ->groupBy('type')->get();
        return $this->success($data);
    }
}
