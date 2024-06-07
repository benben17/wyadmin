<?php

namespace App\Api\Controllers\Business;

use JWTAuth;
use Illuminate\Http\Request;
use App\Api\Models\BuildingRoom;

use App\Models\Area as AreaModel;
use App\Services\CompanyServices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Controllers\BaseController;
use App\Api\Models\Project as ProjectModel;
use App\Api\Models\Building as BuildingModel;
use App\Api\Services\Business\ProjectService;
use App\Api\Services\Building\BuildingService;



/**
 * 
 * Description 项目（园区）管理
 * @OA\Tag(
 *     name="项目",
 *     description="项目管理"
 * )
 */
class ProjectController extends BaseController
{
    private $projectService;
    public function __construct()
    {
        parent::__construct();
        $this->projectService = new ProjectService;
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
        $subMap['room_type'] = 1;
        $subMap['is_valid'] = 1;
        // 获取项目信息
        DB::enableQueryLog();
        // 获取项目数据
        $subQuery = $this->projectService->projectModel()->where($map);
        $subQuery->when($request->proj_name, function ($query) use ($request) {
            $query->where('proj_name', 'like', '%' . $request->proj_name . '%');
        });
        $subQuery->when($request->proj_ids, function ($query) use ($request) {
            $query->whereIn('id', $request->proj_ids);
        });
        $subQuery->when($request->proj_type, function ($query) use ($request) {
            $query->where('proj_type', $request->proj_type);
        });

        $projIds = $subQuery->pluck('id')->toArray();
        // 分页数据
        $data = $this->pageData($subQuery, $request);

        // 获取房间统计数据
        $roomStat = BuildingRoom::selectRaw('
             proj_id,
             count(id) as build_room_count,
             sum(room_area) as total_area,
             sum(case when room_state = 1 then 1 else 0 end) as free_room_count,
             sum(case when room_state = 1 then room_area else 0 end) as free_area
         ')->where($subMap)
            ->whereIn('proj_id', $projIds)
            ->groupBy('proj_id')
            ->get()
            ->toArray();
        $roomMap = array_column($roomStat, null, 'proj_id');
        // 将房间统计数据合并到项目数据
        $arr = [
            'build_room_count' => 0,
            'total_area' => 0,
            'free_room_count' => 0,
            'free_area' => 0,
        ];
        foreach ($data['result'] as &$item) {
            $item = array_merge($item, $roomMap[$item['id']] ?? $arr);
            unset($item['bill_instruction']); // 删除不需要的字段
        }

        $buildingService = new BuildingService;
        $data['stat'] = $buildingService->getBuildingAllStat($roomStat);
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
        ], [
            'proj_name.required' => '项目名称不能为空',
            'proj_name.max' => '项目名称最大长度为64',
            'proj_name.String' => '项目名称必须为字符串',
        ]);
        $data = $request->toArray();

        $projCheck = $this->projectService->projectModel()->where('proj_name', $data['proj_name'])->count();
        if ($projCheck > 0) {
            return $this->error($data['proj_name'] . '名称重复');
        }
        // 判断公司的项目是否到达限制数量
        $companyServices = new CompanyServices;
        if ($companyServices->checkProjCount($this->company_id)) {
            return  $this->error('项目已达到最大数量，如有需要请联系商务！');
        }

        $data = $this->projectService->formatProj($data);
        $data['company_id'] = $this->company_id;
        $data['c_uid'] = $this->uid;
        $project = $this->projectService->projectModel()->create($data);

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
     *       example={"id": "","proj_name":""}
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
        ], [
            'id.required' => '项目ID不能为空',
            'proj_name.required' => '项目名称不能为空',
            'proj_name.max' => '项目名称最大长度为64',
            'proj_name.String' => '项目名称必须为字符串',
        ]);
        $data = $request->toArray();

        $map['proj_name'] = $data['proj_name'];
        $map['company_id'] = $this->company_id;

        $projCheck = $this->projectService->projectModel()->where($map)
            ->where('id', '!=', $request['id'])->count();
        if ($projCheck > 0) {
            return $this->error($data['proj_name'] . '项目名称重复');
        }

        $DA = $this->projectService->formatProj($data);
        $DA['u_uid'] = $this->uid;
        $project = $this->projectService->projectModel()->whereId($request['id'])->update($DA);
        if ($project) {
            return $this->success($DA['proj_name'] . '项目更新成功！');
        } else {
            return $this->error($DA['proj_name'] . '项目更新失败！');
        }
    }


    /**
     * @OA\Post(
     *     path="/api/business/project/set",
     *     tags={"项目"},
     *     summary="项目账单内容编辑",
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
     *       example={"id": "","proj_name":""}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */

    public function billProjEdit(Request $request)
    {
        $validatedData = $request->validate([
            'id'    => 'required|min:1',
        ], [
            'id.required' => '项目ID不能为空',
            'id.min' => '项目ID不合法',
        ]);
        try {
            $this->projectService->setProject($request, $this->user);
            return $this->success('项目账单内容更新成功！');
        } catch (\Exception $e) {
            Log::error("项目账单设置失败." . $e->getMessage());
            return $this->error($e->getMessage());
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
     *          required={"id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="项目Id"
     *       )
     *     ),
     *       example={"id": 11}
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
        $data = ProjectModel::with('building')->find($request->id);
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
            ->get();

        $data['room_count'] = 0;
        $data['free_room'] = 0;
        $data['manager_area'] = 0;
        $data['free_area'] = 0;
        foreach ($rooms as $k => $v) {
            $data['room_count'] += $v['build_room_count'];
            $data['free_room'] += $v['free_room_count'];
            $data['manager_area'] += $v['manager_area'];
            $data['free_area'] += $v['free_area'];
        }
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

        $res = $this->projectService->projectModel()->whereIn('id', $request->Ids)
            ->update(["is_valid" => $request->is_valid]);
        if ($res) {
            return $this->success('项目更新成功。');
        } else {
            return $this->error('项目更新失败！');
        }
    }
}
