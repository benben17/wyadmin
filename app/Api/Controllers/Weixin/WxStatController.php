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
     *     summary="微信首页，客户信息统计，统计当前用户的信息",
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
        $validatedData = $request->validate([
            'proj_id' => 'required|gt:0',
        ]);
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
     *     path="/api/wxapp/customer/list",
     *     tags={"客户"},
     *     summary="客户列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize","orderBy","order"},
     *       @OA\Property(property="list_type",type="int",description="// 1 客户列表 2 在租户 3 退租租户")
     *     ),
     *       example={
     *
     *           }
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
        $validatedData = $request->validate([
            'proj_id' => 'required|gt:0',
        ]);
        $pagesize = $request->input('pagesize');
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config('per_size');
        }
        $map = array();

        if ($request->channel_id && $request->channel_id > 0) {
            $map['channel_id'] = $request->channel_id;
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
        $request->type = [1, 3];
        DB::enableQueryLog();
        $result = $this->customerService->tenantModel()
            ->where($map)
            ->where(function ($q) use ($request) {
                $request->type && $q->whereIn('type', $request->type);
                $request->name && $q->where('name', 'like', '%' . $request->name . '%');
                $request->proj_id && $q->where('proj_id', $request->proj_id);
                $request->belong_uid && $q->where('belong_uid', $request->belong_uid);
                $request->room_type && $q->where('room_type', $request->room_type);
                $request->state && $q->where('state', $request->state);
            })
            ->with('contacts')
            ->with('extraInfo')
            ->whereHas('extraInfo', function ($q) use ($request) {
                $request->demand_area && $q->where('demand_area', $request->demand_area);
            })
            // ->with('customerRoom')
            ->withCount('follow')
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();
        $data = $this->handleBackData($result);
        return $this->success($data);
    }
}
