<?php

namespace App\Api\Controllers\Business;

use Common;
use JWTAuth;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Controllers\BaseController;
use App\Api\Excel\Business\BuildingExcel;
use App\Api\Models\Contract\ContractRoom;
use App\Api\Models\Project as ProjectModel;
use App\Api\Models\Building as BuildingModel;
use App\Api\Services\Building\BuildingService;
use App\Api\Models\BuildingFloor as FloorModel;
use App\Api\Models\BuildingRoom as BuildingRoomModel;


/**
 * @OA\Tag(
 *     name="楼宇管理",
 *     description="楼宇管理"
 * )
 */
class BuildingController extends BaseController
{
    private $buildService;
    public function __construct()
    {
        parent::__construct();
        $this->buildService = new BuildingService;
    }

    /**
     * @OA\Post(
     *     path="/api/business/building/list",
     *     tags={"楼宇管理"},
     *     summary="楼宇列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *       @OA\Property(
     *          property="pagesize",
     *          type="int",
     *          description="行数"
     *       ),
     *       @OA\Property(
     *          property="proj_id",
     *          type="int",
     *          description="项目ID"
     *       ),
     *       @OA\Property(
     *          property="build_no",
     *          type="String",
     *          description="楼号"
     *       ),
     *       @OA\Property(
     *          property="is_valid",
     *          type="int",
     *          description="1启用 2 禁用"
     *       )
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
    public function index(Request $request)
    {

        $map = array();

        if ($request->is_valid) { //是否可用
            $map['is_valid'] = $request->is_valid;
        }

        if ($request->build_id) { // 楼号ID
            $map['build_id'] = $request->build_id;
        }
        if ($request->room_type) {
            $subMap['room_type'] = $request->room_type;
        } else {
            $subMap['room_type'] = 1;
        }
        if ($request->room_state) {
            $subMap['room_state'] = $request->room_state;
        }
        DB::enableQueryLog();
        $subQuery = BuildingModel::where($map)
            ->where(function ($q) use ($request) {
                $request->build_no && $q->where('build_no', 'like', '%' . $request->build_no . '%');
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
            })
            ->withCount('floor')

            //统计所有房间
            ->withCount(['buildRoom' => function ($q) use ($subMap) {
                $q->where($subMap);
            }])
            // 统计空闲房间
            ->withCount(['buildRoom as free_room_count' => function ($q) use ($subMap) {
                $q->where($subMap);
                $q->where('room_state', 1);
            }])
            //统计管理房间面积
            ->withCount(['buildRoom as total_area' => function ($q) use ($subMap) {
                $q->where($subMap);
                $q->select(DB::raw('ifnull(sum(room_area),"0.00")'));
            }])
            //统计空闲房间面积
            ->withCount(['buildRoom as free_area' => function ($q) use ($subMap) {
                $q->select(DB::raw('ifnull(sum(room_area),"0.00")'));
                $q->where($subMap);
                $q->where('room_state', 1);
            }]);
        $pageQuery = clone $subQuery;
        $data = $this->pageData($pageQuery, $request);
        if ($request->export) {
            return $this->exportToExcel($data['result'], BuildingExcel::class);
        }

        $list = $subQuery->get();
        $buildingService = new BuildingService;
        $data['stat'] = $buildingService->getBuildingAllStat($list);
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/business/building/add",
     *     tags={"楼宇管理"},
     *     summary="楼宇新增",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"proj_id", "build_no","floor"},
     *       @OA\Property(
     *          property="proj_id",
     *          type="int",
     *          description="项目ID"
     *       ),
     *       @OA\Property(
     *          property="build_no",
     *          type="String",
     *          description="楼号"
     *       ),
     *       @OA\Property(
     *          property="floor",
     *          type="list",
     *          description="层号"
     *       )
     *     ),
     *       example={
     *              "proj_id": "1", "build_no": "1号楼","floor": "[{}]"
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
            'proj_id' => 'required|numeric|gt:0',
            'proj_name' => 'required|String|min:1',
            'build_no' => 'required|String|min:1',
            'floors' => 'required|array',
        ]);
        $map['proj_id'] = $request->proj_id;
        $map['build_no'] = $request->build_no;

        $checkBuilding = BuildingModel::where($map)->exists();
        if ($checkBuilding) {
            return $this->error('楼号重复！请核对后添加！');
        }

        $DA = $request->toArray();
        try {
            // DB::enableQueryLog();
            DB::transaction(function () use ($DA) {
                $building = $this->formatBuild($DA);
                $building['company_id'] = $this->company_id;
                $building['c_uid'] = $this->uid;
                $res = BuildingModel::Create($building);
                if ($res && !empty($DA['floors'])) {
                    $build_id = $res->id;
                    $floors = $this->formatFloor($DA['floors'], $build_id, $DA['proj_id']);
                    $buildingFloor = new FloorModel;
                    $buildingFloor->addAll($floors);
                }
            }, 2);
            return $this->success('楼宇保存成功。');
        } catch (Exception $e) {
            Log::error("楼宇保存失败" . $e->getMessage());
            return $this->error($DA['build_no'] . '楼宇保存失败');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/business/building/edit",
     *     tags={"楼宇管理"},
     *     summary="楼宇编辑",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id","proj_id", "build_no","floors"},
     *       @OA\Property(
     *          property="proj_id",
     *          type="int",
     *          description="项目ID"
     *       ),
     *       @OA\Property(
     *          property="build_no",
     *          type="String",
     *          description="楼号"
     *       ),
     *       @OA\Property(
     *          property="floors",
     *          type="list",
     *          description="层号"
     *       )
     *     ),
     *       example={
     *              "proj_id": "1", "build_no": "1号楼","floors": "[{}]"
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
            'proj_id' => 'required|numeric|gt:0',
            'build_no' => 'required|String|min:1',
            'floors' => 'required|array'
        ]);
        $map['proj_id'] = $request->proj_id;
        $map['build_no'] = $request->build_no;

        // DB::enableQueryLog();
        $checkBuilding = BuildingModel::where($map)
            ->where('id', '!=', $request->id)->exists();
        // return response()->json(DB::getQueryLog());
        if ($checkBuilding) {
            return $this->error('楼宇数据重复');
        }
        $DA = $request->toArray();
        try {
            // DB::enableQueryLog();
            DB::transaction(function () use ($DA) {
                $data = $this->formatBuild($DA);
                $data['u_uid'] = $this->uid;
                // DB::enableQueryLog();
                BuildingModel::whereId($DA['id'])->update($data);
                // 编辑楼层
                if ($DA['floors']) {
                    $floors = $this->formatFloor($DA['floors'], $DA['id'], $DA['proj_id'], 2);
                    foreach ($floors as $k => $v) {
                        if (isset($v['id']) && $v['id'] > 0) {
                            FloorModel::whereId($v['id'])->update($v);
                        } else {
                            FloorModel::Create($v);
                        }
                    }
                }
            });
            // return response()->json(DB::getQueryLog());
            return $this->success('数据更新成功');
        } catch (Exception $e) {
            return $this->error('数据更新失败');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/business/building/del",
     *     tags={"楼宇管理"},
     *     summary="楼宇删除",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id",},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="楼宇id"
     *       )
     *     ),
     *       example={
     *              "id": "1"
     *           }
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
            'id' => 'required|numeric|gt:0',
        ]);
        $checkRoom = BuildingRoomModel::where('build_id', $request->id)->exists();
        // return response()->json(DB::getQueryLog());
        if ($checkRoom) {
            return $this->error('楼宇下有层或者房间不允许删除');
        }
        try {
            // DB::enableQueryLog();
            DB::transaction(function () use ($request) {
                BuildingModel::whereId($request->id)->delete();
                FloorModel::where('build_id', $request->id)->delete();
            });
            // return response()->json(DB::getQueryLog());
            return $this->success('楼层删除成功。');
        } catch (Exception $e) {
            return $this->error('楼层删除失败！');
        }
    }


    /**
     * @OA\Post(
     *     path="/api/business/building/show",
     *     tags={"楼宇管理"},
     *     summary="通过ID楼宇查看",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="楼ID"
     *       )
     *     ),
     *       example={
     *              "id": 1
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

        DB::enableQueryLog();
        $buildId = $request->id;
        // 获取楼宇信息
        $building = BuildingModel::find($buildId)->toArray();
        // 获取楼层信息
        $floors = FloorModel::where('build_id', $buildId)
            ->withCount(['floorRoom' => function ($q) {
                $q->where('room_type', 1);
            }])
            ->withCount(['floorRoom as room_area' => function ($q) {
                $q->select(DB::Raw('ifnull(sum(room_area),0.00)'));
                $q->where('room_type', 1);
            }])
            ->get();
        // return response()->json(DB::getQueryLog());
        if ($building && $floors) {
            $building['floors'] = $floors;
            return $this->success($building);
        }
        return $this->error('查询失败');
    }

    /**
     * @OA\Post(
     *     path="/api/business/building/floor/del",
     *     tags={"楼宇管理"},
     *     summary="通过ID删除楼层",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"Ids","build_id"},
     *       @OA\Property(
     *          property="Ids",
     *          type="list",
     *          description="楼层ID"
     *       ),
     *       @OA\Property(
     *          property="build_id",
     *          type="int",
     *          description="楼栋ID"
     *       )
     *     ),
     *       example={
     *              "Ids": "[]","build_id":""
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function delFloor(Request $request)
    {
        $validatedData = $request->validate([
            'Ids' => 'required|array',
        ]);
        try {
            DB::transaction(function () use ($request) {
                $checkRoom = FloorModel::select('id')
                    ->withCount('floorRoom')
                    ->whereIn('id', $request->Ids)->get()->toArray();

                foreach ($checkRoom as $k => $v) {
                    if ($v['floor_room_count'] == 0) { // 有房间不允许删除
                        FloorModel::whereId($v['id'])->delete();
                    }
                }
            });
            return $this->success('楼层删除成功');
        } catch (Exception $e) {
            return $this->error('楼层删除失败！');
        }
    }

    /**
     * @OA\Post(
     *   path="/api/business/building/floor/list",
     *   tags={"楼宇管理"},
     *   summary="通过楼宇ID楼层查看",
     *   @OA\RequestBody(
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         schema="UserModel",
     *         required={"build_id"},
     *         @OA\Property(
     *           property="build_id",
     *           type="integer",
     *           description="楼号ID"
     *         ),
     *         @OA\Property(
     *           property="proj_id",
     *           type="integer",
     *           description="项目ID"
     *         )
     *       ),
     *       example={"build_id": 1, "proj_id": 1}
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description=""
     *   )
     * )
     */
    public function listFloor(Request $request)
    {
        $validatedData = $request->validate([
            'build_id' => 'required|numeric|gt:0',
        ]);

        if ($request->proj_id) {
            $buildIds = BuildingModel::where('proj_id', $request->proj_id)
                ->pluck('id')
                ->toArray();
        } else {
            $buildIds = explode(",", $request->build_id);
        }
        // DB::enableQueryLog();

        $data = FloorModel::with('building')
            ->whereIn('build_id', $buildIds)
            ->get();
        // return response()->json(DB::getQueryLog());

        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/business/building/stat",
     *     tags={"租控管理"},
     *     summary="租控管理统计",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={},
     *       @OA\Property(
     *          property="build_id",
     *          type="int",
     *          description="楼号ID"
     *       ),
     *       @OA\Property( property="proj_ids",type="list",description="项目IDs")
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
    public function buildingStat(Request $request)
    {
        $validatedData = $request->validate([
            'proj_ids' => 'required|array',
        ]);

        $building = BuildingModel::select(DB::Raw('group_concat(id) build_ids'))
            ->where(function ($q) use ($request) {
                $request->build_no && $q->where('build_no', 'like', '%' . $request->build_no . '%');
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
            })
            ->first()->toArray();

        $buildIds = explode(",", $building['build_ids']);

        // return response()->json(DB::getQueryLog());
        // $map['company_id'] = $this->company_id;

        $data = FloorModel::with('building')
            ->withCount('floorRoom')
            ->with('floorRoom')
            ->where(function ($q) use ($request) {
                $request->floor_no && $q->where('floor_no', 'like', $request->floor_no);
            })
            ->whereIn('build_id', $buildIds)
            ->get();

        // return $data;
        $BA = [];
        DB::enableQueryLog();
        foreach ($data as $k => $v) {
            $BA[$k] = [
                'building_name' => $v['building']['build_no'] ?? '',
                'floor_no' => $v['floor_no'] ?? '',
                'room_count' => $v['floor_room_count'] ?? 0,
                'room_list' => [],
            ];

            foreach ($v->floorRoom as $k1 => &$v1) {
                $room_list = [
                    'id' => $v1->id,
                    'room_state' => $v1->room_state,
                    'room_area' => $v1->room_area,
                    'room_type' => $v1->room_type,
                    'room_no' => $v1->room_no,
                    'tenant_name' => "",
                ];

                if ($v1->room_state === 0) {
                    $contractRoom = ContractRoom::where('room_id', $v1->id)->with('contract')->groupBy('created_at')->first();
                    $room_list['tenant_name'] = $contractRoom->contract->tenant_name ?? "";
                }

                $BA[$k]['room_list'][$k1] = $room_list;
            }
        }
        // return response()->json(DB::getQueryLog());
        $map = array();

        $DA['data'] = $BA;
        $DA['stat'] = $this->buildService->areaStat($map, $request->proj_ids, $buildIds);
        return $this->success($DA);
    }




    /**
     * 格式化楼层数据
     * @Author   leezhua
     * @DateTime 2020-06-01
     * @param    [数组]     $data       [楼数据]
     * @param    [int]     $buildingId [build_id]
     * @param    [int]     $projId     [项目ID]
     * @param    integer    $type       [1 新增2 编辑]
     * @return   [数组]                 [返回格式化数组]
     */
    private function formatFloor($data, $buildingId, $projId, $type = 1): array
    {
        foreach ($data as $k => $v) {
            if ($type == 1) {
                $BA[$k]['c_uid'] = $this->uid;
            } else {
                (isset($v['id']) && $v['id'] > 0) ? $BA[$k]['id'] = $v['id'] : '';
                $BA[$k]['u_uid'] = $this->uid;
            }
            $BA[$k]['proj_id']    = $projId;
            $BA[$k]['floor_no']   = $v['floor_no'];
            $BA[$k]['build_id']   = $buildingId;
            $BA[$k]['company_id'] = $this->company_id;
            $BA[$k]['created_at'] = nowYmd();
        }
        return $BA;
    }
    private function formatBuild($DA)
    {
        $proj = ProjectModel::select('proj_name')->find($DA['proj_id'])->toArray();
        $build['proj_id'] = $DA['proj_id'];
        $build['proj_name'] = $proj['proj_name'];
        $build['build_type'] = isset($DA['build_type']) ? $DA['build_type'] : "";
        $build['build_no'] = $DA['build_no'];
        $build['build_certificate'] = isset($DA['build_certificate']) ? $DA['build_certificate'] : "";
        $build['build_block'] = isset($DA['build_block']) ? $DA['build_block'] : "";
        $build['build_area'] = isset($DA['build_area']) ? $DA['build_area'] : 0;
        if (isset($DA['build_date'])) {
            $build['build_date'] =  $DA['build_date'];
        }
        $build['build_usage'] = isset($DA['build_usage']) ? $DA['build_usage'] : "";
        $build['floor_height'] = isset($DA['floor_height']) ? $DA['floor_height'] : 0;
        $build['remark'] = isset($DA['remark']) ? $DA['remark'] : "";
        return $build;
    }
}
