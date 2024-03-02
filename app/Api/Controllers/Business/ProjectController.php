<?php

namespace App\Api\Controllers\Business;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;

use App\Api\Models\Project as ProjectModel;
use Illuminate\Support\Facades\DB;
use App\Models\Area as AreaModel;
use App\Api\Models\Sys\UserGroup as UserGroupModel;
use App\Api\Models\Building as BuildingModel;
use App\Api\Models\BuildingRoom as BuildingRoomModel;
use App\Api\Services\Building\BuildingService;
use App\Services\CompanyServices;


/**
 * 项目（园区）管理
 *
 */
class ProjectController extends BaseController
{

    public function __construct()
    {
        $this->uid  = auth()->payload()->get('sub');
        if (!$this->uid) {
            return $this->error('用户信息错误');
        }
        $this->company_id = getCompanyId($this->uid);
    }
    /**
     * @OA\Post(
     *     path="/api/business/project/list",
     *     tags={"项目"},
     *     summary="项目列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={},
     *       @OA\Property(
     *          property="proj_province_id",
     *          type="int",
     *          description="省份ID"
     *       ),
     *       @OA\Property(
     *          property="proj_city_id",
     *          type="int",
     *          description="城市ID"
     *       ),
     *       @OA\Property(
     *          property="proj_name",
     *          type="String",
     *          description="项目名称"
     *       )
     *     ),
     *       example={
     *              "proj_province_id": "","proj_city_id":""
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
        // return "ok";
        $pagesize = $request->input('pagesize');
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config('per_size');
        }
        if ($pagesize == '-1') {
            $pagesize = config('export_rows');
        }

        $map = array();
        if ($request->proj_province_id && $request->proj_province_id > 0) {
            $map['proj_province_id'] = $request->input('proj_province_id');
        }

        if ($request->input('city_id') && $request->input('city_id') > 0) {
            $map['proj_city_id'] = $request->input('city_id');
        }
        if ($request->input('is_valid')) {
            $map['is_valid'] = $request->input('is_valid');
        }

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

        $subMap['room_type'] = 1;

        DB::enableQueryLog();
        // 获取项目信息
        $data = ProjectModel::where($map)
            ->where(function ($q) use ($request) {
                $request->proj_name && $q->where('proj_name', 'like', '%' . $request->proj_name . '%');
                $request->proj_ids && $q->whereIn('id', $request->proj_ids);
                $request->is_valid &&  $q->where('is_valid', $request->is_valid);
                $request->proj_type &&  $q->where('proj_type', $request->proj_type);
            })
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();

        $data = $this->handleBackData($data);
        //通过项目获取房间信息 并进行数据合并

        foreach ($data['result'] as $k => &$v) {
            $result =  BuildingModel::where('proj_id', $v['id'])
                ->withCount(['buildRoom'  => function ($q) use ($subMap) {
                    $q->where($subMap);
                }])
                // 统计空闲房间
                ->withCount(['buildRoom as free_room_count' => function ($q) use ($subMap) {
                    $q->where($subMap);
                    $q->where('room_state', 1);
                }])
                ->whereHas('buildRoom', function ($q) use ($request, $subMap) {
                    if ($request->free_room_count) {
                        $q->havingRaw('count(*) = ?', [$request->free_room_count]);
                        $q->where($subMap);
                        $q->where('room_state', 1);
                    }
                })
                //统计管理房间面积
                ->withCount(['buildRoom as total_area' => function ($q)  use ($subMap) {
                    $q->select(DB::raw("sum(room_area)"));
                    $q->where($subMap);
                }])
                //统计空闲房间面积
                ->withCount(['buildRoom as free_area' => function ($q) use ($subMap) {
                    $q->select(DB::raw("sum(room_area)"));
                    $q->where('room_state', 1);
                    $q->where($subMap);
                }])->get()->toArray();

            $DA = array(
                'total_room_count' => 0, // 总的房源数
                'free_room_count' => 0, //空闲房源数
                'manager_area' => 0.00,  // 管理面积（所有房源面积总和）
                'free_area' => 0.00
            );  // 空闲面积
            foreach ($result as $kr => $vr) {
                $DA['total_room_count'] += $vr['build_room_count'];
                $DA['free_room_count']  += $vr['free_room_count'];
                $DA['manager_area']     += $vr['total_area'];
                $DA['free_area']        += $vr['free_area'];
            }
            $v['build_room_count']   = $DA['total_room_count'];
            $v['free_room_count']    = $DA['free_room_count'];
            $v['total_area']         = numFormat($DA['manager_area']);
            $v['free_area']          = numFormat($DA['free_area']);
        }
        // return response()->json(DB::getQueryLog());
        $buildingService = new BuildingService;
        $data['stat'] = $buildingService->getBuildingAllStat($data['result']);
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/business/project/add",
     *     tags={"项目"},
     *     summary="项目新增",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"proj_name","proj_addr"},
     *       @OA\Property(
     *          property="proj_name",
     *          type="String",
     *          description="项目名称"
     *       ),
     *       @OA\Property(
     *          property="proj_addr",
     *          type="String",
     *          description="项目地址 省份城市区域加详细地址"
     *       )
     *     ),
     *       example={
     *              "proj_name": "","proj_addr":""
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
            'proj_name' => 'required|String|max:64',
        ]);
        $data = $request->toArray();

        $projCheck = ProjectModel::where('proj_name', $data['proj_name'])->count();
        if ($projCheck > 0) {
            return $this->error($data['proj_name'] . '项目名称重复');
        }
        // 判断公司的项目是否到达限制数量
        $companyServices = new CompanyServices;
        if ($companyServices->checkProjCount($this->company_id)) {
            return  $this->error('已达到最大项目数量，如有需要请联系商务！');
        }

        $data = $this->formatProj($data);
        $data['company_id'] = $this->company_id;
        $data['c_uid'] = $this->uid;
        $project = ProjectModel::create($data);

        if ($project) {
            return $this->success($data['proj_name'] . '项目添加成功！');
        } else {
            return $this->error($data['proj_name'] . '项目添加失败！');
        }
    }
    /**
     * @OA\Post(
     *     path="/api/business/project/edit",
     *     tags={"项目"},
     *     summary="项目编辑",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id","proj_name"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="项目ID"
     *       ),
     *       @OA\Property(
     *          property="proj_name",
     *          type="String",
     *          description="项目名称"
     *       )
     *     ),
     *       example={
     *              "id": "","proj_name":""
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
            'id'    => 'required|min:1',
            'proj_name' => 'required|String|max:64',
        ]);
        $data = $request->toArray();
        // $data['company_id'] = $this->company_id;

        $map['proj_name'] = $data['proj_name'];
        $map['company_id'] = $this->company_id;

        $projCheck = ProjectModel::where($map)
            ->where('id', '!=', $request['id'])->count();
        if ($projCheck > 0) {
            return $this->error($data['proj_name'] . '项目名称重复');
        }

        $DA = $this->formatProj($data);
        $DA['u_uid'] = $this->uid;
        $project = ProjectModel::whereId($request['id'])->update($DA);
        if ($project) {
            return $this->success($DA['proj_name'] . '项目更新成功！');
        } else {
            return $this->error($DA['proj_name'] . '项目更新失败！');
        }
    }


    /**
     * @OA\Post(
     *     path="/api/business/project/del",
     *     tags={"项目"},
     *     summary="项目删除",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="项目ID"
     *       )
     *     ),
     *       example={
     *              "id": ""
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
            'id'    => 'required|int',
        ]);

        $checkBuilding = BuildingModel::where('proj_id', $request->id)->exists();
        if ($checkBuilding) {
            return $this->error('项目下有楼宇不允许删除！');
        }
        $res = ProjectModel::whereId($request->id)->delete();
        if ($res) {
            return $this->success('项目删除成功。');
        } else {
            return $this->error('项目删除失败！');
        }
    }



    /**
     * @OA\Post(
     *     path="/api/business/project/show",
     *     tags={"项目"},
     *     summary="根据项目id获取信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"projectId"},
     *       @OA\Property(
     *          property="projectId",
     *          type="int",
     *          description="项目Id"
     *       )
     *     ),
     *       example={"projectId": 11}
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
        $projectId = $request->id;
        // DB::enableQueryLog();

        DB::enableQueryLog();
        $data = ProjectModel::with('building')->find($request->id)->toArray();
        // return $data;
        $rooms =  BuildingModel::where('proj_id', $request->id)
            ->withCount('buildRoom')
            ->withCount(['buildRoom as free_room_count' => function ($q) {
                $q->where('room_state', 1);
            }])
            ->withCount(['buildRoom as manager_area' => function ($q) {
                $q->select(DB::raw("sum(room_area)"));
            }])
            ->withCount(['buildRoom as free_area' => function ($q) {
                $q->select(DB::raw("sum(room_area)"));
                $q->where('room_state', 1);
            }])
            ->get()->toArray();
        // return $rooms;
        $room_count = 0;
        $free_room = 0;
        $manager_area = 0;
        $free_area = 0;
        foreach ($rooms as $k => $v) {
            $room_count += $v['build_room_count'];
            $free_room += $v['free_room_count'];
            $manager_area += $v['manager_area'];
            $free_area += $v['free_area'];
        }
        $data['room_count'] = $room_count;
        $data['free_room'] = $free_room;
        $data['manager_area'] = $manager_area;
        $data['free_area'] = $free_area;

        // dump(response()->json(DB::getQueryLog()));

        return $this->success($data);
    }


    /**
     * @OA\Post(
     *     path="/api/business/project/enable",
     *     tags={"项目"},
     *     summary="项目启用禁用",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="项目ID"
     *       )
     *     ),
     *       example={
     *              "id": ""
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
            'Ids'    => 'required|array',
            'is_valid' => 'required|in:0,1',
        ]);

        $res = ProjectModel::whereIn('id', $request->Ids)->update(["is_valid" => $request->is_valid]);
        if ($res) {
            return $this->success('项目更新成功。');
        } else {
            return $this->error('项目更新失败！');
        }
    }



    /**
     *  格式化传入数据
     */
    private function formatProj($data)
    {
        $DA['proj_name'] = $data['proj_name'];
        $DA['proj_type'] = isset($data['proj_type']) ? $data['proj_type'] : "";
        $DA['proj_logo'] = isset($data['proj_logo']) ? $data['proj_logo'] : "";
        if (isset($data['proj_province_id']) && $data['proj_province_id'] > 0) {
            $res = AreaModel::find($data['proj_province_id']);
            $DA['proj_province'] = $res->name;
            $DA['proj_province_id'] = $data['proj_province_id'];
        }
        if (isset($data['proj_city_id']) && $data['proj_city_id'] > 0) {
            $res = AreaModel::find($data['proj_city_id']);
            $DA['proj_city'] = $res->name;
            $DA['proj_city_id'] = $data['proj_city_id'];
        }
        if (isset($data['proj_district_id']) && $data['proj_district_id'] > 0) {
            $res = AreaModel::find($data['proj_district_id']);
            $DA['proj_district'] = $res->name;
            $DA['proj_district_id'] = $data['proj_district_id'];
        }
        $DA['water_price'] = isset($data['water_price']) ? $data['water_price'] : "0.00";
        $DA['electric_price'] = isset($data['electric_price']) ? $data['electric_price'] : "0.00";
        $DA['proj_addr'] = isset($data['proj_addr']) ? $data['proj_addr'] : "";
        $DA['proj_occupy'] = isset($data['proj_occupy']) ? $data['proj_occupy'] : 0;
        $DA['proj_buildarea'] = isset($data['proj_buildarea']) ? $data['proj_buildarea'] : 0;
        $DA['proj_usablearea'] = isset($data['proj_usablearea']) ? $data['proj_usablearea'] : 0;
        $DA['proj_far'] = isset($data['proj_far']) ? $data['proj_far'] : "";
        $DA['proj_pic'] = isset($data['proj_pic']) ? $data['proj_pic'] : "";
        $DA['support'] = isset($data['support']) ? $data['support'] : "";
        $DA['advantage'] = isset($data['advantage']) ? $data['advantage'] : "";
        $DA['is_valid'] = isset($data['is_valid']) ? $data['is_valid'] : 1;
        return $DA;
    }
}
