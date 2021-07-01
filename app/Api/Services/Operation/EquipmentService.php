<?php

namespace App\Api\Services\Operation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Models\Equipment\Equipment as EquipmentModel;
use App\Api\Models\Equipment\EquipmentMaintain as MaintainModel;
use App\Api\Services\Common\MessageService;

/**
 * 工单服务
 */
class EquipmentService
{
  public function equipmentModel()
  {
    $model = new EquipmentModel;
    return $model;
  }

  /** 设备维保模型 */
  public function maintainModel()
  {
    $model = new MaintainModel;
    return $model;
  }

  /**
   * 保修设备信息保存
   * @Author   leezhua
   * @DateTime 2020-07-24
   * @param    [type]     $DA   [description]
   * @param    [type]     $user [description]
   * @return   [type]           [description]
   */
  public function saveEquipment($DA, $user)
  {
    try {
      if (isset($DA['id']) && $DA['id'] > 0) {
        $equipment = $this->equipmentModel()->find($DA['id']);
        $equipment->u_uid = $user['id'];
      } else {
        $equipment = $this->equipmentModel();
        $equipment->company_id = $user['company_id'];
        $equipment->c_uid = $user['id'];
      }
      $equipment->proj_id           = $DA['proj_id'];
      $equipment->system_name       = isset($DA['system_name']) ? $DA['system_name'] : "";
      $equipment->position          = isset($DA['position']) ? $DA['position'] : "";
      $equipment->major             = isset($DA['major']) ? $DA['major'] : "";
      $equipment->position          = isset($DA['position']) ? $DA['position'] : "";
      $equipment->device_name       = isset($DA['device_name']) ? $DA['device_name'] : "";
      $equipment->model             = isset($DA['model']) ? $DA['model'] : "";
      $equipment->quantity          = isset($DA['quantity']) ? $DA['quantity'] : 0;
      $equipment->unit              = isset($DA['unit']) ? $DA['unit'] : "";
      $equipment->maintain_period   = isset($DA['maintain_period']) ? $DA['maintain_period'] : "";
      $equipment->maintain_content  = isset($DA['maintain_content']) ? $DA['maintain_content'] : "";
      $equipment->maintain_times    = isset($DA['maintain_times']) ? $DA['maintain_times'] : "";
      $res = $equipment->save();
    } catch (Exception $e) {
      Log::error($e->getMessage());
    }
    return $res;
  }
  /**
   * 设备维保保存
   * @Author   leezhua
   * @DateTime 2020-07-24
   * @param    [type]     $DA   [description]
   * @param    [type]     $user [description]
   * @return   [type]           [description]
   */
  public function saveEquipmentMaintain($DA, $user)
  {
    try {
      $equipment = $this->equipmentModel()->find($DA['equipment_id']);
      if (!$equipment) {
        return false;
      }
      if (isset($DA['id']) && $DA['id'] > 0) {
        $maintain = $this->maintainModel()->find($DA['id']);
        $maintain->u_uid = $user['id'];
      } else {
        $maintain = $this->maintainModel();
        $maintain->company_id   = $user['company_id'];
        $maintain->c_uid        = $user['id'];
        $maintain->c_username   = $user['realname'];
      }
      $maintain->proj_id        = $equipment['proj_id'];
      $maintain->device_name    = $equipment['device_name'];
      $maintain->model          = $equipment['model'];
      $maintain->major          = $equipment['major'];
      $maintain->position       = $equipment['position'];
      $maintain->maintain_period = $equipment['maintain_period'];

      $maintain->equipment_id   = $DA['equipment_id'];
      $maintain->equipment_type = $DA['equipment_type'];
      $maintain->maintain_content = $DA['maintain_content'];
      $maintain->maintain_date  = $DA['maintain_date'];
      $maintain->maintain_person = $DA['maintain_person'];
      $maintain->quantity       = isset($DA['quantity']) ? $DA['quantity'] : $equipment['quantity'];
      $maintain->maintain_type  = $DA['maintain_type'];
      $maintain->pic            = isset($DA['pic']) ? $DA['pic'] : $equipment['pic'];
      $res = $maintain->save();
    } catch (Exception $e) {
      Log::error($e->getMessage());
    }
    return $res;
  }
}
