<?php

namespace App\Api\Controllers\Venue;

use JWTAuth;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Pay\WxPayService;
use App\Api\Controllers\BaseController;
use App\Api\Services\Venue\ActivityService;
use App\Api\Services\Venue\ActivityRegService;

/**
 *  活动
 */
class ActivityController extends BaseController
{

	private $activityService;
	public function __construct()
	{
		parent::__construct();
		$this->activityService = new ActivityService;
	}
	/**
	 * @OA\Post(
	 *     path="/api/activity/list",
	 *     tags={"活动"},
	 *     summary="活动报名列表",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={},
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
	 *             "venue_id": 1,"venue_name": "","proj_ids":"[1,2]"
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
		$query = $this->activityService->model()->where($map)
			->where(function ($q) use ($request) {
				$request->activity_title && $q->where('activity_title', 'like', '%' . $request->activity_title . '%');
				$request->venue_name && $q->where('venue_name', 'like', '%' . $request->venue_name . '%');
				$request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
			});
		$data = $this->pageData($query, $request);
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/activity/show",
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
		$data = $this->activityService->model()->find($request->id);

		// $data['venue_book'] = $this->venueServices->getVenueBook($request->id);
		// return response()->json(DB::getQueryLog());

		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/activity/save",
	 *     tags={"活动"},
	 *     summary="活动添加/编辑",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",required={"activityId"},
	 *      @OA\Property(property="id",type="int",description="活动报名id")
	 *     ),
	 *       example={"id": ""}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function save(Request $request)
	{

		$validatedData = $request->validate([
			'proj_id' => 'required|min:1',
			'venue_id' => 'required|min:1',
			'venue_name' => 'required',
			'activity_title' => 'required',
			'activity_desc' => 'required',
			'activity_type' => 'required',
			'start_date' => 'required',
			'end_date' => 'required',
		]);

		$res = $this->activityService->saveActivity($request->toArray(), $this->user);
		return $res ? $this->success("添加成功") : $this->error("添加失败");
	}

	/**
	 * @OA\Post(
	 *     path="/api/activity/delete",
	 *     tags={"活动"},
	 *     summary="活动删除",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",required={"id"},
	 *      @OA\Property(property="id",type="int",description="活动id")
	 *     ),
	 *       example={"id": ""}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function delete(Request $request)
	{
		$validatedData = $request->validate([
			'id' => 'required|int|gt:0',
		]);
		$activity = $this->activityService->model()->find($request->id);
		if (!$activity) {
			return $this->error("活动不存在");
		}
		$startDate   = new DateTime($activity->start_date);
		$endDate     = new DateTime($activity->end_date);
		$currentDate = new DateTime();

		if ($startDate < $currentDate && $endDate > $currentDate) {
			return $this->error("活动已开始，不能删除");
		}

		$res = $activity->delete();
		return $res ? $this->success("【" . $activity->activity_title . "】删除成功")
			: $this->error("删除失败");
	}
}
