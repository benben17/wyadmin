<?php

namespace App\Api\Controllers\Venue;

use App\Api\Controllers\BaseController;
use App\Api\Services\Pay\WxPayService;
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
	public function __construct()
	{
		parent::__construct();
		$this->WxPayService = new WxPayService;
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
	 *          property="venue_province_id",
	 *          type="int",
	 *          description="省份ID"
	 *       ),
	 *       @OA\Property(
	 *          property="venue_city_id",
	 *          type="int",
	 *          description="城市ID"
	 *       ),
	 *       @OA\Property(
	 *          property="venue_name",
	 *          type="String",
	 *          description="场馆名称"
	 *       ),
	 *       @OA\Property(
	 *          property="proj_id",
	 *          type="int",
	 *          description="项目ID"
	 *       )
	 *     ),
	 *       example={
	 *              "venue_province_id": "","venue_city_id":""
	 *           }
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	// public function index(Request $request)
	// {
	// 	$pagesize = $request->input('pagesize');
	// 	if (!$pagesize || $pagesize < 1) {
	// 		$pagesize = config('per_size');
	// 	} elseif ($pagesize == '-1') {
	// 		$pagesize = config('export_rows');
	// 	}
	// 	$map = array();

	// 	if ($request->input('proj_id')) {
	// 		$map['proj_id'] = $request->input('proj_id');
	// 	}
	// 	// 排序字段
	// 	if ($request->input('orderBy')) {
	// 		$orderBy = $request->input('orderBy');
	// 	} else {
	// 		$orderBy = 'created_at';
	// 	}
	// 	// 排序方式desc 倒叙 asc 正序
	// 	if ($request->input('order')) {
	// 		$orderByAsc = $request->input('order');
	// 	} else {
	// 		$orderByAsc = 'desc';
	// 	}
	// 	DB::enableQueryLog();

	// 	$data = $this->venueServices->VenueModel()
	// 		->where($map)
	// 		->where(function ($q) use ($request) {
	// 			$request->venue_name && $q->where('venue_name', 'like', '%' . $request->venue_name . '%');
	// 			$request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
	// 		})
	// 		->with('project:id,proj_name')
	// 		->withCount('venueBook')
	// 		->withCount(['venueSettle as settle_amount' => function ($q) {
	// 			$q->select(DB::Raw('ifnull(sum(amount),"0.00")'));
	// 		}])
	// 		->orderBy($orderBy, $orderByAsc)
	// 		->paginate($pagesize)->toArray();
	// 	// return response()->json(DB::getQueryLog());

	// 	$data = $this->handleBackData($data);
	// 	return $this->success($data);
	// }

	/**
	 * @OA\Post(
	 *     path="/api/activity/pay",
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
		$param['out_trade_no'] = getTradeNo();
		$param['amount'] = '0.01';
		$param['description'] = '测试活动报名';
		$param['openid'] = "o2Hy06_8zLxwGDsYpfmfYmhhM6CI";
		$res = $this->WxPayService->wxJsapiPay($param, $param['openid']);

		return $this->success($res);
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
	 *              "venueId": 11
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
		// DB::enableQueryLog();
		// $data = $this->venueServices->getVenueById($request->id)->toArray();
		// $data['venue_book'] = $this->venueServices->getVenueBook($request->id);
		// return response()->json(DB::getQueryLog());

		return $this->success();
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
}
