<?php

namespace App\Api\Controllers\Weixin;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Api\Controllers\BaseController;

use App\Api\Models\Project as ProjectModel;
use App\Api\Models\BuildingRoom  as RoomModel;
use App\Api\Services\Building\BuildingService;
use App\Api\Services\Building\BuildingRoomService;

/**
 * 项目房源信息
 */
class WxRoomController extends BaseController
{

    public function __construct()
    {
        // Token 验证
        parent::__construct();
        // $this->middleware('jwt.api.auth');
        // $this->uid  = auth()->payload()->get('sub');
        // if (!$this->uid) {
        //     return $this->error('用户信息错误');
        // }
        // $this->company_id = getCompanyId($this->uid);
    }

    /**
     * @OA\Post(
     *     path="/api/business/wx/project/list",
     *     tags={"微信招商房源"},
     *     summary="获取所有项目信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize","proj_id","build_id","orderBy","order"},
     *     ),
     *       example={
     *          "pagesize":"10","proj_id":"1","build_id":"1","orderBy":"id","order":"desc"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function wxGetProj(Request $request)
    {
        $data = ProjectModel::select('id', 'proj_name', 'proj_logo', 'proj_pic')
            // ->when($request->limit, function ($q) use ($DA) {
            //     if (!$DA['is_admin']) {
            //         $q->whereIn('id', $DA['proj_limit']);
            //     }
            // })
            ->where('is_valid', 1)
            ->get()->toArray();

        return $this->success($data);
        // return response()->json(DB::getQueryLog());
    }

    /**
     * @OA\Post(
     *     path="/api/business/wx/room/list",
     *     tags={"微信招商房源"},
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

        $map['room_state'] = 1;

        if ($request->room_type) { // 1 房间 2 工位
            $map['room_type'] = $request->room_type;
        } else {
            $map['room_type'] = 1;
        }

        DB::enableQueryLog();
        $subQuery = RoomModel::where($map)
            ->where(function ($q) use ($request) {
                $request->room_no && $q->where('room_no', 'like', '%' . $request->room_no . '%');
                $request->is_valid && $q->where('is_valid', $request->is_valid);
                $request->min_area && $q->where('room_area', '>=', $request->min_area);
                $request->max_area && $q->where('room_area', '<=', $request->max_area);
                $request->min_price && $q->where('room_price', '>=', $request->min_price);
                $request->max_price && $q->where('room_price', '<=', $request->max_price);
            })
            ->whereHas('building', function ($q) use ($request) {
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
            })
            ->with('building:id,proj_name,build_no,proj_id')
            ->with('floor:id,floor_no');


        // return response()->json(DB::getQueryLog());
        $data = $this->pageData($subQuery, $request);
        $buildService  = new BuildingRoomService;

        if ($data['result']) {
            $data['result'] = $buildService->formatRoomData($data['result']);
        }

        // $data['stat'] = $buildService->areaStat($map, $request->proj_ids);
        return $this->success($data);
    }


    /**
     * @OA\Post(
     *     path="/api/business/wx/room/show",
     *     tags={"微信招商房源"},
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
            $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        })
            ->with('building:id,proj_name,build_no,proj_id')
            ->with('floor:id,floor_no')
            ->find($request->id)->toArray();
        DB::enableQueryLog();

        $project = ProjectModel::find($data['building']['proj_id']);
        $data['project'] = $project;
        // $contract = new ContractService;
        // $data['contract'] = $contract->getContractByRoomId($request->id);

        // return response()->json(DB::getQueryLog());
        // $data = $this->handleBackData($result);
        return $this->success($data);
    }



    /**
     * @OA\Post(
     *     path="/api/business/wx/rooms",
     *     tags={"微信招商房源"},
     *     summary="房源启用禁用",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"Ids","is_valid"},
     *       @OA\Property(
     *          property="Ids",
     *          type="list",
     *          description="房源ID集合"
     *       ),
     *       @OA\Property(
     *          property="is_valid",
     *          type="int",
     *          description="0禁用1 启用"
     *       )
     *
     *     ),
     *       example={
     *              "Ids":"[1]","is_valid":"0"
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
        $request->validate([
            'Ids' => 'required|array',
            'is_valid' => 'required|numeric|in:0,1',
            // 'is_valid' => 'required|numeric|in:0,1',
        ], [
            'Ids.required' => '房源ID不能为空',
            'is_valid.required' => '启用禁用状态不能为空',
            'is_valid.in' => '启用禁用状态不正确',
            'Ids.array' => '房源ID格式不正确',
        ]);

        $subQuery = RoomModel::where(function ($q) use ($request) {
            $request->Ids && $q->whereIn('id', $request->Ids);
            $request->is_valid && $q->where('is_valid', $request->is_valid);
        })
            ->whereHas('building', function ($q) use ($request) {
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
            });
        $data = $subQuery->with('building:id,proj_name,build_no,proj_id')
            ->with('floor:id,floor_no')
            ->get();

        $roomStat = $subQuery->selectRaw('min(room_area) min_area,
                max(room_area) max_area,
                avg(room_price) avg_price')->first();
        $roomStat['avg_price'] = numFormat($roomStat['avg_price']);
        $result['stat'] = $roomStat;
        $result['rooms'] = $data;
        return $this->success($result);
    }
}
