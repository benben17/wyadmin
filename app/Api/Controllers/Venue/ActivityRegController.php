<?php

namespace App\Api\Controllers\Venue;

use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Pay\WxPayService;
use App\Api\Controllers\BaseController;
use App\Api\Services\Venue\ActivityService;
use App\Api\Services\Venue\ActivityRegService;

/**
 *  活动报名
 */
class ActivityRegController extends BaseController
{

	private $wxPayService;
	private $activityRegService;
	public function __construct()
	{
		parent::__construct();
		$this->wxPayService = new WxPayService;
		$this->activityRegService = new ActivityRegService;
	}

	/**
	 * @OA\Post(
	 *     path="/api/activity/reg/list",
	 *     tags={"活动报名"},
	 *     summary="活动报名列表",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={},
	 *       @OA\Property(
	 *          property="venue_id",
	 *          type="int",
	 *          description="场馆ID"
	 *       ),
	 *       @OA\Property(
	 *          property="activity_id",
	 *          type="int",
	 *          description="活动id"
	 *       ),
	 *       @OA\Property(
	 *          property="venue_name",
	 *          type="String",
	 *          description="场馆名称"
	 *       ),
	 *       @OA\Property(
	 *          property="proj_ids",
	 *          type="list",
	 *          description="项目ID"
	 *       )
	 *     ),
	 *       example={
	 *             "venue_id": 1,"activity_id": 1,"venue_name": "","proj_ids":"[1,2]"
	 *           }
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function index(Request $request)
	{

		$map = array();
		DB::enableQueryLog();
		if ($request->venue_id) {
			$ma['venue_id'] = $request->venue_id;
		}
		if ($request->pay_status) {
			$ma['pay_status'] = $request->pay_status;
		}
		if ($request->user_phone) {
			$ma['user_phone'] = $request->user_phone;
		}
		$query = $this->activityRegService->model()->where($map)
			->where(function ($q) use ($request) {
				$request->activity_title && $q->where('activity_title', 'like', '%' . $request->activity_title . '%');
				$request->venue_name && $q->where('venue_name', 'like', '%' . $request->venue_name . '%');
				$request->user_name && $q->where('user_name', 'like', '%' . $request->user_name . '%');
				$request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
			});
		$data = $this->pageData($query, $request);
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/activity/reg/show",
	 *     tags={"活动报名"},
	 *     summary="根据场馆id获取信息",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"id"},
	 *       @OA\Property(
	 *          property="id",
	 *          type="int",
	 *          description="场馆Id"
	 *       )
	 *     ),
	 *       example={
	 *              "id": 11
	 *           }
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function show(Request $request)
	{
		$validatedData = $request->validate([
			'id' => 'required|int|gt:0',
		]);
		DB::enableQueryLog();
		$data = $this->activityRegService->model()->find($request->id);

		// $data['venue_book'] = $this->venueServices->getVenueBook($request->id);
		// return response()->json(DB::getQueryLog());

		return $this->success($data);
	}


	/**
	 * @OA\Post(
	 *     path="/api/activity/reg/pay",
	 *     tags={"活动报名"},
	 *     summary="活动报名支付",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",required={"activityId"},
	 *      @OA\Property(property="activityId",type="int",description="活动报名id")
	 *     ),
	 *       example={"activityId": ""}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function regPay(Request $request)
	{

		$validatedData = $request->validate([

			// 'venue_addr' => 'required|String',
			// 'proj_id' => 'required|min:1',
		]);
		$param = $request->toArray();
		$trade_no = date("ymdHis") . mt_rand(1000, 9999);
		$param['out_trade_no'] = $trade_no;
		$param['amount'] = '1';
		$param['description'] = '测试活动报名';
		$param['openid'] = "o2Hy06_8zLxwGDsYpfmfYmhhM6CI";
		$res = $this->wxPayService->wxJsApiPay($param, $param['openid']);

		return $this->success($res);
	}

	/**
	 * @OA\Post(
	 *     path="/api/activity/reg/refund",
	 *     tags={"活动报名"},
	 *     summary="活动报名退款",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",required={"activityId"},
	 *      @OA\Property(property="activityId",type="int",description="活动报名id")
	 *     ),
	 *       example={"activityId": ""}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function regRefund(Request $request)
	{

		$validatedData = $request->validate([

			// 'venue_addr' => 'required|String',
			// 'proj_id' => 'required|min:1',
		]);
		$param = $request->toArray();
		$trade_no = date("ymdHis") . mt_rand(1000, 9999);
		$param['out_trade_no'] = $trade_no;
		$param['amount'] = '1';
		$param['description'] = '测试活动报名';
		$param['openid'] = "o2Hy06_8zLxwGDsYpfmfYmhhM6CI";
		$res = $this->wxPayService->wxRefund($param, $param['openid']);

		return $this->success($res);
	}
}
