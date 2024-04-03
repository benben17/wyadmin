<?php

namespace App\Api\Controllers\Weixin;

use JWTAuth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Api\Models\Tenant\Follow;
use Illuminate\Support\Facades\DB;
use App\Api\Controllers\BaseController;
use App\Api\Services\Business\CustomerService;
use App\Api\Services\Business\WxCustomerService;

/**
 * 微信招商app 首页统计
 * 
 */
class WxStatController extends BaseController
{
    private $customerService;
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
     *     tags={"微信招商"},
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
     *       example={"start_date":"","end_date":"","proj_id":1}
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

        $tenant = $this->customerService->tenantModel();
        $today = Carbon::today();
        $map['proj_id'] = $request->proj_id;
        $map['belong_uid'] = $this->uid;

        $wxCustomerService = new WxCustomerService;
        $data['first'] = $wxCustomerService->getFirstData($tenant, $map);
        $data['cus'] = $wxCustomerService->getCusData($tenant, $map, $today);
        $data['follow'] = $wxCustomerService->getFollowData($request, $today, $this->uid);
        $data['unfollow'] = $wxCustomerService->getUnfollowData($tenant, $map, $today);

        return $this->success($data);
    }


    /** 
     * @OA\Post(
     *     path="/api/wxapp/customer/list",
     *     tags={"微信招商"},
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
            'proj_ids' => 'required',
        ]);

        $map = array();

        if ($request->channel_id && $request->channel_id > 0) {
            $map['channel_id'] = $request->channel_id;
        }

        $request->type = [1, 3];
        DB::enableQueryLog();
        $resultQuery = $this->customerService->tenantModel()
            ->where($map)
            ->where(function ($q) use ($request) {
                $request->type && $q->whereIn('type', $request->type);
                $request->name && $q->where('name', 'like', '%' . $request->name . '%');
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
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
            ->withCount('follow');
        $data = $this->pageData($resultQuery, $request);

        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/wxapp/customer/follow/list",
     *     tags={"微信招商"},
     *     summary="客户跟进列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize"},
     *       @OA\Property(property="pagesize",type="int",description="每页行数"),
     *       @OA\Property(property="follow_type",type="int",description="跟进类型"),
     *       @OA\Property(property="start_time",type="string",description="开始时间"),
     *       @OA\Property(property="end_time",type="string",description="结束时间"),
     *       @OA\Property(property="proj_ids",type="list",description="项目ID"),
     *     ),
     *       example={
     *          "pagesize":10,"proj_ids":"[]","follow_type":1,"start_time":"2020-01-01","end_time":"2020-01-01"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function followList(Request $request)
    {


        $map = array();
        if ($request->follow_type) {
            $map['follow_type'] = $request->follow_type;
        }
        // 排序字段
        if (!$request->input('orderBy')) {
            $request->orderBy = 'follow_time';
        }


        DB::enableQueryLog();
        $result = $this->customerService->followModel()->where($map)
            ->where(function ($q) use ($request) {
                $request->start_time && $q->where('follow_time', '>=', $request->start_time);
                $request->end_time && $q->where('follow_time', '<=', $request->end_time);
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
            });

        // return response()->json(DB::getQueryLog());
        $data = $this->pageData($result, $request);
        return $this->success($data);
    }
}
