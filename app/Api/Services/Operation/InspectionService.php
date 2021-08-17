<?php

namespace App\Api\Services\Operation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Equipment\Inspection as InspectionModel;
use App\Api\Models\Equipment\InspectionRecord as InspectionRecordModel;
use App\Api\Services\Common\MessageService;
use App\Api\Services\Operation\WorkOrderService;
use App\Api\Services\Common\QrcodeService;

/**
 * 巡检服务
 */
class InspectionService
{
  // 巡检点
  public function inspectionModel()
  {
    $model = new InspectionModel;
    return $model;
  }

  /** 巡检 */
  public function inspectionRecordModel()
  {
    $model = new InspectionRecordModel;
    return $model;
  }

  /**
   * 巡检点保存以及编辑
   * @Author   leezhua
   * @DateTime 2020-07-24
   * @param    [type]     $DA   [description]
   * @param    [type]     $user [description]
   * @return   [type]           [description]
   */
  public function saveInspection($DA, $user)
  {
    try {

      if (isset($DA['id']) && $DA['id'] > 0) {
        $inspection = $this->inspectionModel()->find($DA['id']);
      } else {
        $inspection = $this->inspectionModel();
        $inspection->company_id = $user['company_id'];
        $inspection->c_uid = $user['id'];
      }
      $inspection->proj_id      = $DA['proj_id'];
      $inspection->name         = $DA['name'];
      $inspection->type         = isset($DA['type']) ? $DA['type'] : 1;
      // $inspection->major        = $DA['major'];
      $inspection->device_name  = isset($DA['device_name']) ? $DA['device_name'] : "";
      $inspection->position     = isset($DA['position']) ? $DA['position'] : "";
      $inspection->check_cycle  = isset($DA['check_cycle']) ? $DA['check_cycle'] : 1;
      $inspection->rfid_id      = isset($DA['rfid_id']) ? $DA['rfid_id'] : "";
      $inspection->remark       = isset($DA['remark']) ? $DA['remark'] : "";
      $res = $inspection->save();

      // 生成二维码
      if (!isset($DA['id']) || $DA['id'] == 0) {
        $this->createQr($inspection->id, $inspection->id, $user['company_id']);
      }
      return true;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception("巡检点保存失败!");
    }
  }
  /**
   * 巡检记录保存
   * @Author   leezhua
   * @DateTime 2020-07-24
   * @param    [type]     $DA   [description]
   * @param    [type]     $user [description]
   * @return   [type]           [description]
   */
  public function saveInspectionRecord($DA, $user)
  {
    try {
      if (isset($DA['id']) && $DA['id'] > 0) {
        $record = $this->inspectionRecordModel()->find($DA['id']);
      } else {
        $record = $this->inspectionRecordModel();
        $record->company_id   = $user['company_id'];
        $record->c_uid        = $user['id'];
        $record->c_username   = $user['realname'];
      }
      $record->proj_id        = $DA['proj_id'];
      $record->inspection_id     = $DA['inspection_id'];
      $record->is_unusual     = $DA['is_unusual'];
      $record->pic            = isset($DA['pic']) ? $DA['pic'] : "";
      $record->record         = isset($DA['record']) ? $DA['record'] : "";
      if ($DA['is_unusual'] == 0) {
        $workOrder = new WorkOrderService;
        $inspection = $this->inspectionModel()->find($DA['inspection_id'])->toArray();
        $inspection['repair_content'] = isset($DA['record']) ? $DA['record'] : "";
        $inspection['pic'] = $record->pic;
        $workOrder->saveWorkOrder($DA, $user);
        // 更新主表状态
        $this->updateInspectionStatus($DA['inspection_id'], $DA['is_unusual']);
      }
      $res = $record->save();
    } catch (Exception $e) {
      Log::error($e->getMessage());
    }
    return $res;
  }

  /**
   * 巡检点生成二维码
   * @Author   leezhua
   * @DateTime 2021-05-27
   * @param    [type]     $inspection_id [description]
   * @param    [type]     $info          [description]
   * @param    [type]     $company_id    [description]
   * @return   [type]                    [description]
   */
  public function createQr($inspection_id, $info, $company_id)
  {
    $qrcodeService = new QrcodeService;
    $data = $this->inspectionModel()->find($inspection_id);
    $qrcode = $qrcodeService->createQr($info, $company_id);
    if ($qrcode) {
      $data->qr_code      = $qrcode;
      $res = $data->save();
      return $res;
    } else {
      return false;
    }
  }

  public function updateInspectionStatus($id, $status)
  {
    $data = $this->inspectionModel()->find($id);
    $data->status = $status;
    $res = $data->save();
    return $res;
  }
}
