<?php

namespace App\Api\Controllers\Business;

use JWTAuth;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Api\Controllers\BaseController;
use App\Api\Excel\Business\BuildingRoomExcel;
use App\Api\Services\Building\BuildingService;
use App\Api\Services\Contract\ContractService;
use App\Api\Models\Tenant\Tenant as TenantModel;
use App\Api\Services\Building\BuildingRoomService;

/**
 * @OA\Tag(
 *     name="房源",
 *     description="房源管理"
 * )
 */
class BuildingRoomController extends BaseController
{
    private $buildRoomService;
    private $errorMsg;
    public function __construct()
    {
        parent::__construct();
        $this->buildRoomService = new BuildingRoomService;
        $this->errorMsg = [
            'build_id.required' => '楼号不能为空',
            'floor_id.required' => '楼层不能为空',
            'room_no.required' => '房间号不能为空',
            'room_type.required' => '房间类型不能为空',
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/business/building/room/list",
     *     tags={"房源"},
     *     summary="根据房源ID获取房源信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize","proj_id","build_id","orderBy","order"},
     *     ),
     *       example={"pagesize": 10,"proj_id": 11,"build_id":1,"orderBy":"created_at","order":"desc"}
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

        if ($request->build_id) {
            $map['build_id'] = $request->build_id;
            if ($request->floor_id) {
                $map['floor_id'] = $request->floor_id;
            }
        }
        if ($request->channel_state) {
            $map['channel_state'] = $request->channel_state;
        }
        if ($request->room_state) {
            $map['room_state'] = $request->room_state;
        }

        if ($request->room_type) { // 1 房间 2 工位
            $map['room_type'] = $request->room_type;
        } else {
            $map['room_type'] = 1;
        }
        if ($request->room_trim_state) {
            $map['room_trim_state'] = $request->room_trim_state;
        }
        $map['is_valid'] = 1;

        DB::enableQueryLog();
        $subQuery = $this->buildRoomService->buildingRoomModel()->where($map)
            ->where(function ($q) use ($request) {
                $request->room_no && $q->where('room_no', 'like', columnLike($request->room_no));
            })
            ->whereHas('building', function ($q) use ($request) {
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->build_no && $q->where('build_no', 'like', columnLike($request->build_no));
            })
            ->with('building:id,proj_name,build_no,proj_id')
            ->with('floor:id,floor_no');

        $pageQuery = clone $subQuery;
        $data = $this->pageData($pageQuery, $request);
        if ($request->export) {
            return $this->exportToExcel($data['result'], BuildingRoomExcel::class);
        }

        $buildService  = new BuildingService;

        if ($data['result']) {
            $data['result'] = $buildService->formatData($data['result']);
        }

        $data['stat'] = $buildService->areaStat($map, $request->proj_ids, array());
        return $this->success($data);
    }


    /**
     * @OA\Post(
     *     path="/api/business/building/room/show",
     *     tags={"房源"},
     *     summary="根据房源ID获取房源信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="房源Id"
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
            'id' => 'required|numeric|gt:0',
        ], [
            'id.required' => '房源ID不能为空',
        ]);
        $data = $this->buildRoomService->buildingRoomModel()
            ->whereHas('building', function ($q) use ($request) {
                $request->proj_id && $q->where('proj_id', $request->proj_id);
            })
            ->with('building:id,proj_name,build_no,proj_id')
            ->with('floor:id,floor_no')
            ->find($request->id)->toArray();
        DB::enableQueryLog();

        $customer = TenantModel::whereHas('tenantRooms', function ($q) use ($request) {
            $q->where('room_id', $request->id);
        })
            ->select('name', 'state', 'belong_person', 'industry', 'created_at')
            ->get();
        $data['customer'] = $customer;
        $contract = new ContractService;
        $data['contract'] = $contract->getContractByRoomId($request->id);

        // return response()->json(DB::getQueryLog());
        // $data = $this->handleBackData($result);
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/business/building/room/add",
     *     tags={"房源"},
     *     summary="新增房源",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"proj_id","build_id","build_room_no"},
     *       @OA\Property(
     *          property="proj_id",
     *          type="int",
     *          description="项目Id"
     *       ),
     *       @OA\Property(
     *          property="build_id",
     *          type="int",
     *          description="楼号Id"
     *       ),
     *       @OA\Property(
     *          property="build_room_no",
     *          type="String",
     *          description="房间号"
     *       )
     *     ),
     *       example={
     *              "proj_id": 11,"build_id":1,"build_room_no":""
     *           }
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
            'proj_id' => 'required|min:1',
            'build_id' => 'required|numeric|gt:0',
            'floor_id' => 'required|numeric|gt:0',
            'room_no' => 'required|String|min:1',
            'room_type' => 'required|numeric|in:1,2'
        ], $this->errorMsg);
        // $map['proj_id'] = $request->proj_id;
        $map['build_id'] = $request->build_id;
        $map['room_no'] = $request->room_no;
        $map['floor_id'] = $request->floor_id;

        $building = $this->buildRoomService->buildingModel()->whereId($request->build_id)->exists();
        if (!$building) {
            return $this->error('项目或者楼宇不存在');
        }
        $checkRoom = $this->buildRoomService->buildingRoomModel()->where($map)->exists();
        if ($checkRoom) {
            return $this->error('【' . $request->room_no . '】房源重复');
        }
        $data = $request->toArray();
        $room = $this->buildRoomService->formatRoom($data, $this->user);
        $res = $this->buildRoomService->buildingRoomModel()->create($room);
        if ($res) {
            return $this->success('房源保存成功');
        }
        return $this->error('房源保存失败');
    }


    /**
     * @OA\Post(
     *     path="/api/business/building/room/edit",
     *     tags={"房源"},
     *     summary="房源编辑",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id","proj_id","build_id","build_room_no"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="Id"
     *       ),
     *       @OA\Property(
     *          property="proj_id",
     *          type="int",
     *          description="项目Id"
     *       ),
     *       @OA\Property(
     *          property="build_id",
     *          type="int",
     *          description="楼号Id"
     *       ),
     *       @OA\Property(
     *          property="build_room_no",
     *          type="String",
     *          description="房间号"
     *       )
     *     ),
     *       example={
     *              "id":1,"proj_id": 11,"build_id":1,"build_room_no":""
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
            'id' => 'required|numeric|gt:0',
            'build_id' => 'required|numeric|gt:0',
            'floor_id' => 'required|numeric|gt:0',
            'room_type' => 'required|numeric|in:1,2',
            'room_no' => 'required|String',
        ], $this->errorMsg);

        // $map['proj_id'] = $request->proj_id;
        $map['build_id'] = $request->build_id;
        $map['room_no'] = $request->room_no;
        $map['floor_id'] = $request->floor_id;
        $map['room_type'] = $request->room_type;

        $checkRoom = $this->buildRoomService->buildingRoomModel()->where($map)
            ->where('id', '!=', $request->id)
            ->exists();
        if ($checkRoom) {
            return $this->error('【' . $request->room_no . '】房源重复');
        }
        $data = $request->toArray();
        $room = $this->buildRoomService->formatRoom($data, $this->user, 2);
        $res = $this->buildRoomService->buildingRoomModel()->whereId($data['id'])->update($room);
        if ($res) {
            return $this->success('房源保存成功');
        }
        return $this->error('房源保存失败');
    }

    /**
     * @OA\Post(
     *     path="/api/business/building/room/enable",
     *     tags={"房源"},
     *     summary="房源启用禁用",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"Ids","is_vaild"},
     *       @OA\Property(
     *          property="Ids",
     *          type="list",
     *          description="房源ID集合"
     *       ),
     *       @OA\Property(
     *          property="is_vaild",
     *          type="int",
     *          description="0禁用1 启用"
     *       )
     *
     *     ),
     *       example={
     *              "Ids":"[1]","is_vaild":0
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
            'Ids' => 'required|array',
            'is_valid' => 'required|numeric|in:0,1',
        ], [
            'Ids.required' => '房源ID不能为空',
            'is_valid.required' => '启用禁用状态不能为空',
        ]);
        $data['is_valid'] = $request->is_valid;
        $res = $this->buildRoomService->buildingRoomModel()->whereIn('id', $request->Ids)->update($data);
        if ($res) {

            return $this->success('房源启用禁用成功');
        }
        return $this->error('房源启用禁用');
    }
}
