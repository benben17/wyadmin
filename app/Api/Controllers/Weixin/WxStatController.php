<?php

namespace App\Api\Controllers\Weixin;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Models\Building as BuildingModel;
use App\Api\Services\Common\DictServices;
use App\Api\Models\Contract\Contract as ContractModel;
use App\Api\Models\Contract\ContractBill as ContractBillModel;
use App\Api\Models\Tenant\ExtraInfo as TenantExtraInfo;
use App\Api\Models\Tenant\Follow;
use App\Api\Services\CustomerService;

/**
 * 微信招商app 首页统计
 * 
 */
class WxStatController extends BaseController
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
     * @OA\Post(
     *     path="/api/wxapp/customer/stat",
     *     tags={"微信首页"},
     *     summary="微信首页，客户信息统计",
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
    public function customerStat(Request $request)
    {
        $today = strtotime(date('Y-m-d'));
        $weekday = date('w') == 0 ? 7 : date('w');
        $Monday = $today - ($weekday - 1) * 3600 * 24;
        $sunday = date('Y-m-d', $Monday + 7 * 24 * 3600 - 1);
        $monday = date('Y-m-d', $Monday);
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $tenant = $this->customerService->tenantModel();


        /** 统计当日 本周 本月新增客户数 */
        DB::enableQueryLog();
        $map['proj_id'] = $request->proj_id;
        $map['belong_uid'] = $this->uid;

        // 统计
        $followCount = $tenant->where($map)->whereHas('follow')->count();
        $lossCount = $tenant->where($map)->whereYear('created_at', date('Y'))->where('state', '流失客户')->count();
        $dealCount = $tenant->where($map)->whereHas('contract')->count();
        $data['first']['followCount'] = $followCount;
        $data['first']['lossCount'] = $lossCount;
        $data['first']['dealCount'] = $dealCount;
        // return response()->json(DB::getQueryLog());
        $todayCount = $tenant->where($map)->whereDate('created_at', nowYmd())->count();
        $weekCount = $tenant->where($map)->whereBetween('created_at', [$monday, $sunday])->count();
        $monthCount = $tenant->where($map)->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $data['cus']['today'] = $todayCount;
        $data['cus']['week'] = $weekCount;
        $data['cus']['month'] = $monthCount;




        // 统计跟进信息
        $where['proj_id'] = $request->proj_id;
        $where['c_uid'] = $this->uid;
        $f_today = Follow::where($where)->whereDate('created_at', nowYmd())->count();
        $f_week = Follow::where($where)->whereBetween('created_at', [$monday, $sunday])->count();
        $f_month = Follow::where($where)->whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $data['follow']['today'] = $f_today;
        $data['follow']['week'] = $f_week;
        $data['follow']['month'] = $f_month;


        $unfollow1 = $tenant->where($map)->whereDoesntHave('follow', function ($q) {
            $q->whereBetween('created_at', [getPreYmdByDay(nowYmd(), 3), getPreYmdByDay(nowYmd(), 7)]);
        })->count();
        $unfollow2 = $tenant->where($map)->whereDoesntHave('follow', function ($q) {
            $q->whereBetween('created_at', [getPreYmdByDay(nowYmd(), 8), getPreYmdByDay(nowYmd(), 16)]);
        })->count();
        $unfollow3 = $tenant->where($map)->whereDoesntHave('follow', function ($q) {
            $q->whereBetween('created_at', [getPreYmdByDay(nowYmd(), 16), getPreYmd(nowYmd(), 3)]);
        })->count();
        $data['unfollow']['three'] = $unfollow1;
        $data['unfollow']['week']  = $unfollow2;
        $data['unfollow']['month'] = $unfollow3;
        // return response()->json(DB::getQueryLog());
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
        $thisMonth = date('Y-m-01', time());
        $startDate = getPreYmd($thisMonth, 11);

        $endDate = getNextYmd($thisMonth, 1);
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
        $DA['start_date'] = '2020-01-01';
        $DA['end_date'] = '2020-12-31';
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
}
