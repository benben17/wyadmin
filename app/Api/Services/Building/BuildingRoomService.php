<?php

namespace App\Api\Services\Building;

use App\Enums\AppEnum;
use App\Api\Models\Building;
use App\Api\Models\BuildingRoom;
use App\Api\Models\BuildingFloor;
use Illuminate\Support\Facades\DB;
use App\Api\Models\Tenant\TenantRoom;
use App\Api\Services\Contract\ContractService;

class BuildingRoomService
{
  public function buildingModel()
  {
    return new Building;
  }
  public function buildingRoomModel()
  {
    return new BuildingRoom;
  }

  public function floorModel()
  {
    return new BuildingFloor;
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
  public function formatRoomData($data): array
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
      $contractService = new ContractService;
      $v['tenant_name'] = $contractService->getTenantNameFromRoomId($v['id']);
      // foreach ($v['pic_list'] ?? [] as $key => $val) {
      //   $v['pic_list_full'][$key] = getOssUrl($val);
      // }
    }
    return $data;
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
      $freeRate   = numFormat(($DA['t_free_are']) / $DA['t_manager_area'] * 100);
    }

    return array(
      ['label' => '招商面积', 'value' => "{$DA['t_manager_area']} " . AppEnum::squareMeterUnit],
      ['label' => '可招商面积', 'value' => "{$DA['t_free_are']} " . AppEnum::squareMeterUnit],
      ['label' => '总房间数', 'value' => $DA['t_room_count']],
      ['label' => '可招商房间', 'value' => $DA['t_free_count']],
      ['label' => '当前出租率', 'value' => "{$rentalRate} " . AppEnum::percentUnit],
      ['label' => '当前空闲率', 'value' => "{$freeRate} " . AppEnum::percentUnit]
    );
  }

  /**
   * 通过项目 楼的信息查询管理面积信息
   * @Author   leezhua
   * @DateTime 2020-07-04
   * @param    array      $buildingWhere [description]
   * @param    array      $projWhere     [description]
   * @return   [type]                    [description]
   */
  public function areaStat($query, $buildIds = array())
  {
    // $room = BuildingRoomModel::where(function ($q) use ($buildingWhere, $buildIds) {
    //   $buildingWhere && $q->where($buildingWhere);
    //   $buildIds && $q->whereIn('build_id', $buildIds);
    // })
    //   ->whereHas('building', function ($q) use ($projWhere) {
    //     $projWhere && $q->whereIn('proj_id', $projWhere);
    //   })
    $room = $query->select(DB::Raw('ifnull(sum(room_area),0) total_area,
            ifnull(sum(case room_state when 1 then room_area end),0) free_area,
            count(*) as total_room,
            ifnull(sum(case room_state when 1 then 1  end),0) free_room'))
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
  // 格式化房间数据
  public function formatRoom($DA, $user, $type = 1)
  {
    $BA = [];
    if ($type == 1) {
      $BA['c_uid'] = $user['id'];
    } else {
      $BA['id']    = $DA['id'];
      $BA['u_uid'] = $user['id'];
    }
    $BA['room_type']         = 1;
    $BA['company_id']        = $user['company_id'];
    $BA['proj_id']           = $DA['proj_id'];
    $BA['build_id']          = $DA['build_id'];
    $BA['floor_id']          = $DA['floor_id'];
    $BA['room_no']           = $DA['room_no'];
    $BA['room_state']        = $DA['room_state'];
    $BA['room_measure_area'] = isset($DA['room_measure_area']) ? $DA['room_measure_area'] : 0;
    $BA['room_trim_state']   = isset($DA['room_trim_state']) ? $DA['room_trim_state'] : "";
    $BA['room_price']        = isset($DA['room_price']) ? $DA['room_price'] : 0.00;
    $BA['price_type']        = isset($DA['price_type']) ? $DA['price_type'] : 1;
    $BA['room_tags']         = isset($DA['room_tags']) ? $DA['room_tags'] : "";
    $BA['channel_state']     = isset($DA['channel_state']) ? $DA['channel_state'] : 0;
    $BA['room_area']         = isset($DA['room_area']) ? $DA['room_area'] : 0;
    $BA['rentable_date'] = $DA['rentable_date'] ?? "";
    $BA['remark']        = isset($DA['remark']) ? $DA['remark'] : "";
    $BA['pics']          = isset($DA['pics']) ? $DA['pics'] : "";
    $BA['detail']        = isset($DA['detail']) ? $DA['detail'] : "";
    return $BA;
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
}
