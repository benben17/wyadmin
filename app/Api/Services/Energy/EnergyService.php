<?php

namespace App\Api\Services\Energy;

use App\Api\Models\Contract\Contract;
use App\Api\Models\Contract\ContractRoom;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

use App\Api\Models\Energy\Meter as MeterModel;
use App\Api\Models\Energy\MeterRecord as MeterRecordModel;
use App\Api\Models\Energy\MeterLog as MeterLogModel;
use App\Api\Services\Common\QrCodeService;
use App\Api\Services\Tenant\TenantService;
use App\Api\Services\Bill\TenantBillService;
use App\Enums\AppEnum;

/**
 *能耗服务
 *remark parent_type 3
 */
class EnergyService
{
  var $parent_type = 3;
  /** 表模型 */
  public function meterModel()
  {
    $meterModel =  new MeterModel;
    return $meterModel;
  }
  // 抄表模型
  public function meterRecordModel()
  {
    $model =  new MeterRecordModel;
    return $model;
  }


  /** 水表电表保存 */
  public function saveMeter($DA, $user)
  {
    // Log::error(json_encode($DA));
    try {
      DB::transaction(function () use ($DA, $user) {
        $is_add = false;
        if (isset($DA['id']) && $DA['id'] > 0) {
          $meter  = MeterModel::find($DA['id']);
          $meter->u_uid = $user['id'];
        } else {
          $meter  = new MeterModel;
          $meter->company_id = $user['company_id'];
          $meter->c_uid      = $user['id'];
          $meter->parent_id  = isset($DA['parent_id']) ? $DA['parent_id'] : 0;
          $is_add = true;
        }
        $meter->tenant_id    = isset($DA['tenant_id']) ? $DA['tenant_id'] : 0;
        if ($meter->tenant_id == 0) {
          $tenant = $this->getTenantByRoomId($DA['room_id'] ?? 0);
          $meter->tenant_id = $tenant['id'];
        }
        $meter->type         = $DA['type'];
        $meter->proj_id      = $DA['proj_id'];
        $meter->meter_no     = $DA['meter_no'];
        $meter->build_no     = isset($DA['build_no']) ? $DA['build_no'] : "";
        $meter->floor_no     = isset($DA['floor_no']) ? $DA['floor_no'] : "";
        $meter->room_no      = isset($DA['room_no']) ? $DA['room_no'] : "";
        $meter->build_id     = isset($DA['build_id']) ? $DA['build_id'] : 0;
        $meter->floor_id     = isset($DA['floor_id']) ? $DA['floor_id'] : 0;
        $meter->room_id      = isset($DA['room_id']) ? $DA['room_id'] : 0;
        $meter->position     = isset($DA['position']) ? $DA['position'] : "";
        $meter->multiple     = $DA['multiple'] ?? 1;
        $meter->price        = isset($DA['price']) ? $DA['price'] : 0.00;
        $meter->is_vaild     = isset($DA['is_vaild']) ? $DA['is_vaild'] : 1;
        $meter->master_slave = $DA['master_slave']; // 总表还是子表 统计用量的时候只统计总表
        $meter->detail       = isset($DA['detail']) ? $DA['detail'] : "";
        $res = $meter->save();
        $DA['meter_id'] = $meter->id;
        if ($is_add && $res) {
          $DA['meter_id'] = $meter->id;
          $this->initRecord($DA, $user, $is_add);
        }
        if ($is_add) {
          $DA['id']  = $meter->id;
          $meter->qrcode_path = $this->createQrCode($meter->id, $user['company_id']);
          $meter->save();
        }
        $res = $this->saveMeterLog($DA, $user);
        if (!$res) {
          throw new Exception("能耗日志保存失败！");
        }
      });
      return true;
    } catch (Exception $e) {
      Log::error("save meter :" . $e);
      return false;
    }
  }


  public function createQrCode($meterId, $companyId)
  {
    $qrCode = new QrCodeService;
    return $qrCode->createQr($meterId, $companyId);
  }

  /** 保存抄表记录 以及新加表初始化 */
  public function initRecord($DA, $user, $is_add = false)
  {
    try {
      if (isset($DA['id']) && $DA['id'] > 0) {
        $meterRecord  = MeterRecordModel::find($DA['id']);
      } else {
        $meterRecord  = new MeterRecordModel;
        $meterRecord->company_id = $user['company_id'];
        $meterRecord->c_uid      = $user['id'];
        $meterRecord->c_username = $user['realname'];
      }

      $meterRecord->meter_id   = $DA['meter_id'];
      $meterRecord->tenant_id  = isset($DA['tenant_id']) ? $DA['tenant_id'] : 0;
      // $meterRecord->pre_value  = isset($DA['pre_value']) ? $DA['pre_value'] :0;
      if (isset($DA['pre_date'])) {
        $meterRecord->pre_date  = $DA['pre_date'];
      }
      if ($DA['tenant_id'] > 0) {
        $tenant = new TenantService;
        $meterRecord->tenant_name = $tenant->getTenantById($DA['tenant_id']);
      } else {
        $meterRecord->tenant_name = '公区';
      }
      $meterRecord->pre_value = isset($DA['meter_value']) ? $DA['meter_value'] : 0;
      $meterRecord->meter_value = isset($DA['meter_value']) ? $DA['meter_value'] : 0;
      $meterRecord->used_value  = isset($DA['use_value']) ? $DA['use_value'] : 0;
      $meterRecord->record_date = isset($DA['record_date']) ? $DA['record_date'] : date('Y-m-d', time());
      $meterRecord->pic = isset($DA['pic']) ? $DA['pic'] : "";
      $meterRecord->audit_user = isset($DA['audit_user']) ? $DA['audit_user'] : "";
      if ($is_add) {
        $meterRecord->remark = date('Y-m-d', time()) . '初始化';
        $meterRecord->status = 1;  //  标识初始化
      }
      return  $meterRecord->save();
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception("表记录增加失败");

      return false;
    }
  }

  /**
   * 检查表号是否重复
   * @Author   leezhua
   * @DateTime 2020-06-30
   * @param    [array]     $DA [description]
   * @return   boolean        [description]
   */
  public function isRepeat($DA, $user)
  {
    $map['type'] = $DA['type'];
    $map['company_id'] = $user['company_id'];
    $map['meter_no'] = $DA['meter_no'];
    if (isset($DA['id']) && $DA['id'] > 0) {
      $isRepeat  = $this->meterModel()->where($map)->where('id', '!=', $DA['id'])->exists();
    } else {
      $isRepeat  = $this->meterModel()->where($map)->exists();
    }
    return $isRepeat;
  }



  /** 启用禁用能源表 */
  public function enableMeter($DA, $user)
  {
    $data['is_vaild'] = $DA['is_vaild'];
    $res = $this->meterModel()->whereIn('id', $DA['Ids'])->update($data);
    return $res;
  }

  /** 绑定租户 */
  public function unBind(array $DA, $user)
  {
    try {
      DB::transaction(function () use ($DA, $user) {
        $DA['tenant_id']    = 0;
        $data['tenant_id']  =  $DA['tenant_id'];
        $data['u_uid']      = $user['id'];
        $DA['remark']       = '解绑租户';
        $this->meterModel()->whereId($DA['id'])->update($data);
        $this->saveMeterLog($DA, $user);
      });
      return true;
    } catch (Exception $e) {
      throw new Exception("保存日志失败");
      Log::error($e);
      return false;
    }
  }


  /**
   * 合同审核时，自动绑定到租户
   *
   * @Author leezhua
   * @DateTime 2021-07-14
   * @param int $tenantId
   * @param array $meterIds
   * @param [type] $user
   *
   * @return void
   */
  public function bindTenant($tenantId, int $contractId, $user)
  {
    try {
      DB::transaction(function () use ($contractId, $tenantId, $user) {
        $contractRoom = ContractRoom::selectRaw('GROUP_CONCAT(room_id) room_ids')
          ->where('contract_id', $contractId)->groupBy('contract_id')->first();
        $data['tenant_id']  = $tenantId;
        $data['u_uid']      = $user['id'];
        $this->meterModel()->whereIn('room_id', str2Array($contractRoom['room_ids']))->update($data);
        // 获取 水表电表信息
        $meter = $this->meterModel()->selectRaw('GROUP_CONCAT(id) ids')
          ->whereIn('room_id', str2Array($contractRoom['room_ids']))->first();
        foreach (str2Array($meter['ids']) as $k => $v) {
          $DA['remark']  = '绑定租户';
          $DA['meter_id']  = $v;
          $DA['tenant_id'] = $tenantId;
          $this->saveMeterLog($DA, $user);
        }
      }, 2);
      return true;
    } catch (Exception $e) {
      Log::error("水电表租户绑定失败" . $e);
      throw new Exception("水电表租户绑定失败" . $e);
      return false;
    }
  }

  /** 获取最近一次的抄表记录 */
  public function getNewMeterRecord($meterId)
  {
    return $this->meterRecordModel()->where('meter_id', $meterId)->orderBy('id', 'desc')->first();
  }

  // 通过租户ID 超表月份获取租户水电表信息
  /**
   * [meterRecord description]
   * @Author   leezhua
   * @DateTime 2020-08-10
   * @param    [type]     $tenantId   [description]
   * @param    [type]     $month      [description]
   * @param    integer    $energyType [1 水表 2 电表]
   * @return   [type]                 [description]
   */
  public function getMeterRecord($tenantId, $month, $energyType = 1)
  {
    return $this->meterRecordModel()->where('tenant_id', $tenantId)
      ->where(function ($q) use ($month) {
        if ($month) {
          $q->whereMonth('bill_month', dateFormat('m', $month)); // changed
          $q->whereYear('bill_month', dateFormat('Y', $month)); //
        }
      })->get();
  }
  /** 保存抄表记录 */
  /**
   * 保存抄录记录
   * @Author   leezhua
   * @DateTime 2020-07-23
   * @param    [type]     $DA   [description]
   * @param    [type]     $user [description]
   * @return   [type]           [description]
   */
  public function saveMeterRecord($DA, $user)
  {

    $res = ['flag' => true, 'msg' => ''];
    // 获取上一次的抄表信息
    $preData = $this->getNewMeterRecord($DA['meter_id']);
    // 获取表信息
    $meter = MeterModel::find($DA['meter_id']);

    $meterRecord = new MeterRecordModel;
    $meterRecord->company_id  = $user['company_id'];
    $meterRecord->c_username  = $user['realname'];
    $meterRecord->c_uid       = $user['id'];
    $meterRecord->pre_value   = $preData['meter_value'];
    $meterRecord->pre_used_value = $preData['used_value'];
    $meterRecord->pre_date    = $preData['record_date'];
    $meterRecord->meter_value = $DA['meter_value'];
    $meterRecord->used_value  = ($DA['meter_value'] - $preData['meter_value']) * $meter['multiple'];
    if (isset($DA['used_value']) < 0) {
      $res['flag'] = false;
      $res['msg'] = "使用数量不允许小于0！";   // 前端计算好传数据
      return $res;
    }
    $meterRecord->record_date = $DA['record_date'];
    $meterRecord->meter_id    = $DA['meter_id'];
    $tenant = $this->getTenantByRoomId($meter['room_id']);
    $meterRecord->tenant_name = $tenant['tenant_name'];
    $meterRecord->tenant_id = $tenant['id'];


    $meterRecord->pic = isset($DA['pic']) ? $DA['pic'] : "";
    $meterRecord->remark = isset($DA['remark']) ? $DA['remark'] : "";
    $result = $meterRecord->save();
    if ($result) {
      return $res;
    } else {
      $res['flag'] = false;
      $res['msg'] = "抄表失败";   // 前端计算好传数据
      return $res;
    }
  }


  /** 编辑抄表记录 */
  /**
   * 抄录记录修改
   * @Author   leezhua
   * @DateTime 2020-07-23
   * @param    [type]     $DA   [description]
   * @param    [type]     $user [description]
   * @return   [type]           [description]
   */
  public function editMeterRecord($DA, $user)
  {
    // 获取上一次的抄表信息
    $meterRecord = $this->meterRecordModel()->find($DA['id']);
    $meter = MeterModel::select('multiple')->find($meterRecord['meter_id']);
    $meterRecord->meter_value = $DA['meter_value'];
    $meterRecord->used_value  = ($DA['meter_value'] - $meterRecord['pre_value']) * $meter['multiple'];
    $meterRecord->record_date = $DA['record_date'];
    // $meterRecord->used_value = $DA['used_value'];
    $meterRecord->pic = isset($DA['pic']) ? $DA['pic'] : "";
    $meterRecord->remark = isset($DA['remark']) ? $DA['remark'] : "";
    $res = $meterRecord->save();
    return $res;
  }

  /** 核准 */
  /**
   * 抄录记录修改
   * @Author   leezhua
   * @DateTime 2020-07-23
   * @param    [type]     $DA   [description]
   * @param    [type]     $user [description]
   * @return   [type]           [description]
   */
  public function auditMeterRecord(array $Ids, $user)
  {
    try {
      DB::transaction(function () use ($Ids, $user) {
        $billService = new TenantBillService;
        $data['audit_user'] = $user['realname'];
        $data['audit_status'] = 1;
        $BA = array();
        foreach ($Ids as $k => $id) {
          $record = MeterRecordModel::whereId($id)->where('audit_status', 0)->first();
          if (!$record) {
            Log::error("未查询到数据.meter_id:" . $id);
            continue;
          }
          $meter = MeterModel::find($record['meter_id']);
          $BA['proj_id']      = $meter['proj_id'];
          $BA['tenant_id']    = $meter['tenant_id'];
          $BA['tenant_name']  = $record['tenant_name'];
          if ($meter['type'] == 1) {
            $BA['fee_type'] = AppEnum::waterFeeType;
          } else {
            $BA['fee_type'] = AppEnum::electricFeeType;
          }
          $BA['amount']       = numFormat($meter['price'] * $record['used_value']);
          $BA['bill_date']    = $record['pre_date'] . "至" . $record['record_date'];
          $BA['charge_date']  = date('Y-m-01', strtotime(getNextYmd($record['record_date'], 1)));
          // Log::error(json_encode($BA));
          $contractRoom = ContractRoom::where('room_id', $meter['room_id'])->first();
          if ($contractRoom['contract_id'] > 0) {
            $BA['contract_id'] = $contractRoom['contract_id'];
          } else {
            $BA['contract_id'] = 0;
          }
          // 插入水电费用到 租户费用表
          if (!$record['status']) { // 不是初始化的数据，审核时产生的费用到租户费用列表
            $BA['bank_id'] = getBankIdByFeeType($BA['fee_type'], $BA['proj_id']);
            if ($BA['bank_id'] == 0) {
              throw new Exception("未找到【" . getFeeNameById($BA['fee_type'])['fee_name'] . "】收款账户信息，请联系管理员配置");
            }
            $billService->saveBillDetail($BA, $user);
          }
        }
        // 更新状态
        $this->meterRecordModel()->whereIn('id', $Ids)->where('audit_status', 0)->update($data);
      }, 3);
      return true;
    } catch (Exception $th) {
      Log::error("水电表审核失败" . $th);
      throw new Exception($th->getMessage());
      return false;
    }
  }

  /**
   * 保存水电表更新记录
   *
   * @Author leezhua
   * @DateTime 2021-07-17
   * @param [type] $DA
   * @param [type] $user
   *
   * @return void
   */
  private function saveMeterLog($DA, $user)
  {
    $meterLog = new MeterLogModel;
    if (isset($DA['tenant_id'])) {
      if ($DA['tenant_id'] == 0  || empty($DA['tenant_id'])) {
        $meterLog->tenant_id   = 0;
        $meterLog->tenant_name =  '公区';
      } else {
        $tenant = new TenantService;
        $meterLog->tenant_id    = $DA['tenant_id'];
        $meterLog->tenant_name = $tenant->getTenantById($DA['tenant_id']);
      }
      // 保存水表客户
    } else {
      $meterLog->tenant_id  = 0;
      $meterLog->tenant_name = '公区';
    }

    $record = $this->meterRecordModel()->select('meter_value')
      ->where('meter_id', $DA['meter_id'])
      ->orderBy('id', 'desc')->first();
    $meterLog->meter_value  = $record['meter_value'];
    $meterLog->meter_id     = $DA['meter_id'];
    $meterLog->c_uid        = $user['id'];
    $meterLog->c_username   = $user['realname'];
    $meterLog->remark       = $DA['detail'] . "-" . $meterLog->tenant_name;
    $res = $meterLog->save();
    if ($res) {
      return true;
    } else {
      return false;
    }
  }


  /**
   * 获取水电表租户名称
   *
   * @Author leezhua
   * @DateTime 2024-03-20
   * @param [type] $roomId
   *
   * @return void
   */
  public function getTenantByRoomId($roomId)
  {
    $BA = array(
      'id' => 0,
      "tenant_name" => '',
    );
    if ($roomId === 0) {
      $BA['tenant_name'] = "公区";
      return $BA;
    }
    $room = ContractRoom::where('room_id', $roomId)->first();
    if (!$room) {
      $BA['tenant_name'] = "公区";
      return $BA;
    }
    $contract = Contract::find($room->contract_id);
    if ($contract) {
      $BA['id'] = $contract->tenant_id;
      $BA['tenant_name'] = $contract->tenant_name;
      return $BA;
    } else {
      $BA['tenant_name'] = "公区";
      return $BA;
    }
  }
}
