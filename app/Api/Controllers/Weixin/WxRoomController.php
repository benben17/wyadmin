<?php

namespace App\Api\Controllers\Weixin;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;

use Illuminate\Support\Facades\DB;

use App\Api\Models\BuildingRoom  as RoomModel;
use App\Api\Models\Project as ProjectModel;
use App\Api\Models\Building as BuildingModel;
use App\Api\Models\Tenant\Tenant as TenantModel;
use App\Api\Services\Contract\ContractService;
use App\Api\Services\Building\BuildingService;


/**
 * 项目房源信息
 */
class WxRoomController extends BaseController
{

    public function __construct()
    {
        // Token 验证
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
            ->where('is_vaild', 1)
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
        $pagesize = $request->input('pagesize');
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config('per_size');
        }
        if ($pagesize == '-1') {
            $pagesize = config('export_rows');
        }
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
                $request->room_no && $q->where('room_no', 'like', '%' . $request->room_no . '%');
                $request->is_vaild && $q->where('is_vaild', $request->is_vaild);
                $request->min_area && $q->where('room_area', '>=', $request->min_area);
                $request->max_area && $q->where('room_area', '<=', $request->max_area);
                $request->min_price && $q->where('room_price', '>=', $request->min_price);
                $request->max_price && $q->where('room_price', '<=', $request->max_price);
            })
            ->whereHas('building', function ($q) use ($request) {
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
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
            ->with('project')
            ->find($request->id)->toArray();
        DB::enableQueryLog();


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
     *              "Ids":"[1]","is_vaild":"0"
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
}
