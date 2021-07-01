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
class VenueController extends BaseController
{

	public function __construct()
	{
		$this->uid  = auth()->payload()->get('sub');
		if (!$this->uid) {
			return $this->error('用户信息错误');
		}
		$this->company_id = getCompanyId($this->uid);
		$this->venueServices = new VenueServices;
		$this->user = auth('api')->user();
	}
	/**
	 * @OA\Post(
	 *     path="/api/venue/list",
	 *     tags={"场馆"},
	 *     summary="场馆列表",
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
	public function index(Request $request)
	{
		$pagesize = $request->input('pagesize');
		if (!$pagesize || $pagesize < 1) {
               $pagesize = config('per_size');
        }elseif($pagesize == '-1'){
            $pagesize = config('export_rows');
        }
		$map = array();

		if ($request->input('proj_id')) {
			$map['proj_id'] = $request->input('proj_id');
		}
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

		$data = $this->venueServices->VenueModel()
		->where($map)
		->where(function ($q) use($request){
			$request->venue_name && $q->where('venue_name','like','%'.$request->venue_name.'%');
			$request->proj_ids && $q->whereIn('proj_id',$request->proj_ids);
		})
		->with('project:id,proj_name')
		->withCount('venueBook')
		->withCount(['venueSettle as settle_amount' => function ($q)
		{
			$q->select(DB::Raw('ifnull(sum(amount),"0.00")'));
		}])
		->orderBy($orderBy, $orderByAsc)
		->paginate($pagesize)->toArray();
				// return response()->json(DB::getQueryLog());

		$data = $this->handleBackData($data);
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/venue/add",
	 *     tags={"场馆"},
	 *     summary="场馆新增",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",required={"venue_name","proj_id","venue_addr"},
	 *      @OA\Property(property="proj_id",type="int",description="项目id"),
	 * 		  @OA\Property(property="venue_name",type="String",description="场馆名称"),
	 *      @OA\Property(property="venue_addr",type="String",description="场馆地址 省份城市区域加详细地址")
	 *     ),
	 *       example={"venue_name": "","venue_addr":"","proj_id":""}
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
			'venue_name' => 'required|String|min:1',
			// 'venue_addr' => 'required|String',
			'proj_id' => 'required|min:1',
		]);
		$data = $request->toArray();

		$map['company_id'] = $this->company_id;
		$map['venue_name'] = $data['venue_name'];
		$map['proj_id'] = $data['proj_id'];
		$check_venue = $this->venueServices->VenueModel()->where($map)->exists();
		if ($check_venue) {
			return $this->error($data['venue_name'] . '场馆已存在！');
		}

		$res = $this->venueServices->saveVenue($data,$this->user);
		if ($res) {
			return $this->success('场馆添加成功！');
		} else {
			return $this->error('场馆添加失败！');
		}
	}
	/**
	 * @OA\Post(
	 *     path="/api/venue/edit",
	 *     tags={"场馆"},
	 *     summary="场馆编辑",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"id","venue_name","proj_id"},
	 *       @OA\Property(property="id",type="int",description="场馆ID"),
	 *       @OA\Property(property="venue_name",type="String",description="场馆名称"),
	 *       @OA\Property(property="proj_id",type="int",description="项目ID")
	 *     ),
	 *       example={
	 *              "id": "","proj_id":"","venue_name":""
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
			'venue_name' => 'required|min:1',
			'proj_id' => 'required|min:1',
		]);
		$data = $request->toArray();
		$data['u_uid'] 		= $this->uid;
		$map['venue_name'] = $data['venue_name'];
		$map['proj_id'] = $data['proj_id'];
		$check_venue = $this->venueServices->VenueModel()
		->where($map)->where('id', '!=', $data['id'])->exists();
		if ($check_venue) {
			return $this->error($data['venue_name'] . '场馆已存在！');
		}
		$res = $this->venueServices->saveVenue($data,$this->user,2);
		if ($res) {
			return $this->success('场馆更新成功。');
		} else {
			return $this->error('场馆更新失败！');
		}
	}


	/**
	 * @OA\Post(
	 *     path="/api/venue/show",
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
		DB::enableQueryLog();
		$data = $this->venueServices->getVenueById($request->id)->toArray();
		$data['venue_book'] = $this->venueServices->getVenueBook($request->id);
		// return response()->json(DB::getQueryLog());

		return $this->success($data);
	}


	/**
	 * @OA\Post(
	 *     path="/api/venue/enable",
	 *     tags={"场馆"},
	 *     summary="场馆启用禁用",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"id","is_vaild"},
	 *       @OA\Property(
	 *          property="id",
	 *          type="int",
	 *          description="场馆Id"
	 *       ),
	 *       @OA\Property(
	 *          property="is_vaild",
	 *          type="int",
	 *          description="1启用0禁用"
	 *       )
	 *     ),
	 *       example={
	 *              "id": 11,"is_vaild":0
	 *           }
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function enable(Request $request)
	{
		$validatedData = $request->validate([
			'id' => 'required|int|gt:0',
			'is_vaild' => 'required|int|in:0,1',
		]);
		DB::enableQueryLog();
		$res = $this->venueServices->whereId($request->id)
		->update(['is_vaild',$request->is_vaild]);

		// return response()->json(DB::getQueryLog());
		if ($res) {
			return $this->success('场馆更新成功。');
		}else{
			return $this->success('场馆更新失败');
		}
	}

}
