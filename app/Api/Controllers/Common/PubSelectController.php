<?php

namespace App\Api\Controllers\Common;

use App\Enums\AppEnum;
use Illuminate\Http\Request;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Support\Facades\DB;
use App\Api\Controllers\BaseController;
use App\Api\Models\Company\BankAccount;
use App\Api\Services\Sys\DepartService;
use App\Api\Services\Bill\ChargeService;
use App\Api\Models\Project as ProjectModel;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Models\Building as BuildingModel;
use App\Api\Models\BuildingFloor as FloorModel;
use App\Api\Models\Channel\Channel as channelModel;
use App\Api\Models\Sys\UserGroup as UserGroupModel;
use App\Api\Models\Company\CompanyDict as DictModel;
use App\Api\Models\BuildingRoom as BuildingRoomModel;
use App\Api\Models\Channel\ChannelPolicy as ChannelPolicyModel;

/**
 * parent_type 联系人类型 1 channel 2 客户 3 供应商 4 政府关系 5 租户
 */
class PubSelectController extends BaseController
{
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * @OA\Post(
	 *     path="/api/pub/proj/getAll",
	 *     tags={"选择公用接口"},
	 *     summary="项目列表选择使用，超级管理员不判断权限",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"limit"},
	 *       @OA\Property(property="limit",type="int",description="1 有权限的项目 0 所有项目")
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

	public function projAll(Request $request)
	{
		// 判断是不是需要判断用户具有的项目权限
		$DA = $request->toArray();
		DB::enableQueryLog();

		$user = auth('api')->user();
		$DA['is_admin'] = $user->is_admin;
		if (!$user->is_admin) {
			$userGroup = UserGroupModel::find($user->group_id);
			if ($userGroup) {
				$DA['proj_limit'] = str2Array($userGroup->project_limit);
			} else {
				$DA['proj_limit'] = [];
			}
		}
		$data = ProjectModel::with('building')
			->when($request->limit, function ($q) use ($DA) {
				if (!$DA['is_admin']) {
					$q->whereIn('id', $DA['proj_limit']);
				}
			})
			->where('is_valid', 1)
			->get()->toArray();
		// return response()->json(DB::getQueryLog());
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/pub/building/getAll",
	 *     tags={"选择公用接口"},
	 *     summary="楼宇列表选择使用",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"proj_id"},
	 *       @OA\Property(property="proj_id",type="int",description="项目ID")
	 *     ),
	 *       example={"proj_id":"1"}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function buildingAll(Request $request)
	{
		$validatedData = $request->validate([
			'proj_id' => 'required|min:1',
		]);
		$projId = $request->proj_id;
		// DB::enableQueryLog();
		$data = BuildingModel::selectRaw('id,id as value , build_no as label, build_no,build_no as title ,1 as level')
			->whereHas('project', function ($q) use ($projId) {
				$q->whereId($projId);
			})
			->with(['children' => function ($q) {
				$q->selectRaw('id as value,build_id,floor_no as label,id,floor_no,floor_no as title,2 as level');
			}])
			->where('is_valid', AppEnum::valid)
			->withCount('floor')
			->get()->toArray();
		// return response()->json(DB::getQueryLog());
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/pub/select/space",
	 *     tags={"选择公用接口"},
	 *     summary="楼宇列表加房间选择使用",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"proj_id","room_type"},
	 *       @OA\Property(
	 *          property="proj_id",
	 *          type="int",
	 *          description="项目ID"
	 *       ),
	 *       @OA\Property(
	 *          property="room_type",
	 *          type="int",
	 *          description="房源类型 1房间2工位3场馆"
	 *       ),
	 *       @OA\Property(
	 *          property="room_state",
	 *          type="int",
	 *          description="1可招商 0不可"
	 *       )
	 *     ),
	 *     example={
	 *         "proj_id":"1","room_type":"1","room_state":"1"
	 *          }
	 *      )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function selectSpace(Request $request)
	{
		$validatedData = $request->validate([
			'proj_id' => 'required|numeric|gt:0',
			'room_type' => 'required|in:1,2,3',
		]);
		$map['proj_id'] = $request->proj_id;
		$map['is_valid'] = 1; // 启用状态

		$subMap['room_type'] =  $request->room_type;
		$subMap['is_valid']  = 1;
		if ($request->room_state) {
			// $subMap['room_state'] = $request->room_state;
		}

		$buildings = BuildingModel::select('id', 'proj_id', 'proj_name', 'build_type', 'build_no')
			->where($map)
			->whereHas('buildRoom', function ($q) use ($subMap) {
				$q->where($subMap);
			})->get();

		if ($request->room_type != 3) {
			foreach ($buildings as $k => &$v) {
				DB::enableQueryLog();
				$floors = FloorModel::where('build_id', $v['id'])
					->whereHas('floorRoom', function ($q) use ($subMap) {
						$q->where($subMap);
					})->get();
				foreach ($floors as $kr => &$vr) {
					$rooms = BuildingRoomModel::where('floor_id', $vr['id'])
						->where(function ($q) use ($subMap) {
							$q->where($subMap);
						})
						->get();

					$floor_room_count = 0;
					$floor_area = 0.00;
					foreach ($rooms as $km => $vm) {
						$rooms[$km]['room_id'] = $vm['id'];
						$rooms[$km]['build_no'] = $v['build_no'];
						$rooms[$km]['floor_no'] = $vr['floor_no'];
						$rooms[$km]['proj_id'] = $v['proj_id'];
						$rooms[$km]['proj_name'] = $v['proj_name'];
						$floor_area += $vm['room_area'];
						$floor_room_count++;
					}
					$floors[$kr]['floor_room_count'] = $floor_room_count;
					$floors[$kr]['floor_area'] = $floor_area;

					$vr['rooms'] = $rooms;
				}
				$v['floors'] = $floors;
			}
		}
		return $this->success($buildings);
	}

	/**
	 * @OA\Post(
	 *     path="/api/pub/room/getAll",
	 *     tags={"选择公用接口"},
	 *     summary="根据build_id,floor_id获取所有的房间信息",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"floor_id"},
	 *       @OA\Property(
	 *          property="build_id",
	 *          type="int",
	 *          description="楼ID"
	 *       ),
	 *       @OA\Property(
	 *          property="floor_id",
	 *          type="int",
	 *          description="层ID"
	 *       )
	 *     ),
	 *       example={
	 *       	"build_id":"1",
	 *          "floor_id":"1"
	 *           }
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function roomAll(Request $request)
	{
		$validatedData = $request->validate([
			'floor_id' => 'required|min:1',
		]);
		// DB::enableQueryLog();
		if ($request->build_id && $request->build_id > 0) {
			$map['build_id'] = $request->build_id;
		}

		if ($request->floor_id && $request->floor_id > 0) {
			$map['floor_id'] = $request->floor_id;
		}
		if ($request->room_type && $request->room_type > 0) {
			$map['room_type'] = $request->room_type;
		}
		$map['is_valid'] = 1;
		// $map['room_state'] =1;
		$data = BuildingRoomModel::where($map)->get()->toArray();
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/pub/dict/getAll",
	 *     tags={"选择公用接口"},
	 *     summary="获取字典数据 ,dict_key：可单个值|多个逗号分隔|不传所有",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *       @OA\Property(property="dict_key",type="String",description="类型字符串，多个用逗号隔开")
	 *     ),
	 *       example={
	 *       	"dict_key":""
	 *           }
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */

	public function dictAll(Request $request)
	{
		$companyIds = array(0, $this->company_id);

		if ($request->dict_key) {
			$keys = explode(',', $request->dict_key);
		} else {
			$dict = DictModel::select(DB::Raw("group_concat(distinct(dict_key)) as dict_keys"))
				->whereIn('company_id', $companyIds)
				->first();
			$keys = explode(",", $dict['dict_keys']);
		}

		foreach ($keys as $k => $v) {
			$data = DictModel::select(DB::Raw('dict_value as value,ifnull(label,id) as id'))
				->whereIn('company_id', $companyIds)
				->where('dict_key', $v)
				->where('is_vaild', 1)
				->get()->toArray();
			$DA[$v] = $data;
		}
		return $this->success($DA);
	}

	/**
	 * @OA\Post(
	 *     path="/api/pub/channel/getAll",
	 *     tags={"选择公用接口"},
	 *     summary="获取所有的渠道以及渠道联系人 通过id 获取单个渠道信息 不传所有",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel"
	 *     ),
	 *       example={
	 *          "dict_type":""
	 *           }
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */

	public function channelAll(Request $request)
	{

		$map = array();
		if ($request->id) {
			$map['id'] = $request->id;
		}
		DB::enableQueryLog();
		$map['is_valid'] = 1;
		$data = channelModel::select('id', 'channel_name', 'policy_id')
			->with('channelContact')
			->where($map)
			->where(function ($q) use ($request) {
				if ($request->proj_ids) {
					// $q->orWhere(DB::Raw("proj_ids = ''"));
					$q->whereRaw(" (proj_ids = '' or find_in_set('" . $request->proj_ids . "',proj_ids))");
				}
			})
			->with('channelPolicy')
			->orderBy('created_at', 'desc')
			->get()->toArray();
		foreach ($data as $k => &$v) {
			$v['month_rate'] = $v['channel_policy']['month'];
			unset($v['channel_policy']);
		}
		// return response()->json(DB::getQueryLog());
		return $this->success($data);
	}


	/**
	 * @OA\Post(
	 *     path="/api/pub/channel/getPolicyAll",
	 *     tags={"选择公用接口"},
	 *     summary="查询所有的渠道政策",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel"
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

	public function policyAll()
	{
		$result = ChannelPolicyModel::select('id', 'name')->get()->toArray();
		return $this->success($result);
	}

	/** select 选择使用 */

	/**
	 * @OA\Post(
	 *     path="/api/pub/customer/getAll",
	 *     tags={"选择公用接口"},
	 *     summary="客户列表select使用",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"proj_ids"},
	 *				@OA\Property(property="proj_ids",type="String",description="项目ID，多个用逗号隔开"),
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
	public function cusList(Request $request)
	{
		$request->validate([
			'proj_ids' => 'required',
		], ['proj_ids.required' => '项目ID不能为空']);
		$data = Tenant::select('id', 'name', 'industry', 'type', 'business_id')
			->where(function ($q) use ($request) {
				if ($request->type == 1) {
					$q->whereIn('type', [1, 3]);
				}
				$request->proj_ids && $q->whereIn('proj_id', str2Array($request->proj_ids));
				$this->applyUserPermission($q, $request->depart_id, $this->user);
			})
			->with('business_info:id,legalPersonName')
			->orderBy('name', 'asc')
			->get()->toArray();

		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/pub/venue/getAll",
	 *     tags={"选择公用接口"},
	 *     summary="场馆列表select使用",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={},
	 *     ),
	 *       example={
	 *           }
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function venueList(Request $request)
	{
		$map['is_vaild'] = 1;
		if ($request->proj_id) {
			$map['proj_id'] = $request->proj_id;
		}
		$venue = new \App\Api\Services\Venue\VenueServices;
		$data = $venue->venueModel()->select('id', 'proj_id', 'venue_name', 'venue_area')
			->with('project:id,proj_name')
			->where($map)
			->get()->toArray();

		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/pub/tenant/getAll",
	 *     tags={"选择公用接口"},
	 *     summary="租户列表select使用",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"proj_ids"},
	 * 					@OA\Property(property="proj_ids",type="String",description="项目ID，多个用逗号隔开"),
	 *          @OA\Property(property="type",type="int",description="0 所有 1 客户 2租户")
	 *     ),
	 *       example={"proj_ids":"1,2"}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */

	public function tenantList(Request $request)
	{
		$request->validate([
			'proj_ids' => 'required',
		]);

		$data = \App\Api\Models\Tenant\Tenant::select('id', 'name', 'industry', 'level', 'proj_id', 'on_rent', 'state', 'parent_id')
			->where(function ($q) use ($request) {
				// $q->where('parent_id', 0);
				$request->proj_ids && $q->whereIn('proj_id', str2Array($request->proj_ids));
				if ($request->type == 2) {
					$q->where('type', AppEnum::TenantType);
				}
			})
			->with('contacts:id,contact_name,contact_phone,contact_role,is_default,parent_id,parent_type')
			// ->with('invoice:id,tenant_id,title,bank_name,tax_number,tel_number,account_name,invoice_type,addr')
			->orderBy('name', 'asc')
			->get()->toArray();

		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/pub/relations/getAll",
	 *     tags={"选择公用接口"},
	 *     summary="供应商列表select使用",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"proj_ids"},
	 * 					@OA\Property(property="proj_ids",type="String",description="项目ID，多个用逗号隔开")
	 *     ),
	 *       example={"proj_ids":"1,2"}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function relationsList(Request $request)
	{
		$data = \App\Api\Models\Operation\PubRelations::where(function ($q) use ($request) {
			$request->proj_ids && $q->whereIn('proj_id', str2Array($request->proj_ids));
		})
			->orderBy('name', 'asc')
			->get()->toArray();
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/pub/supplier/getAll",
	 *     tags={"选择公用接口"},
	 *     summary="供应商列表select使用",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"proj_ids"},
	 * 					@OA\Property(property="proj_ids",type="String",description="项目ID，多个用逗号隔开")
	 *     ),
	 *       example={"proj_ids":"1,2"}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */

	public function supplierList(Request $request)
	{
		$data = \App\Api\Models\Operation\Supplier::select('id', 'name', 'proj_id', 'major', 'department', 'service_content', 'main_business')
			->where(function ($q) use ($request) {
				$request->proj_ids && $q->whereIn('proj_id', str2Array($request->proj_ids));
			})
			->orderBy('name', 'asc')
			->get()->toArray();
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/pub/equipment/getAll",
	 *     tags={"选择公用接口"},
	 *     summary="设备列表select使用",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"proj_ids"},
	 * 					@OA\Property(property="proj_ids",type="String",description="项目ID，多个用逗号隔开")
	 *     ),
	 *       example={"proj_ids":"1,2"}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */

	public function equipmentList(Request $request)
	{
		$data = \App\Api\Models\Equipment\Equipment::where(function ($q) use ($request) {
			$request->proj_ids && $q->whereIn('proj_id', str2Array($request->proj_ids));
		})
			->orderBy('device_name', 'asc')
			->get()->toArray();

		if ($data) {
			foreach ($data as $k => &$v) {
				$v['maintain_period_label'] = getDictName($v['maintain_period']);
			}
			return $this->success($data);
		}
		return $this->success($data);
	}


	/**
	 * @OA\Post(
	 *     path="/api/pub/feetype/getAll",
	 *     tags={"选择公用接口"},
	 *     summary="费用类型接口",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"proj_id"},
	 * 				@OA\Property(property="proj_id",type="String",description="项目ID"),
	 *     ),
	 *       example={"proj_id":1}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function feetypeList(Request $request)
	{
		$request->validate([
			'proj_id' => 'required|gt:0',
		], [
			'proj_id.required' => '项目ID不能为空',
			'proj_id.gt' => '项目ID必须大于0'
		]);
		$service = new \App\Api\Services\Company\FeeTypeService;
		$companyIds = getCompanyIds($this->uid);
		$data = $service->model()->whereIn('company_id', $companyIds)
			->where(function ($q) use ($request) {
				$request->type && $q->where('type', $request->type);
			})
			->where('is_vaild', AppEnum::valid)
			->orderBy('id', 'asc')
			->get()->toArray();
		foreach ($data as &$v) {
			DB::enableQueryLog();
			$bank = BankAccount::whereRaw("FIND_IN_SET(?, fee_type_id)", [$v['id']])
				->where('proj_id', $request->proj_id)->first();
			// Log::info(json_decode($bank));
			// return response()->json(DB::getQueryLog());
			$v['bank_id'] = $bank ? $bank->id : 0;
			$v['notice'] = $v['bank_id'] == 0 ? "未绑定银行收款账户" : "已绑定银行收款账户";
		}

		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/pub/tenant/bill/detail",
	 *     tags={"选择公用接口"},
	 *     summary="查询未开发费用数据",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"proj_id","tenant_id"},
	 *  				@OA\Property(property="proj_id",type="int",description="项目ID"),
	 * 				@OA\Property(property="tenant_id",type="int",description="租户id"),
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
	public function getBillDetail(Request $request)
	{
		$validatedData = $request->validate([
			'proj_id' => 'required',
			'tenant_id' => 'required',
		]);
		$service = new TenantBillService;
		$where['proj_id'] = $request->proj_id;
		$where['tenant_id'] = $request->tenant_id;
		$where['type'] = 1;
		// $where['invoice_id'] = 0;
		$query = $service->billDetailModel()
			->where(function ($q) use ($request) {
				$request->fee_type && $q->where('fee_type', $request->fee_type);
				isset($request->invoice_id) && $q->where('invoice_id', $request->invoice_id);
				isset($request->status) && $q->where('status', $request->status);
			})
			->where($where);
		if ($request->verify) {
			$data = $query->with('chargeBillRecord')->get();
		} else {
			$data = $query->get();
		}
		return $this->success($data);
	}

	/**
	 * @OA\Post(
	 *     path="/api/pub/tenant/charge/bill",
	 *     tags={"选择公用接口"},
	 *     summary="查询未核销完的收款费用",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"proj_id","tenant_id"},
	 *  				@OA\Property(property="proj_id",type="int",description="项目ID"),
	 * 				@OA\Property(property="tenant_id",type="int",description="租户id"),
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
	public function getChargeBill(Request $request)
	{
		$validatedData = $request->validate([
			'proj_id' => 'required',
			'tenant_id' => 'required',
			'source'  => 'required',
		]);

		$chargeService = new ChargeService;
		$where['proj_id'] = $request->proj_id;
		$where['tenant_id'] = $request->tenant_id;
		$where['source'] = $request->source;
		DB::enableQueryLog();
		$data = $chargeService->model()
			->where(function ($q) use ($request) {
				$request->type && $q->where('type', $request->type);
				$request->bank_id && $q->where('bank_id', $request->bank_id);
				if (isset($request->status)) {
					$q->where('status', $request->status);
				}
			})
			->where($where)->get();
		foreach ($data as $k => &$v) {
			$availableAmt = bcsub($v['amount'], $v['verify_amount'], 2);
			$v['available_amount'] = bcsub($availableAmt, $v['refund_amount'], 2);
		}
		// return response()->json(DB::getQueryLog());
		return $this->success($data);
	}


	/**
	 * @OA\Post(
	 *     path="api/pub/depart/getAll",
	 *     tags={"选择公用接口"},
	 *     summary="部门选择",
	 *    @OA\RequestBody(
	 *       @OA\MediaType(
	 *           mediaType="application/json",
	 *       @OA\Schema(
	 *          schema="UserModel",
	 *          required={"is_vaild"},
	 *  				@OA\Property(property="is_vaild",type="int",description="0 无效 1 有效"),
	 * 				
	 *     ),
	 *       example={"is_vaild":1}
	 *       )
	 *     ),
	 *     @OA\Response(
	 *         response=200,
	 *         description=""
	 *     )
	 * )
	 */
	public function getDeparts(Request $request)
	{
		$validatedData = $request->validate([
			'is_vaild' => 'required|in:0,1',
		]);
		$departService = new DepartService;
		$data =  $departService->getDepartSelect(0, $request->is_vaild);
		return $this->success($data);
	}
}
