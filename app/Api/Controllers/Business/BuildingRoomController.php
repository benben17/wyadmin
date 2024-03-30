<?php

namespace App\Api\Controllers\Business;

use JWTAuth;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Api\Controllers\BaseController;
use App\Api\Excel\Business\BuildingRoomExcel;
use App\Api\Models\Building as BuildingModel;
use App\Api\Models\BuildingRoom  as RoomModel;
use App\Api\Services\Building\BuildingService;

use App\Api\Services\Contract\ContractService;
use App\Api\Models\Tenant\Tenant as TenantModel;

/**
 * 项目房源信息
 */
class BuildingRoomController extends BaseController
{
    private $buildService;
    public function __construct()
    {
        parent::__construct();
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
    public function index(Request $request)
    {
        $pagesize = $this->setPagesize($request);
        $map = array();

        if ($request->build_id) {
            $map['build_id'] = $request->build_id;
            if ($request->build_floor_id) {
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
        DB::enableQueryLog();
        $data = RoomModel::where($map)
            ->where(function ($q) use ($request) {
                $request->room_no && $q->where('room_no', 'like', columnLike($request->room_no));
            })
            ->whereHas('building', function ($q) use ($request) {
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->build_no && $q->where('build_no', 'like', columnLike($request->build_no));
            })
            ->with('building:id,proj_name,build_no,proj_id')
            ->with('floor:id,floor_no')
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();

        // return response()->json(DB::getQueryLog());
        $data = $this->handleBackData($data);
        $buildService  = new BuildingService;

        if ($data['result']) {
            $data['result'] = $buildService->formatData($data['result']);
        }
        if ($request->export) {
            return $this->exportToExcel($data['result'], BuildingRoomExcel::class);
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
     *          required={"roomId"},
     *       @OA\Property(
     *          property="roomId",
     *          type="int",
     *          description="房源Id"
     *       )
     *     ),
     *       example={
     *              "roomId": 11
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
        ]);
        $data = RoomModel::whereHas('building', function ($q) use ($request) {
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
            // 'proj_id' =>'required|min:1',
            'build_id' => 'required|numeric|gt:0',
            'floor_id' => 'required|numeric|gt:0',
            'room_no' => 'required|String|min:1',
            'room_type' => 'required|numeric|in:1,2'
        ]);
        // $map['proj_id'] = $request->proj_id;
        $map['build_id'] = $request->build_id;
        $map['room_no'] = $request->build_room_no;
        $map['floor_id'] = $request->floor_id;

        $building = BuildingModel::whereId($request->build_id)->exists();
        if (!$building) {
            return $this->error('项目或者楼不存在');
        }
        $checkRoom = RoomModel::where($map)->exists();
        if ($checkRoom) {
            return $this->error('房源重复');
        }
        $data = $request->toArray();
        $room = $this->formatRoom($data);
        $res = RoomModel::create($room);
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
        ]);

        // $map['proj_id'] = $request->proj_id;
        $map['build_id'] = $request->build_id;
        $map['room_no'] = $request->build_room_no;
        $map['floor_id'] = $request->floor_id;
        $map['room_type'] = $request->room_type;

        $checkRoom = RoomModel::where($map)
            ->where('id', '!=', $request->id)
            ->exists();
        if ($checkRoom) {
            return $this->error('房源重复');
        }
        $data = $request->toArray();
        $room = $this->formatRoom($data, 2);
        $res = RoomModel::whereId($room['id'])->update($room);
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
            'is_vaild' => 'required|numeric|in:0,1',
        ]);
        $data['is_vaild'] = $request->is_vaild;
        $res = RoomModel::whereIn('id', $request->Ids)->update($data);
        if ($res) {
            return $this->success('房源删除成功');
        }
        return $this->error('房源删除失败');
    }


    /**
     * @OA\Post(
     *     path="/api/business/building/wx/rooms",
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
    public function rooms(Request $request)
    {
        $validatedData = $request->validate([
            'Ids' => 'required|array',
            'is_vaild' => 'required|numeric|in:0,1',
            // 'is_vaild' => 'required|numeric|in:0,1',
        ]);

        $data = RoomModel::where(function ($q) use ($request) {
            $request->Ids && $q->whereIn('id', $request->Ids);
            $request->is_vaild && $q->where('is_vaild', $request->is_vaild);
        })
            ->whereHas('building', function ($q) use ($request) {
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
            })
            ->with('building:id,proj_name,build_no,proj_id')
            ->with('floor:id,floor_no')
            ->get()->toArray();
        $roomStat = RoomModel::selectRaw('min(room_area) min_area,max(room_area) max_area,avg(room_price) avg_price')
            ->where(function ($q) use ($request) {
                $request->Ids && $q->whereIn('id', $request->Ids);
                $request->is_vaild && $q->where('is_vaild', $request->is_vaild);
            })
            ->whereHas('building', function ($q) use ($request) {
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
            })->first();
        $roomStat['avg_price'] = numFormat($roomStat['avg_price']);
        $result['stat'] = $roomStat;
        $result['rooms'] = $data;
        return $this->success($result);
    }

    private function formatRoom($DA, $type = 1)
    {
        if ($type == 1) {
            $BA['c_uid'] = $this->uid;
        } else {
            $BA['id'] = $DA['id'];
            $BA['u_uid'] = $this->uid;
        }
        $BA['room_type'] = 1;
        $BA['company_id'] = $this->company_id;
        $BA['build_id'] = $DA['build_id'];
        $BA['floor_id'] = $DA['floor_id'];
        $BA['room_no'] = $DA['room_no'];
        $BA['room_state'] = $DA['room_state'];
        $BA['room_measure_area'] = isset($DA['room_measure_area']) ? $DA['room_measure_area'] : 0;
        $BA['room_trim_state'] = isset($DA['room_trim_state']) ? $DA['room_trim_state'] : "";
        $BA['room_price'] = isset($DA['room_price']) ? $DA['room_price'] : 0.00;
        $BA['price_type'] = isset($DA['price_type']) ? $DA['price_type'] : 1;
        $BA['room_tags'] = isset($DA['room_tags']) ? $DA['room_tags'] : "";
        $BA['channel_state'] = isset($DA['channel_state']) ? $DA['channel_state'] : 0;
        $BA['room_area'] = isset($DA['room_area']) ? $DA['room_area'] : 0;
        if (isset($DA['rentable_date']) && isDate($DA['rentable_date'])) {
            $BA['rentable_date'] = $DA['rentable_date'];
        }
        $BA['remark'] = isset($DA['remark']) ? $DA['remark'] : "";
        $BA['pics'] = isset($DA['pics']) ? $DA['pics'] : "";
        $BA['detail'] = isset($DA['detail']) ? $DA['detail'] : "";
        return $BA;
    }
}
