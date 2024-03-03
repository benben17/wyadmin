<?php

namespace App\Api\Services\Operation;


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Equipment\EquipmentPlan;
use App\Api\Models\Equipment\Equipment as EquipmentModel;
use App\Api\Models\Equipment\EquipmentMaintain as MaintainModel;
use App\Api\Services\Common\MessageService;
use DateTime;
use Exception;
use function Complex\add;

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
  /** 维护计划模型 */
  public function MaintainPlanModel()
  {
    return new EquipmentPlan;
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
      $equipment->maintain_period   = $DA['maintain_period'];
      $equipment->maintain_content  = isset($DA['maintain_content']) ? $DA['maintain_content'] : "";
      $equipment->maintain_times    = $DA['maintain_times'] ?? $this->getMaintainTimes($equipment->maintain_period, date('Y'));
      $equipment->save();
    } catch (Exception $e) {
      Log::error($e->getMessage());
    }
    return $equipment->id;
  }

  /**
   * 根据维护周期生成维护计划
   *
   * @Author leezhua
   * @DateTime 2024-02-26
   * @param [type] $equipment
   * @param [type] $maintainPeriod
   * @param [type] $user
   *
   * @return void
   */
  public function saveBatchMaintainPlan($equipmentId, $maintainPeriod, $user, $year)
  {
    $data = array();
    // Log::info($maintainPeriod);
    $equipment = $this->equipmentModel()->find($equipmentId);
    $maintenanceDates = $this->generateMaintenancePlan($maintainPeriod, $year);
    foreach ($maintenanceDates  as $date) {
      // Log::info($date);
      $plan = [
        'company_id'      => $user['company_id'],
        'c_uid'           => $user['id'],
        'c_username'      => $user['realname'],
        'plan_date'       => $date,
        'proj_id'         => $equipment['proj_id'],
        'device_name'     => $equipment['device_name'],
        'model'           => $equipment['model'],
        'major'           => $equipment['major'],
        'position'        => $equipment['position'],
        // 'maintain_period' => $equipment['maintain_period'],
        'equipment_id'    => $equipment['id'],
        // 'equipment_type'  => $equipment['equipment_type'],
        'quantity'        => $equipment['quantity'],
        'created_at'      => nowTime(),
      ];

      $data[] = $plan;
    }
    if ($data) {
      $this->maintainPlanModel()->addAll($data);
    }
  }

  /** 
   * 当维护 数量和 计划中数量相等的时候  更新维护数量和 维护计划状态
   */
  public function updateMaintainPlan($maintainId)
  {
    $maintain = $this->maintainModel()->find($maintainId);
    $maintainPlan = $this->MaintainPlanModel()->find($maintain['plan_id']);
    if (!$maintainPlan) {
      return false;
    }
    $maintainPlan->maintain_quantity = $maintainPlan->maintain_quantity + $maintain['quantity'];
    if ($maintainPlan->maintain_quantity >= $maintainPlan->quantity) {
      $maintainPlan->status = 1;
    }
    $maintainPlan->save();
    return true;
  }

  public function editMaintainPlan($maintainPlan)
  {
    $plan = $this->MaintainPlanModel()->find($maintainPlan['id']);

    $plan->fill([
      'plan_date'       => $maintainPlan['plan_date'],
      'device_name'     => $maintainPlan['device_name'],
      'model'           => $maintainPlan['model'],
      'major'           => $maintainPlan['major'],
      'position'        => $maintainPlan['position'],
      'equipment_id'    => $maintainPlan['equipment_id'],
      'equipment_type'  => $maintainPlan['equipment_type'],
      'quantity'        => $maintainPlan['quantity'],
      'updated_at'      => nowTime(),
    ]);

    return $plan->save();
  }

  /**
   * 设备维保保存
   * @Author   leezhua
   * @DateTime 2020-07-24
   * @param    [type]     $DA   [description]
   * @param    [type]     $user [description]
   * @return   [type]           [description]
   */
  public function saveEquipmentMaintain(array $DA, $user)
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
      $maintain->plan_id        = $DA['plan_id'];
      $maintain->equipment_id   = $DA['equipment_id'];
      $maintain->equipment_type = $DA['equipment_type'];
      $maintain->maintain_content = $DA['maintain_content'];
      $maintain->maintain_date  = $DA['maintain_date'];
      $maintain->maintain_person = $DA['maintain_person'];
      $maintain->quantity       =  $DA['quantity']  ?? $equipment['quantity'];
      $maintain->maintain_type  = $DA['maintain_type'];
      $maintain->pic            = isset($DA['pic']) ? $DA['pic'] : $equipment['pic'];
      $maintain->save();
      return $maintain->id;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }


  private function getMaintainTimes($maintainPeriod, $year): int
  {
    try {
      $plan = $this->generateMaintenancePlan($maintainPeriod, $year);
      return count($plan);
    } catch (\Exception $e) {
      // Log or handle the exception as needed
      return 0; // Return 0 if there's an issue with generating the maintenance plan
    }
  }

  // 函数用于计算指定周期内的最后一天日期
  public function getFirstDay($year, $month, $quarter = 0, $halfYear = false)
  {

    if ($quarter > 0) {
      $month = ($quarter - 1) * 3 + 1;
    }

    return date('Y-m-01', strtotime($year . '-' . $month . '-01'));
  }

  // 函数用于生成维护计划日期数组
  public function generateMaintenancePlan($maintenancePeriod, $year)
  {
    $maintenanceDates = [];

    switch ($maintenancePeriod) {
      case 1: // 周
        for ($i = 1; $i <= 52; $i++) {
          $firstDay = $this->getFirstDayOfWeek($year, $i);
          $maintenanceDates[] = $firstDay;
        }
        break;
      case 2: //月
        for ($i = 1; $i <= 12; $i++) {
          $lastDay = $this->getFirstDay($year, $i);
          $maintenanceDates[] = $lastDay;
        }
        break;
      case 3:  // 季度
        for ($i = 1; $i <= 4; $i++) {
          $lastDay = $this->getFirstDay($year, 0, $i);
          $maintenanceDates[] = $lastDay;
        }
        break;
      case 4: // 半年
        // 上半年
        $maintenanceDates[] = date('Y-01-01');
        // 下半年
        $maintenanceDates[] = date('Y-07-01');
        break;
      case 5: // 年
        $maintenanceDates[] =  date('Y-01-01');
        break;
      default:
        return "Invalid maintenance period.";
    }

    return $maintenanceDates;
  }

  public function getFirstDayOfWeek($year, $week)
  {
    $dto = new DateTime();
    $dto->setISODate($year, $week);
    // Set the day to Monday (1) to get the first day of the week
    $dto->modify('-' . ($dto->format('N') - 1) . ' days');
    return $dto->format('Y-m-d');
  }
}
