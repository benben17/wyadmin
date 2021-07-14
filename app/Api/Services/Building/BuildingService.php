<?php

namespace App\Api\Services\Building;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

use App\Api\Models\Building as BuildingModel;
use App\Api\Models\BuildingRoom as BuildingRoomModel;
use App\Api\Models\Contract\ContractRoom;
use App\Api\Models\Tenant\TenantRoom;
use App\Api\Services\Contract\ContractService;

/**
 *
 */
class BuildingService
{

  public function getBuildingAllStat($data)
  {
    $DA = array('t_manager_area' => 0, 't_free_are' => 0, 't_room_count' => 0, 't_free_count' => 0);
    foreach ($data as $k => $v) {
      $DA['t_manager_area'] += $v['total_area'];
      $DA['t_free_are']     += $v['free_area'];
      $DA['t_room_count']   += $v['build_room_count'];
      $DA['t_free_count']   += $v['free_room_count'];
    }
    $stat[] = array('label' => '管理面积', 'value' => $DA['t_manager_area'] . ' ㎡');
    $stat[] = array('label' => '可招商面积', 'value' => $DA['t_free_are'] . " ㎡");
    $stat[] = array('label' => '总房间数', 'value' => $DA['t_room_count']);
    $stat[] = array('label' => '可招商房间', 'value' => $DA['t_free_count']);

    if (!$DA['t_manager_area']) {
      $rentalRate = 0;
      $freeRate = 0;
    } else {
      $rentalRate = sprintf("%.2f", ($DA['t_manager_area'] - $DA['t_free_are']) / $DA['t_manager_area'] * 100, 2);
      $freeRate = sprintf("%.2f", ($DA['t_free_are']) / $DA['t_manager_area'] * 100, 2);
    }
    $stat[] = array('label' => '计租率', 'value' => $rentalRate . ' %');
    $stat[] = array('label' => '空闲率', 'value' => $freeRate . ' %');

    return $stat;
  }

  /**
   * 通过项目 楼的信息查询管理面积信息
   * @Author   leezhua
   * @DateTime 2020-07-04
   * @param    array      $buildingWhere [description]
   * @param    array      $projWhere     [description]
   * @return   [type]                    [description]
   */
  public function areaStat($buildingWhere = array(), $projWhere = array())
  {
    $room = BuildingRoomModel::where($buildingWhere)
      ->whereHas('building', function ($q) use ($projWhere) {
        $projWhere && $q->whereIn('proj_id', $projWhere);
      })
      ->select(DB::Raw('ifnull(sum(room_area),0) total_area,
            ifnull(sum(case room_state when 1 then room_area end),0) free_area,
            count(*) as total_room,
            ifnull(sum(case room_state when 1 then 1  end),0) free_room'))
      ->first()->toArray();
    if (!$room['free_area']) {
      $rentalRate = 0;
    } else {
      $rentalRate = numFormat($room['free_area'] / $room['total_area'] * 100);
    }
    $contract =  new ContractService;
    $price = $contract->contractAvgPrice();

    $stat = array(
      ['title' => '总面积',   'value' => $room['total_area'] . '㎡'],
      ['title' => '空闲面积',  'value' => $room['free_area'] . '㎡'],
      ['title' => '总房间数',  'value' => $room['total_room']],
      ['title' => '空闲房间数', 'value' => $room['free_room']],
      ['title' => '空闲率',    'value' => $rentalRate . '%'],
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
      throw $e->getMessage();
      Log::error($e->getMessage());
    }
    return $res;
  }

  public function formatData($data)
  {
    foreach ($data as $k => &$v) {
      $num = ContractRoom::select(DB::Raw('ifnull(count(*),0) count'))
        ->where('room_id', $v['id'])
        ->first();
      $v['view_num']  = $num['count'];
      $v['IsVaild']   = $this->getIsVaild($v['is_vaild']);
      $v['Status']    = $this->getStatus($v['channel_state']);
      $v['roomState'] = $this->getRoomState($v['room_state']);
      $v['proj_name'] = $v['building']['proj_name'];
      $v['build_no']  = $v['building']['build_no'];
      $v['floor_no']  = $v['floor']['floor_no'];
    }
    return $data;
  }



  public function getIsVaild($value)
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
}
