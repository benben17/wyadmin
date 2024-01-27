<?php

namespace App\Api\Controllers\Venue;

use App\Api\Controllers\BaseController;
use App\Api\Models\Venue\ActivityReg;
use App\Api\Services\Pay\WxPayService;
use App\Api\Services\Venue\ActivityRegService;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Api\Services\Venue\VenueServices;
use Illuminate\Support\Facades\Log;

/**
 *  场馆管理
 */
class ActivityController extends BaseController
{

	private $WxPayService;
	private $activityRegService;
	public function __construct()
	{
		parent::__construct();
		$this->WxPayService = new WxPayService;
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
	 *              "venue_id": "","proj_ids":""
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
		$pagesize = $request->input('pagesize');
		if (!$pagesize || $pagesize < 1) {
			$pagesize = config('per_size');
		} elseif ($pagesize == '-1') {
			$pagesize = config('export_rows');
		}
		$map = array();


		// 排序字段
		if ($request->input('orderBy')) {
			$orderBy = $request->input('orderBy');
		} else {
			$orderBy = 'created_at';
		}
		// 排序方式desc 倒叙 asc 正序
		if ($request->input('order')) {
			$orderByAsc = $request->input('order');
		} else {
			$orderByAsc = 'desc';
		}
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
		$data = $this->activityRegService->model()->where($map)
			->where(function ($q) use ($request) {
				$request->activity_title && $q->where('activity_title', 'like', '%' . $request->activity_title . '%');
				$request->venue_name && $q->where('venue_name', 'like', '%' . $request->venue_name . '%');
				$request->user_name && $q->where('user_name', 'like', '%' . $request->user_name . '%');
				$request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
			})
			// ->with('project:id,proj_name')
			->orderBy($orderBy, $orderByAsc)
			->paginate($pagesize)->toArray();
		// return response()->json(DB::getQueryLog());

		$data = $this->handleBackData($data);
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
		$data['pay_status_label'] = $this->activityRegService->getStatus($data['pay_status']);
		// $data['venue_book'] = $this->venueServices->getVenueBook($request->id);
		// return response()->json(DB::getQueryLog());

		return $this->success($data);
	}




	public function WxPayNotify(Request $request)
	{
		try {

			// Get headers
			$headers = $request->headers->all();
			$wxSignature = $headers['Wechatpay-Signature'];
			$wxTimestamp = $headers['Wechatpay-Timestamp'];
			$wxpaySerial = $headers['Wechatpay-Serial'];
			$wxpayNonce = $headers['Wechatpay-Nonce'];
			$wxBody = $request->getContent();

			// Log the raw input data from the request
			Log::info('Raw Callback Data: ' . $request->getContent());

			// Handle the payment callback using the WxPayService
			$response = $this->WxPayService->wxPayNotify($wxSignature, $wxTimestamp, $wxpayNonce, $wxBody);

			// Return the appropriate response to WeChat Pay
			return response($request->getContent());
		} catch (\Exception $e) {
			// Log any exceptions that occur during callback processing
			Log::error('Exception during callback processing: ' . $e->getMessage());

			// Return an error response to WeChat Pay
			return response('FAIL', 500);
		}
	}


	/**
	 * @OA\Post(
	 *     path="/api/activity/reg/pay",
	 *     tags={"活动报名支付"},
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
	 * 				 data = "{"prepay_id": "wx130014427185823e9abe73f608c5990000"}"
	 *         description=""
	 *     )
	 * )
	 */
	public function activityPay(Request $request)
	{

		$validatedData = $request->validate([

			// 'venue_addr' => 'required|String',
			// 'proj_id' => 'required|min:1',
		]);
		$param = $request->toArray();


		// $check_venue = $this->venueServices->VenueModel()->where($map)->exists();
		// if ($check_venue) {
		// 	return $this->error($data['venue_name'] . '场馆已存在！');
		// }
		$trade_no = date("ymdHis") . mt_rand(1000, 9999);
		$param['out_trade_no'] = $trade_no;
		$param['amount'] = '1';
		$param['description'] = '测试活动报名';
		$param['openid'] = "o2Hy06_8zLxwGDsYpfmfYmhhM6CI";
		$res = $this->WxPayService->wxJsapiPay($param, $param['openid']);

		return $this->success($res);
	}
}