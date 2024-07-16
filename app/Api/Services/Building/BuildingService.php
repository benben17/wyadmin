<?php

namespace App\Api\Services\Building;

use Exception;
use App\Enums\AppEnum;
use App\Api\Models\Building;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;
use App\Api\Models\Tenant\TenantRoom;
use App\Api\Services\Contract\ContractService;
use App\Api\Models\BuildingRoom as BuildingRoomModel;

/**
 * 楼宇服务类
 * Description
 * @package App\Api\Services\Building
 */
class BuildingService
{

  /**
   * 获取房间模型
   * @Author leezhua
   * @Date 2024-03-31
   * @return BuildingRoomModel 
   */
  public function RoomModel()
  {
    return new BuildingRoomModel();
  }

  /**
   * 获取楼宇信息
   * @Author leezhua
   * @Date 2024-03-31
   * @return array BuildingModel 
   */
  public function BuildingModel()
  {
    return new Building();
  }

  /**
   * 通过项目 楼的信息 查询租赁信息
   * @Author   leezhua
   * @DateTime 2020-07-04
   * @param    array      $buildingWhere [description]
   * @param    array      $projWhere     [description]
   * @return   [type]                    [description]
   */
  public function areaStatAll(array $projIds, $buildIds)
  {
    $map['is_valid'] = 1; // 启用房间
    $map['room_type'] = 1; // 房间
    $room = BuildingRoomModel::where(function ($q) use ($buildIds, $map) {
      // $buildingWhere && $q->where($buildingWhere);
      $buildIds && $q->whereIn('build_id', $buildIds);
      $q->where($map);
    })
      ->whereHas('building', function ($q) use ($projIds) {
        $projIds && $q->whereIn('proj_id', $projIds);
      })
      ->select(DB::Raw('ifnull(sum(room_area),0) total_area,
            ifnull(sum(case room_state when 1 then room_area end),0) free_area,
            count(*) as total_room,
            ifnull(sum(case room_state when 1 then 1  end),0) free_room'))
      ->whereIn('room_state', [0, 1])
      ->first()->toArray();
    if ($room['free_area'] == 0) {
      $rentalRate = 100.00;
    } else {
      $rentalRate = numFormat($room['free_area'] / $room['total_area'] * 100);
    }
    $contract =  new ContractService;
    $price = $contract->contractAvgPrice();

    $stat = array(
      ['title' => '可租面积',   'value' => $room['total_area'] . '㎡'],
      ['title' => '可招租面积',  'value' => $room['free_area'] . '㎡'],
      ['title' => '总房间数',  'value' => $room['total_room']],
      ['title' => '可招租房间数', 'value' => $room['free_room']],
      ['title' => '当前空置率',    'value' => $rentalRate . '%'],
      ['title' => '平均单价',  'value' => $price . '元/㎡·天']
    );
    return $stat;
  }

  /** 合同审核或者租户退租的时候更新房间状态 */
  public function updateRoomState($roomIds, $roomState)
  {
    try {
      $roomIds = str2Array($roomIds);
      $res = BuildingRoomModel::whereIn('id', $roomIds)->update(['room_state' => $roomState]);
      return $res;
    } catch (Exception $e) {
      Log::error("更新房间错误：" . $e->getMessage());
      throw new Exception("更新房间错误：" . $e->getMessage());
    }
  }


  /**
   * 获取楼宇统计信息
   * @Author leezhua
   * @Date 2024-03-31
   * @param mixed $data 
   * @return array 
   */
  public function getBuildingAllStat($data)
  {
    $totalArea  = 0;
    $freeArea   = 0;
    $totalRooms = 0;
    $freeRooms  = 0;

    foreach ($data as $v) {

      $totalArea  += $v['total_area'];
      $freeArea   += $v['free_area'];
      $totalRooms += $v['build_room_count'];
      $freeRooms  += $v['free_room_count'];
    }

    $rentalRate = $totalArea ? numFormat(($totalArea - $freeArea) / $totalArea * 100) : 0.00;
    $freeRate = $totalArea ? numFormat($freeArea / $totalArea * 100) : 0.00;

    return [
      ['label' => '招商面积', 'value' => "{$totalArea} " . AppEnum::squareMeterUnit],
      ['label' => '可招商面积', 'value' => "{$freeArea} " . AppEnum::squareMeterUnit],
      ['label' => '总房间数', 'value' => $totalRooms],
      ['label' => '可招商房间', 'value' => $freeRooms],
      ['label' => '当前出租率', 'value' => "{$rentalRate} " . AppEnum::percentUnit],
      ['label' => '当前空闲率', 'value' => "{$freeRate} " . AppEnum::percentUnit]
    ];
  }


  public function formatBuilding($row, $companyId)
  {
    try {
      $build = new Building;
      $build->company_id = $companyId;
      if (getProjIdByName($row[0])) {
        $build->proj_id =  getProjIdByName($row[0]);
      } else {
        return false;
      }
      $build->proj_name         = $row[0];
      $build->build_no          = $row[1];
      $build->build_type        = $row[3];
      $build->build_usage       = $row[4];
      $build->build_certificate = $row[5];
      $build->floor_height      = $row[6];
      $build->build_area        = $row[7];
      if (isDate($row[8])) {
        $build->build_date =  $row[8];
      }
      $build->remark = $row[9];
      return $build;
    } catch (Exception $th) {
      Log::error("保存失败" . json_encode($row));
      return false;
    }
  }
}
