<?php

namespace App\Api\Services\Operation;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Common\QrcodeService;
use App\Api\Services\Operation\WorkOrderService;
use App\Api\Models\Equipment\Inspection as InspectionModel;
use App\Api\Models\Equipment\InspectionRecord as InspectionRecordModel;

/**
 * 巡检服务
 */
class InspectionService
{
  // 巡检点
  public function inspectionModel()
  {
    return new InspectionModel;
  }

  /** 巡检 */
  public function inspectionRecordModel()
  {
    return new InspectionRecordModel;
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
      DB::transaction(function () use ($DA, $user) {
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
        $inspection->save();

        // 生成二维码
        if (!isset($DA['id']) || $DA['id'] == 0) {
          $this->createQr($inspection->id, $inspection->id, $user['company_id']);
        }
      }, 2);
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
      DB::transaction(function () use ($DA, $user) {
        // DB::enableQueryLog();
        if (isset($DA['id']) && $DA['id'] > 0) {
          $record = $this->inspectionRecordModel()->find($DA['id']);
          if (!$record) {
            Log::info("巡检记录不存在!");
          }
        } else {
          $record = $this->inspectionRecordModel();
          $record->company_id   = $user['company_id'];
          $record->c_uid        = $user['id'];
          $record->c_username   = $user['realname'];
        }
        // Log::info(json_encode($record->toArray()));
        $record->proj_id        = $DA['proj_id'];
        $record->inspection_id  = $DA['inspection_id'];
        $record->is_unusual     = $DA['is_unusual'];
        $record->pic            = isset($DA['pic']) ? $DA['pic'] : "";
        $record->record         = isset($DA['record']) ? $DA['record'] : "";
        // 
        $record->save();
        if ($DA['is_unusual'] == 2) { // 异常
          $workOrder = new WorkOrderService;
          $DA['pic']            = $record->pic;
          $DA['repair_content'] = $DA['record'];
          $DA['remark']         = "巡检异常";
          unset($DA['id']);
          $workOrder->saveWorkOrder($DA, $user);
          // 更新主表状态
          $this->inspectionModel()->whereId($DA['inspection_id'])->update(['status' => 2]);
        }
      }, 2);
      return true;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception("巡检记录保存失败!");
    }
  }

  /**
   * 巡检点生成二维码
   * @Author   leezhua
   * @DateTime 2021-05-27
   * @param    [type]     $inspection_id [description]
   * @param    [type]     $content          [description]
   * @param    [type]     $company_id    [description]
   * @return   [type]                    [description]
   */
  public function createQr($inspection_id, $content, $company_id)
  {
    $qrcodeService = new QrcodeService;
    $data = $this->inspectionModel()->find($inspection_id);
    $qrcode = $qrcodeService->createQr($content, $company_id);
    if ($qrcode) {
      $data->qr_code      = $qrcode;
      return $data->save();
    } else {
      return false;
    }
  }



  public function delInspection($ids)
  {
    try {
      DB::transaction(function () use ($ids) {
        $idsArray = str2Array($ids);
        $this->inspectionModel()->whereIn('id', $idsArray)->delete();
        $this->inspectionRecordModel()->whereIn('inspection_id', $idsArray)->delete();
      }, 2);
    } catch (Exception $e) {
      Log::error("删除失败" . $e->getMessage());
      throw new Exception("删除失败!");
    }
  }
}
