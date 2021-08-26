<?php

namespace App\Api\Controllers\Business;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Models\Building as BuildingModel;
use App\Api\Models\Business\CusClue;
use App\Api\Services\Common\DictServices;
use App\Api\Models\Contract\Contract as ContractModel;
use App\Api\Models\Contract\ContractBill as ContractBillModel;
use App\Api\Models\Tenant\ExtraInfo as TenantExtraInfo;
use App\Api\Models\Tenant\Follow;
use App\Api\Services\CustomerService;
use App\Enums\AppEnum;

/**
 *
 */
class StatController extends BaseController
{


    public function __construct()
    {
        $this->uid  = auth()->payload()->get('sub');
        if (!$this->uid) {
            return $this->error('用户信息错误');
        }
        $this->company_id = getCompanyId($this->uid);
        $this->customerService = new CustomerService;
    }

    /**
     * 招商首页统计信息
     */
    /**
     * [dashboard description]
     * @Author   leezhua
     * @DateTime 2020-06-06
     * @param    Request    $request [description]
     * @return   [type]              [description]
     */
    public function dashboard(Request $request)
    {
        $validatedData = $request->validate([
            'proj_ids' => 'required|array',
        ]);
        $DA = $request->toArray();
        // $projIds = ;
        if (empty($request->projIds)) {
            return $this->error('项目ID不允许为空！');
        }
        $thisYear = date('Y-01-01');
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
        $room['manager_room_count'] = 0;
        $room['free_room_count'] = 0;
        $room['manager_area'] = 0.00;
        $room['free_area'] = 0.00;
        foreach ($data as $k => $v) {
            $room['manager_room_count'] += $v['manager_room_count'];
            $room['free_room_count']     += $v['free_room_count'];
            $room['manager_area']     += $v['manager_area'];
            $room['free_area']         += $v['free_area'];
        }

        $room['rental_rate'] =  sprintf("%01.2f", $room['free_area'] / $room['manager_area'] * 100);
        //统计客户
        DB::enableQueryLog();
        $customer = $this->customerService->tenantModel()->select('state', DB::Raw('count(*) as cus_count'))
            ->where(function ($q) use ($request) {
                $q->whereIn('proj_id', $request->proj_ids);
            })
            ->where('created_at', '>', $thisYear)
            ->groupBy('state')->get();

        // return response()->json(DB::getQueryLog());
        $channel = $this->customerService->tenantModel()->select('channel_id', 'state', DB::Raw('count(*) as cus_count'))
            ->with('channel:id,channel_type')
            ->where(function ($q) use ($request) {
                $q->whereIn('proj_id', $request->proj_ids);
            })
            ->where('created_at', '>', $thisYear)
            ->groupBy('channel_id', 'state')->get();


        $BA['room'] = $room;
        $BA['customer'] = $customer;
        $BA['channel'] = $channel;
        return $this->success($BA);
    }


    /**
     * @OA\Post(
     *     path="/api/business/stat/customerstat",
     *     tags={"统计"},
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
        $BA = $request->toArray();
        // $validatedData = $request->validate([
        //     'proj_ids' => 'required|array',
        // ]);
        //如果没有传值，默认统计最近一年的数据
        if (!isset($BA['start_date']) || !isset($BA['end_date'])) {
            $BA['end_date']     = date('Y-m-d', time());
            $BA['start_date']   = getPreYmd($BA['end_date'], 12);
        }
        // $BA['end_date']     = getNextYmdByDay($BA['end_date'], 1);
        DB::enableQueryLog();

        // 来电数量

        $clueStatPie = CusClue::selectRaw('clue_type,count(*) cus_count')
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('clue_time', [$BA['start_date'], $BA['end_date']]);
            })
            ->groupBy('clue_type')->get()->toArray();
        $cluePieTotal = 0;

        foreach ($clueStatPie as $k => &$v) {
            $v['clue_type_label'] = getDictName($v['clue_type']);
            $cluePieTotal += $v['cus_count'];
        }

        /** 统计每种状态下的客户  */
        $cusBySource = $this->customerService->tenantModel()
            ->selectRaw('count(*) as cus_count,source_type')
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('created_at', [$BA['start_date'], $BA['end_date']]);
                if (!empty($BA['proj_ids'])) {
                    $BA['proj_ids'] &&  $q->whereIn('proj_id', $BA['proj_ids']);
                }
            })
            ->where('parent_id', '>', 0)
            ->groupBy('source_type')->get();
        foreach ($cusBySource as $k => &$v) {
            $v['source_type_label'] = getDictName($v['source_type']);
        }
        // return response()->json(DB::getQueryLog());
        /** 统计每种状态下的客户  */
        $customerByState = $this->customerService->tenantModel()
            ->where('parent_id', '>', 0)
            ->select('state', DB::Raw('count(*) as cus_count'))
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('created_at', [$BA['start_date'], $BA['end_date']]);
                if (!empty($BA['proj_ids'])) {
                    $BA['proj_ids'] &&  $q->whereIn('proj_id', $BA['proj_ids']);
                }
            })
            ->groupBy('state')->get();


        // 按照行业进行客户统计
        DB::enableQueryLog();
        $customerByIndustry = $this->customerService->tenantModel()->select('industry', DB::Raw('count(*) as cus_count'))
            ->where('parent_id', '>', 0)
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('created_at', [$BA['start_date'], $BA['end_date']]);
                $BA['proj_ids'] &&  $q->whereIn('proj_id', $BA['proj_ids']);
            })
            ->groupBy('industry')->get();
        // return response()->json(DB::getQueryLog());
        // 按照渠道统计统计每种渠道带来的客户
        $customerByChannel = $this->customerService->tenantModel()->select('channel_id', DB::Raw('count(*) as cus_count'))
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('created_at', [$BA['start_date'], $BA['end_date']]);
                $BA['proj_ids'] &&  $q->whereIn('proj_id', $BA['proj_ids']);
                $q->where('parent_id', '>', 0);
            })
            ->with('channel:id,channel_name')
            ->groupBy('channel_id')->get();

        // 按照渠道统计成交的客户
        $customerByChannelDeal = $this->customerService->tenantModel()->select('channel_id', DB::Raw('count(*) as cus_count'))
            ->where(function ($q) use ($BA) {
                $q->WhereBetween('created_at', [$BA['start_date'], $BA['end_date']]);
                $q->where('state', '成交客户');
                $BA['proj_ids'] &&  $q->whereIn('proj_id', $BA['proj_ids']);
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
                $BA['proj_ids'] &&  $q->whereIn('proj_id', $BA['proj_ids']);
                $q->where('parent_id', '>', 0);
            })

            ->groupBy('demand_area')->get();

        $dict = new DictServices;  // 根据ID 获取字典信息
        $demandArea = $dict->getByKey(getCompanyIds($this->uid), 'demand_area');
        $Stat = array();
        foreach ($demandArea as $k => $v) {
            foreach ($demandStat as $ks => $vs) {
                if ($v['value'] == $vs['demand_area']) {
                    $Stat[$k] = $vs;
                    break;
                } else {
                    $Stat[$k]['demand_area'] = $v['value'];
                    $Stat[$k]['count'] = 0;
                }
            }
        }

        // return $Stat;
        $data['cusBySource'] =  $cusBySource->toArray();
        $data['customerCluepie'] =  $clueStatPie;
        $data['customerByState'] = $customerByState->toArray();
        $data['customerByIndustry'] = $customerByIndustry->toArray();
        $data['customerByChannel'] = $customerByChannel->toArray();
        $data['customerByChannelDeal'] = $customerByChannelDeal->toArray();
        $data['demandArea'] = $Stat;

        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/business/stat/contract",
     *     tags={"统计"},
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
        // $validatedData = $request->validate([
        //     'start_date'=> 'required|date',
        //     'end_date'=> 'required|date',
        //     // 'proj_id'=> 'array',
        // ]);
        if ($request->start_date && $request->end_date) {
            $startDate = date('Y-01-01', $request->start_date);
            $endDate = date('Y-12-t', strtotime($startDate));
        } else {
            $thisMonth = date('Y-m-01', time());
            $startDate = getPreYmd($thisMonth, 11);
            $endDate = getNextYmd($thisMonth, 1);
        }

        // return $startDate.'+++++++'.$endDate;
        // 如果是月单价（rental_price_type 2 ）乘以12除以365 获取日金额
        $contract = ContractModel::select(DB::Raw('count(*) contract_total,
            count(distinct(tenant_id)) cus_total,
            sum(case rental_price_type when 1 then rental_price*sign_area else rental_price*sign_area*12/365 end) amount,
            sum(sign_area) area, DATE_FORMAT(sign_date,"%Y-%m") as ym'))
            ->where('contract_state', 2)
            ->where(function ($q) use ($request) {
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->room_type && $q->where('room_type', $request->room_type);
            })
            ->where('sign_date', '>=', $startDate)
            ->where('sign_date', '<', $endDate)
            ->groupBy('ym')->get();

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
     *     tags={"统计"},
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
        $State = str2Array($cusState['value']);
        Log::error(json_encode($cusState['value']));
        DB::enableQueryLog();
        $belongPerson = $this->customerService->tenantModel()->select(DB::Raw('group_concat(distinct belong_person) as user,count(*) total'))
            ->where(function ($q) use ($DA) {
                $q->whereBetween('created_at', [$DA['start_date'], $DA['end_date']]);
                isset($DA['proj_ids']) && $q->whereIn('proj_id', $DA['proj_ids']);
            })
            ->groupBy('belong_person')
            ->get()->toArray();
        // return response()->json(DB::getQueryLog());
        $i = 0;
        $stat_cus = array(['name' => '']);

        foreach ($belongPerson as $k => &$v) {
            foreach ($State as $ks => $vs) {
                Log::error($vs . '----' . $v['user']);
                $cusCount = $this->customerService->tenantModel()
                    ->where('state', $vs)
                    ->where('belong_person', $v['user'])
                    ->where(function ($q) use ($DA) {
                        $q->whereBetween('created_at', [$DA['start_date'], $DA['end_date']]);
                        isset($DA['proj_ids']) && $q->whereIn('proj_id', $DA['proj_ids']);
                    })
                    ->count();

                if ($vs == '成交客户') {
                    $area = ContractModel::selectRaw('sum(sign_area) deal_area')
                        ->where('room_type', 1)
                        ->where('belong_person', $v['user'])
                        ->where(function ($q) use ($DA) {
                            $q->whereBetween('sign_date', [$DA['start_date'], $DA['end_date']]);
                            isset($DA['proj_ids']) && $q->whereIn('proj_id', $DA['proj_ids']);
                        })
                        ->first();
                    $v['dealArea']  = $area['deal_area'];
                    $v['deal'] = $cusCount;
                } else if ($vs == '潜在客户') {
                    $v['potential'] = $cusCount;
                } else if ($vs == '初次接触') {
                    $v['first'] = $cusCount;
                } else if ($vs == '意向客户') {
                    $v['intention'] = $cusCount;
                } else if ($vs == '流失客户') {
                    $v['lose'] = $cusCount;
                }
            }
            $v['followCount'] = Follow::whereHas('customer', function ($q) use ($DA, $v) {
                $q->whereBetween('created_at', [$DA['start_date'], $DA['end_date']]);
                isset($DA['proj_ids']) && $q->whereIn('proj_id', $DA['proj_ids']);
                $q->where('belong_person', $v['user']);
            })->count();
        }
        return $this->success($belongPerson);
    }


    /**
     * @OA\Post(
     *     path="/api/business/stat/forecast/income",
     *     tags={"统计"},
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
            $endDate = date('Y-12-t', strtotime($startDate));
        } else {
            $thisMonth = date('Y-m-01', time());
            $startDate = getPreYmd($thisMonth, 11);
            $endDate = getNextYmd($thisMonth, 1);
        }

        DB::enableQueryLog();
        $res = ContractBillModel::whereBetween('charge_date', [$startDate, $endDate])
            ->whereHas('contract', function ($q)  use ($request) {
                $q->where('contract_state', 2);
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->room_type && $q->where('room_type', $request->room_type);
            })
            ->select(DB::Raw('count(distinct(tenant_id)) cus_count,
            sum(case when fee_type = 101  then amount else 0 end) rental_amount,           
            sum(case when  fee_type = 102  then amount else 0 end) manager_amount,
            DATE_FORMAT(charge_date,"%Y-%m") as ym'))
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();
        // return response()->json(DB::getQueryLog());
        // return $res;
        $i = 0;
        $stat = array();

        while ($i < 24) {
            foreach ($res as $k => $v) {
                if ($v['ym'] == getNextMonth($startDate, $i)) {
                    $stat[$i] = $v;
                    $i++;
                }
            }
            $stat[$i]['cus_count'] = 0;
            $stat[$i]['ym'] = getNextMonth($startDate, $i);
            $stat[$i]['rental_amount'] = 0.00;
            $stat[$i]['manager_amount'] = 0.00;
            $i++;
        }
        return $this->success($stat);
    }

    /**
     * @OA\Post(
     *     path="/api/business/stat/month/receive",
     *     tags={"统计"},
     *     summary="统计当月应收的各种费用（租金，租金押金管理费押金 管理费）",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={},
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
    public function getMonthReceive(Request $request)
    {
        if (!$request->year) {
            $request->year = date('Y');
        }
        $data = ContractBillModel::select(DB::Raw('sum(amount) amount ,type
            ,count(distinct cus_id) cus_count'))
            ->whereHas('contract', function ($q)  use ($request) {
                $q->where('contract_state', 2);
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->room_type && $q->where('room_type', $request->room_type);
                $request->year && $q->whereYear('charge_date', $request->year);
            })

            ->groupBy('type')->get();
        return $this->success($data);
    }
}
