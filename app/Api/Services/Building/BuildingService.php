<?php

namespace App\Api\Services\Building;

use Exception;
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
   * 获取楼宇统计信息
   * @Author leezhua
   * @Date 2024-03-31
   * @param mixed $data 
   * @return array 
   */
  public function getBuildingAllStat($data)
  {
    $DA = array('t_manager_area' => 0, 't_free_are' => 0, 't_room_count' => 0, 't_free_count' => 0);
    foreach ($data as $k => $v) {
      $DA['t_manager_area'] += $v['total_area'];
      $DA['t_free_are']     += $v['free_area'];
      $DA['t_room_count']   += $v['build_room_count'];
      $DA['t_free_count']   += $v['free_room_count'];
    }

    $rentalRate = 0.00;
    $freeRate = 0.00;
    if ($DA['t_manager_area']) {
      $rentalRate = numFormat(($DA['t_manager_area'] - $DA['t_free_are']) / $DA['t_manager_area'] * 100);
      $freeRate = numFormat(($DA['t_free_are']) / $DA['t_manager_area'] * 100);
    }
    return [
      ['label' => '招商面积', 'value' => "{$DA['t_manager_area']} ㎡"],
      ['label' => '可招商面积', 'value' => "{$DA['t_free_are']} ㎡"],
      ['label' => '总房间数', 'value' => $DA['t_room_count']],
      ['label' => '可招商房间', 'value' => $DA['t_free_count']],
      ['label' => '当前出租率', 'value' => "$rentalRate %"],
      ['label' => '当前空闲率', 'value' => "$freeRate %"]
    ];
  }

  /**
   * 通过项目 楼的信息查询管理面积信息
   * @Author   leezhua
   * @DateTime 2020-07-04
   * @param    array      $buildingWhere [description]
   * @param    array      $projWhere     [description]
   * @return   [type]                    [description]
   */
  public function areaStat($buildingWhere = array(), $projWhere = array(), $buildIds = array())
  {
    $room = BuildingRoomModel::where(function ($q) use ($buildingWhere, $buildIds) {
      $buildingWhere && $q->where($buildingWhere);
      $buildIds && $q->whereIn('build_id', $buildIds);
    })
      ->whereHas('building', function ($q) use ($projWhere) {
        $projWhere && $q->whereIn('proj_id', $projWhere);
      })
      ->select(DB::Raw('ifnull(sum(room_area),0) total_area,
            ifnull(sum(case room_state when 1 then room_area end),0) free_area,
            count(*) as total_room,
            ifnull(sum(case room_state when 1 then 1  end),0) free_room'))
      ->first()->toArray();
    if ($room['free_area'] == 0) {
      $rentalRate = 0.00;
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
    } catch (Exception $e) {
      throw new Exception("更新房间错误：" . $e->getMessage());
      Log::error($e->getMessage());
    }
    return $res;
  }

  /**
   * 格式化房源list返回数据
   *
   * @Author leezhua
   * @DateTime 2021-07-19
   * @param [type] $data
   *
   * @return void
   */
  public function formatData($data): array
  {
    foreach ($data as $k => &$v) {
      $viewNum = TenantRoom::select(DB::Raw('ifnull(count(*),0) as  count'))
        ->where('room_id', $v['id'])
        ->first()->count;
      $v['view_num']  = $viewNum;
      $v['IsValid']   = $this->getIsValid($v['is_valid']);
      $v['Status']    = $this->getStatus($v['channel_state']);
      $v['roomState'] = $this->getRoomState($v['room_state']);
      $v['proj_name'] = $v['building']['proj_name'];
      $v['build_no']  = $v['building']['build_no'];
      $v['floor_no']  = $v['floor']['floor_no'];
      $v['pic_list_full'] = [];
      foreach ($v['pic_list'] ?? [] as $key => $val) {
        $v['pic_list_full'][$key] = getOssUrl($val);
      }
    }
    return $data;
  }



  public function getIsValid($value)
  {
    switch ($value) {
      case '1':
        return '启用';
        break;
      case '0':
        return '禁用';
        break;
    }
  }

  public function getStatus($value)
  {
    switch ($value) {
      case '1':
        return '公开';
        break;
      case '0':
        return '不公开';
        break;
    }
  }

  public function getRoomState($value)
  {
    switch ($value) {
      case '1':
        return '可招商';
        break;
      case '0':
        return '在租';
        break;
    }
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
      $build->proj_name = $row[0];
      $build->build_no = $row[1];
      $build->build_type = $row[3];
      $build->build_usage = $row[4];

      $build->build_certificate = $row[5];
      $build->floor_height  = $row[6];
      $build->build_area = $row[7];
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
