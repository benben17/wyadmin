<?php

namespace App\Api\Controllers\Venue;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Api\Services\Venue\VenueServices;


/**
 *  场馆管理
 */
class VenueBookController extends BaseController
{

	public function __construct()
	{
		$this->uid  = auth()->payload()->get('sub');
		if (!$this->uid) {
			return $this->error('用户信息错误');
		}
		// $this->company_id = getCompanyId($this->uid);
		$this->user = auth('api')->user();
		$this->venueServices = new VenueServices;
	}
	/**
	 * @OA\Post(
	 *     path="/api/venue/book/list",
	 *     tags={"场馆"},
	 *     summary="场馆预定列表",
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
	 *          property="start_date",
	 *          type="date",
	 *          description="预定开始时间"
	 *       ),
	 *       @OA\Property(
	 *          property="end_date",
	 *          type="date",
	 *          description="预定结束时间"
	 *       )
	 *     ),
	 *       example={
	 *              "venue_id": "","start_date":"","end_date"
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
		$validatedData = $request->validate([
			'book_state' => 'required|int|in:0,1,2,99',
		]);
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
			$orderBy = 'id';
		}
		// 排序方式desc 倒叙 asc 正序
		if ($request->input('order')) {
			$orderByAsc = $request->input('order');
		} else {
			$orderByAsc = 'desc';
		}
		if ($request->activity_type) {
			$map['activity_type'] = $request->activity_type;
		}

		DB::enableQueryLog();
		$data = $this->venueServices->venueBookModel()->where($map)
			->where(function ($q) use ($request) {
				if ($request->book_ym) {
					$s_date = dateFormat('Y-m-01', $request->book_ym);
					$e_date = dateFormat('Y-m-t', $request->book_ym);
					$q->whereBetween('start_date', [$s_date, $e_date]);
				}
				if ($request->book_state == 0) {
					$q->whereIn('state', [1, 99]);
				} else if ($request->book_state != 99) {
					$q->where('state', $request->book_state);
				}
				$request->belong_uid && $q->where('belong_uid', $request->belong_uid);
				$request->cus_name && $q->where('cus_name', 'like', '%' . $request->cus_name . '%');
				$request->venue_id && $q->where('venue_id', $request->venue_id);
			})
			->whereHas('venue', function ($q) use ($request) {
				$request->venue_name && $q->where('venue_name', 'like', '%' . $request->venue_name . '%');
				$request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
			})

			->with('venue:id,venue_name')
			->orderBy($orderBy, $orderByAsc)
			->paginate($pagesize)->toArray();
		// return response()->json(DB::getQueryLog());
		$data = $this->handleBackData($data);
		foreach ($data['result'] as $k => &$v) {
			$v['book_state'] = $this->venueServices->bookState($v['state']);
			$v['venue_name'] = $v['venue']['venue_name'];
		}
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/venue/book/add",
	 *     tags={"场馆"},
	 *     summary="场馆新增",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",required={"venue_id","start_date","end_date","activity_type"},
	 *      @OA\Property(property="venue_id",type="int",description="场馆id"),
	 * 		  @OA\Property(property="start_date",type="date",description="开始时间"),
	 *      @OA\Property(property="end_date",type="date",description="结束时间"),
	 *      @OA\Property(property="activity_type",type="String",description="活动类型"),
	 *      @OA\Property(property="cus_name",type="String",description="主办方")
	 *     ),
	 *       example={"venue_id": "",
	 *       	"start_date":"",
	 *        "end_date":"",
	 *        "activity_type":"",
	 *        "cus_name":""
	 *        }
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function store(Request $request)
	{

		$validatedData = $request->validate([
			'venue_id' => 'required|int|gt:0',
			'start_date' => 'required|date',
			'end_date' => 'required|date',
			'activity_type' => 'required|String|min:1',
			'activity_type_id' => 'required|int|gt:0',
			'cus_name' => 'required|String',
		]);
		$data = $request->toArray();
		$res = $this->venueServices->saveVenueBook($data, $this->user);
		if ($res) {
			return $this->success('场馆预定成功！');
		} else {
			return $this->error('场馆预定失败！');
		}
	}
	/**
	 * @OA\Post(
	 *     path="/api/venue/book/edit",
	 *     tags={"场馆"},
	 *     summary="场馆编辑",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"id","start_date","end_date"},
	 *       @OA\Property(property="id",type="int",description="场馆预定ID"),
	 *       @OA\Property(property="start_date",type="date",description="开始时间"),
	 *       @OA\Property(property="end_date",type="date",description="结束时间")
	 *     ),
	 *       example={
	 *              "id": "","start_date":"","end_date":""
	 *           }
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function update(Request $request)
	{

		$validatedData = $request->validate([
			'id' => 'required|min:1',
			'start_date' => 'required|min:1',
			'end_date' => 'required|min:1',
		]);
		$data = $request->toArray();
		$res = $this->venueServices->saveVenueBook($data, $this->user);
		if ($res) {
			return $this->success('场馆预定更新成功。');
		} else {
			return $this->error('场馆预定更新失败！');
		}
	}


	/**
	 * @OA\Post(
	 *     path="/api/venue/book/show",
	 *     tags={"场馆"},
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
			'id' => 'required|min:1',
		]);
		DB::enableQueryLog();
		$data = $this->venueServices->getVenueBookById($request->id);
		$settleBill =  $this->venueServices->venueSettleModel()
			->where('book_id', $request->id)
			->get()->toArray();
		if (!$settleBill) {
			$settleBill = [];
		}

		$data['settle_bill'] = $settleBill;
		// return response()->json(DB::getQueryLog());
		$data['book_state'] = $this->venueServices->bookState($data['state']);
		return $this->success($data);
	}


	/**
	 * @OA\Post(
	 *     path="/api/venue/book/cancel",
	 *     tags={"场馆"},
	 *     summary="场馆预定取消",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"id","cancel_date"},
	 *       @OA\Property(
	 *          property="id",
	 *          type="int",
	 *          description="场馆Id"
	 *       ),
	 *       @OA\Property(
	 *          property="cancel_reason",
	 *          type="String",
	 *          description="取消预定的原因"
	 *       ),
	 *       @OA\Property(
	 *          property="cancel_date",
	 *          type="date",
	 *          description="取消时间"
	 *       )
	 *     ),
	 *       example={
	 *              "id": 11,"cancel_reason":"","cancel_date":""
	 *           }
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function cancelBook(Request $request)
	{
		$validatedData = $request->validate([
			'id' => 'required|min:1',
			'cancel_date' => 'required|date',
		]);

		$venueBook = $this->venueServices->venueBookModel()->find($request->id);
		if ($venueBook->state == 2 || $venueBook->state == 99) {
			return $this->error('已经是结算或者取消状态不允许操作.');
		}
		$venueBook->state = 99;
		if ($request->cancel_reason) {
			$venueBook->cancel_reason = $request->cancel_reason;
		}
		$venueBook->cancel_date = $request->cancel_date;
		$res = $venueBook->save();

		// return response()->json(DB::getQueryLog());
		if ($res) {
			return $this->success('取消预定。');
		}
		return $this->error('取消预定失败！');
	}
	/**
	 * @OA\Post(
	 *     path="/api/venue/book/settle",
	 *     tags={"场馆"},
	 *     summary="场馆结算",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"id","settle_bill","settle_amount","settle_date"},
	 *       @OA\Property(property="id",type="int",description="场馆Id"),
	 *       @OA\Property(property="settle_amount",type="int",description="结算金额"),
	 *       @OA\Property(property="settle_date",type="date",description="结算日期"),
	 *       @OA\Property(property="settle_bill",type="list",description="结算账单")
	 *     ),
	 *       example={
	 *              "id": 11,"settle_amount":"","settle_date":"","settle_bill":"[]"
	 *           }
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */

	public function settleVenue(Request $request)
	{
		$validatedData = $request->validate([
			'id' => 'required|min:1',
			'settle_amount' => 'required',
			'settle_date' => 'required|date',
			'settle_bill' => 'array',
		]);
		$DA = $request->toArray();
		$venueSettle = $this->venueServices->getVenueBookById($DA['id']);
		if ($venueSettle->state == 99 || $venueSettle->state == 2) {
			return $this->error('已经结算或者此预定已经取消');
		}
		$res = $this->venueServices->settleVenue($DA, $this->user);
		if ($res) {
			return $this->success('结算成功');
		} else {
			return $this->error('结算失败');
		}
	}

	/**
	 * @OA\Post(
	 *     path="/api/venue/book/settle/stat",
	 *     tags={"场馆"},
	 *     summary="场馆结算统计，统计已结算的预定每月活动次数、每个月收入",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"type","proj_ids","start_date","end_name","settle_date"},
	 *       @OA\Property(property="type",type="int",description="1预定 2 结算"),
	 *       @OA\Property(property="proj_ids",type="list",description="项目ID集合"),
	 *       @OA\Property(property="start_date",type="date",description="结算开始日期"),
	 *       @OA\Property(property="end_date",type="date",description="结算结束日期"),
	 *       @OA\Property(property="venue_id",type="list",description="场馆Id")
	 *     ),
	 *       example={
	 *              "proj_ids": "[]","start_date":"","end_date":"","venue_id":""
	 *           }
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function settleStat(Request $request)
	{

		$data = $this->venueServices->venueBookModel()
			->selectRaw('sum(settle_amount) amount,count(*) count,DATE_FORMAT(start_date,"%Y-%m") ym')
			->where(function ($q) use ($request) {
				$request->start_date && $q->whereBetween('settle_date', [$request->start_date, $request->end_date]);
				$request->cus_name && $q->where('cus_name', 'like', '%' . $request->cus_name . '%');
				$request->venue_id && $q->where('venue_id', $request->venue_id);
				$q->where('state', 2);
			})
			->whereHas('venue', function ($q) use ($request) {
				$request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
			})->groupBy('ym')->get();

		$thisYear = date('Y-01-01', time());
		$i = 0;
		$stat = array();
		while ($i < 12) {
			foreach ($data as $k => $v) {
				if ($v['ym'] == getNextMonth($thisYear, $i)) {
					$stat[$i]['amount'] 	= $v['amount'];
					$stat[$i]['ym'] 			= $v['ym'];
					$stat[$i]['count'] 		= $v['count'];
					$i++;
				}
			}
			$stat[$i]['amount'] 	= 0.00;
			$stat[$i]['ym'] 			= getNextMonth($thisYear, $i);
			$stat[$i]['count'] 		= 0;
			$i++;
		}
		return $this->success($stat);
	}
}
