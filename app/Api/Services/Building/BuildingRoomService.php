<?php

namespace App\Api\Services\Building;

use App\Api\Models\Building;
use App\Api\Models\BuildingRoom;

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
}
