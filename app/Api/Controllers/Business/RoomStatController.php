<?php

namespace App\Api\Controllers\Business;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


use App\Api\Models\BuildingRoom  as RoomModel;



/**
 * 房源统计分析
 */
class RoomStatController extends BaseController
{


    public function __construct()
    {
        $this->uid  = auth()->payload()->get('sub');
        if (!$this->uid) {
            return $this->error('用户信息错误');
        }
    }

    /**
     * 房源统计
     */
    /**
     * @OA\Post(
     *     path="/api/business/stat/room/stat",
     *     tags={"统计"},
     *     summary="房源按照面积区间进行统计",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"room_type"},
     *       @OA\Property(property="proj_id",type="int",description="项目ID"),
     *       @OA\Property(property="build_id",type="int",description="楼宇ID"),
     *       @OA\Property(property="room_type",type="int",description="1房源 2 工位")
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
    public function roomStat(Request $request)
    {
        $validatedData = $request->validate([
            'room_type' => 'required|int|in:1,2',

        ]);
        $subMap = array();
        $map = array();
        if ($request->room_type) {
            $map['room_type'] = $request->room_type;
        }
        $areaType = RoomModel::select(DB::Raw('
            sum(case when room_area > 0 and room_area <=100 then 1 else 0 end)  "0-100",
            sum(case when room_area > 100 and room_area <=200 then 1 else 0 end) "100-200",
            sum(case when room_area > 200 and room_area <=300 then 1 else 0 end) "200-300",
            sum(case when room_area > 300 and room_area <=400 then 1 else 0 end) "300-400",
            sum(case when room_area > 400 and room_area <=500 then 1 else 0 end) "400-500",
            sum(case when room_area > 500 and room_area <=700 then 1 else 0 end)  "500-700",
            sum(case when room_area > 700 and room_area <=1000 then 1 else 0 end) "700-1000",
            sum(case when room_area > 1000 and room_area <=1300 then 1 else 0 end) "1000-1300",
            sum(case when room_area > 1300  then 1 else 0 end) 1300以上,
            room_state'))
            ->whereHas('building', function ($q) use ($request) {
                $request->id && $q->whereId($request->id);
                $request->proj_ids &&  $q->whereIn('proj_id', $request->proj_ids);
            })
            ->where($map)
            ->groupBy('room_state')
            ->get()->toArray();
        $areaTypeStat = array();
        foreach ($areaType as $k => $v) {
            $i = 0;
            foreach ($v as $kr => $vr) {
                // Log::info($i);
                if ($kr == 'room_state') {
                    continue;
                }
                $areaTypeStat[$i]['label'] = $kr;
                if ($v['room_state'] == 1) {
                    $areaTypeStat[$i]['free_count'] = $vr;
                } else if ($v['room_state'] == 0) {
                    $areaTypeStat[$i]['used_count'] = $vr;
                }
                $i++;
            }
        }

        foreach ($areaTypeStat as $k => &$v) {
            if (isset($v['used_count']) && isset($v['free_count'])) {
                $v['total'] = $v['used_count'] + $v['free_count'];
            } else if (!isset($v['used_count']) && !isset($v['free_count'])) {
                $v['total'] = 0;
                $v['used_count'] = 0;
                $v['free_count'] = 0;
            } else if (!isset($v['used_count'])) {
                $v['total'] = $v['free_count'];
                $v['used_count'] = 0;
            } else if (!isset($v['free_count'])) {
                $v['free_count'] = 0;
                $v['total'] = $v['used_count'];
            }
        }

        $data['stat'] = $areaTypeStat;
        /** 统计空闲房间占比 空闲面积占比 */
        $room_area_data = RoomModel::select(DB::Raw('sum(room_area) area,count(*) room_count,room_state'))
            ->whereHas('building', function ($q) use ($request) {
                $request->id && $q->whereId($request->id);
                $request->proj_ids &&  $q->whereIn('proj_id', $request->proj_ids);
            })->where('room_type', 1)
            ->groupBy('room_state')
            ->get()->toArray();
        $areaStat = array('free_count' => 0, 'free_area' => 0, 'used_count' => 0, 'used_area' => 0);
        foreach ($room_area_data as $k => $v) {
            if ($v['room_state'] == 1) {
                $areaStat['free_count'] = $v['room_count'];
                $areaStat['free_area'] = numFormat($v['area']);
            } else {
                $areaStat['used_count'] = $v['room_count'];
                $areaStat['used_area'] = numFormat($v['area']);
            }
        }
        if (!$areaStat['free_area']) {
            $areaStat['free_area_rate'] = '0' . '%';
        } else {
            $areaStat['free_area_rate'] = numFormat($areaStat['free_area'] / ($areaStat['free_area'] + $areaStat['used_area']) * 100) . '%';
        }
        if (!$areaStat['free_count']) {
            $areaStat['free_room_count_rate'] = '0%';
        } else {
            $areaStat['free_room_count_rate'] = numFormat($areaStat['free_count'] / ($areaStat['free_count'] + $areaStat['used_count']) * 100) . '%';
        }


        $areaStat['total_area'] = $areaStat['free_area'] + $areaStat['used_area'];
        $data['areaStat'] = $areaStat;

        $stationStat = RoomModel::select(DB::Raw('sum(case room_state when 1 then 1 else 0 end) free_count,sum(case room_state when 0 then 1  else 0 end) used_count,count(*) total_count'))
            ->whereHas('building', function ($q) use ($request) {
                $request->id && $q->whereId($request->id);
                $request->proj_ids &&  $q->whereIn('proj_id', $request->proj_ids);
            })->where('room_type', 2)
            ->first();

        if (!$stationStat['free_count']) {
            $stationStat['free_rate'] = '0%';
        } else {
            $stationStat['free_rate'] = numFormat($stationStat['free_count'] / $stationStat['total_count'] * 100) . '%';
        }

        $data['station'] = $stationStat;

        $data['trim'] = RoomModel::where($map)
            ->selectRaw('count(*) count,room_trim_state trim_state')
            ->groupBy('trim_state')->get();
        return $this->success($data);
    }
}
