<?php

namespace App\Api\Services\Contract;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Contract\ContractLog as ContractLogModel;
use App\Api\Models\Contract\ContractRoom as ContractRoomModel;
use App\Api\Models\Contract\Contract as ContractModel;
use App\Api\Models\Tenant\Tenant;
use App\Api\Services\Channel\ChannelService;
use App\Api\Services\Common\MessageService;
use App\Api\Models\Contract\ContractBill;
use App\Api\Models\Contract\ContractFreePeriod;
use App\Api\Services\Building\BuildingService;
use App\Api\Services\Company\FeeTypeService;
use App\Api\Services\Tenant\TenantBillService;
use App\Enums\AppEnum;

/**
 *合同更新 日志处理 合同审核 客户更新为租户
 *contract_state  0 待提交 1 提交待审核 2 正式合同。98 退租。99 作废合同
 */

class ContractService
{

  public function model()
  {
    return new ContractModel;
  }

  public function freeModel()
  {
    return new ContractFreePeriod;
  }
  public function contractBillModel()
  {
    return new ContractBill;
  }

  /**
   * 合同查看
   * @Author   leezhua
   * @DateTime 2020-07-05
   * @param    [type]     $contractId [description]
   * @param     $show [true 查看主合同信息以及账单信息 false 只查看主合同信息]
   * @return   [array]                 [合同以及账单信息根据所需返回]
   */
  public function showContract($contractId, $uid, $bill = true)
  {
    $data = ContractModel::with('contractRoom')
      ->with('freeList')
      ->with('billRule')
      ->with('depositRule')
      ->find($contractId);
    if ($data && $bill) {
      $data['fee_bill'] = $this->getContractBillDetail($contractId, array(1), $uid);
      $data['deposit_bill'] = $this->getContractBillDetail($contractId, array(2), $uid);
    }

    return $data;
  }

  /**
   * 获取合同账单明细
   *
   * @Author leezhua
   * @DateTime 2021-07-11
   * @param array $types
   *
   * @return void
   */
  public function getContractBillDetail($contractId, array $types, $uid)
  {
    $feeTypeService = new FeeTypeService;

    $feeBill = array();
    $i = 0;
    foreach ($types as $k => $v) {
      $feeTypeIds = $feeTypeService->getFeeIds($v, $uid);

      if ($v == 1) {
        foreach ($feeTypeIds as $k1 => $v1) {
          $bill = $this->contractBillModel()->where('type', $v)
            ->where('contract_id', $contractId)
            ->whereIn('fee_type', str2Array($v1))
            ->get();
          $total = $this->contractBillModel()->where('type', $v)
            ->where('contract_id', $contractId)
            ->whereIn('fee_type', str2Array($v1))
            ->sum('amount');
          if ($bill && $total) {
            // Log::error($v1 . "--------");
            $feeBill[$i]['bill'] = $bill;
            $feeBill[$i]['total'] = $total;
            $feeBill[$i]['fee_label'] = getFeeNameById($v1)['fee_name'];
            $i++;
          }
        }
      } else {
        $bill = $this->contractBillModel()->where('type', $v)
          ->where('contract_id', $contractId)
          ->whereIn('fee_type', $feeTypeIds)
          ->get();
        $total = $this->contractBillModel()->where('type', $v)
          ->where('contract_id', $contractId)
          ->whereIn('fee_type', $feeTypeIds)
          ->sum('amount');
        if ($total && $bill) {
          $feeBill[$i]['bill'] = $bill;
          $feeBill[$i]['total']  = $total;
          $v == 2 &&  $feeBill[$i]['fee_label'] = '押金';
          $v == 3 &&  $feeBill[$i]['fee_label'] = '其他费用';
          $i++;
        }
      }
    }
    return $feeBill;
  }
  /**
   * 保存合同审核日志
   *
   * @Author leezhua
   * @DateTime 2021-07-12
   * @param [type] $DA
   *
   * @return void
   */
  public function saveLog($DA)
  {
    try {
      $contractLog = new ContractLogModel;
      $contractLog->contract_id = $DA['id'];
      $contractLog->title       = $DA['title'];
      // $contractLog->type = isset($DA['type'];
      $contractLog->contract_state = $DA['contract_state'];
      if (isset($DA['audit_state']) && !empty($DA['audit_state'])) {
        $contractLog->audit_state = $DA['audit_state'];
      };
      $contractLog->remark      = isset($DA['remark']) ? $DA['remark'] : "";
      $contractLog->c_uid       = $DA['c_uid'];
      $contractLog->c_username  = $DA['c_username'];
      $contractLog->save();
      return true;
    } catch (Exception $th) {
      Log::error("保存合同日志失败" . $th->getMessage());
    }
  }

  /** 合同作废 */
  public function disuseContract($DA, $user)
  {
    $DA['c_uid'] = $user->id;
    $DA['c_username'] = $user->realname;
    $DA['title'] = "作废合同";
    $DA['contract_state'] = 99;
    $contract = ContractModel::find($DA['id']);
    if ($contract->contract_state == 99 || $contract->contract_state == 2) {
      return array('code' => 0, 'msg' => '合同为取消状态或者正式合同不允许取消');
    }
    try {
      DB::transaction(function () use ($contract, $DA) {
        // 更新合同状态
        $contract->contract_state = 99;
        $contract->save();

        // 写入合同日志
        $this->saveLog($DA);
      });
      return array('code' => 1, 'msg' => '');
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return array('code' => 0, 'msg' => '');
    }
  }
  /** 合同审核 */
  /**
   * 审核成功后 ，合同状态 contract_state更新为2
   * 客户状态 state 更新为2
   * 房源状态 room_state更新为0
   * 更新渠道佣金
   * 给合同跟进人发送系统通知消息
   * @Author   leezhua
   * @DateTime 2020-07-15
   * @param    [type]     $DA   [description]
   * @param    [type]     $user [description]
   * @return   [type]           [description]
   */
  public function auditContract($DA, $user)
  {
    $DA['c_uid'] = $user->id;
    $DA['c_username'] = $user->realname;
    // $DA['type'] = 2;
    $DA['title'] = "审核合同";

    try {
      DB::transaction(function () use ($DA, $user) {
        $contract = ContractModel::find($DA['id']);
        if ($DA['audit_state'] == 1) {
          $DA['contract_state'] = 2;  // 审核通过 ，合同状态 为正式合同
          // 更新客户状态 为租户
          $tenant  = Tenant::find($contract['tenant_id']);
          $tenant->type = 2;   //2 租户 1 客户 3 退租
          $tenant->state = '成交客户';
          $tenant->save();
          // 更新房间信息
          $contractRoom = ContractRoomModel::selectRaw('group_concat(room_id) as room_id')->where('contract_id', $DA['id'])->first();
          Log::info($contractRoom);
          $roomService = new BuildingService;
          $roomService->updateRoomState($contractRoom['room_id'], 0);
          $msgContent =  $contract['tenant_name'] . "-已被-" . $user['realname'] . " 在 " . nowTime() . "审核完成。";
          $msgTitle = '合同审核通过';
          // 同步押金信息到 tenant_bill_detail
          $bills = ContractBill::where('contract_id', $contract['id'])->where('type', 2)->get();
          if ($bills) {
            $tenantBillService  = new TenantBillService;
            $tenantBillService->batchSaveBillDetail($bills, $user, $contract['proj_id']);
          }
        } else {
          $DA['contract_state'] = 0; //审核不通过 进入草稿箱编辑
          $msgContent =  $contract['tenant_name'] . "-已被-" . $user['realname'] . " 在 " . nowTime() . "退回修改！";
          $msgTitle = '合同审核不通过!';
        }
        // 更新合同状态
        $contract->contract_state = $DA['contract_state'];
        $contract->save();
        // 写入合同日志
        $DA['remark'] = $msgContent;
        $this->saveLog($DA);
        // 给合同提交人发送系统通知消息
        $this->sendMsg($msgTitle, $msgContent, $user, $contract['belong_uid']);
      });
      return true;
    } catch (Exception $e) {
      log::error("audit" . $e);
      return false;
    }
  }

  /**
   * 合同日志保存
   * @Author   leezhua
   * @DateTime 2020-06-30
   * @param    [object]     $DA   [合同信息]
   * @param    [array]     $user [用户信息]
   * @return   [true]           [description]
   */
  public function contractLog($DA, $user)
  {
    try {
      if ($DA['contract_state'] == 1) {
        $DA['title'] = "待审核";
        $DA['remark'] = $user->realname . '在' . nowTime() . '提交' . $DA['title'] . "等待审核";
      } else {
        $DA['title'] = "保存合同";
        $DA['remark'] = $user->realname . '在' . nowTime() . $DA['title'];
      }
      $DA['c_uid'] = $user->id;
      $DA['c_username'] = $user->realname;
      // $DA['type'] = 2;
      $this->saveLog($DA);
      return true;
    } catch (Exception $e) {
      throw new Exception("合同日志保存失败", 1);
      return false;
    }
  }

  /** 通过客户id 获取客户合同信息 */
  public function getByContractID($contractId)
  {
    $contract = ContractModel::find($contractId);
    if ($contract) {
      return $contract->toArray();
    } else {
      return array();
    }
  }

  // 通过合同ids 获取合同列表
  //
  public function getContractByIDs($contractIds)
  {
    if (!is_array($contractIds)) {
      $contractIds = str2Array($contractIds);
    }
    $contract = ContractModel::select('rental_price', 'rental_price_type', 'rental_month_amount', 'management_price', 'management_month_amount', 'sign_date', 'pay_method', 'start_date', 'end_date', 'lease_term', 'sign_area', 'free_type', 'room_type', 'manager_show', 'rental_deposit_month', 'manager_deposit_amount', 'customer_id', 'id', 'contract_no')
      ->with('contractRoom')
      ->whereIn('id', $contractIds)
      ->get();
    if ($contract) {
      return $contract;
    } else {
      return array();
    }
  }

  /**
   * [getContractRoomByCusId description]
   * @Author   leezhua
   * @DateTime 2020-07-21
   * @param    [type]     $contractId [description]
   * @return   [type]                 [description]
   */
  public function getContractRoomByCusId($contractId)
  {
    $room = ContractRoomModel::selectRaw('concat_ws("-", build_no, floor_no) as build_floor, group_concat(room_no) room_no')
      ->where('contract_id', $contractId)
      ->groupBy('contract_id')->first();
    if ($room) {
      return $room->toArray();
    } else {
      return array();
    }
  }
  /**
   * [getRoomByContractId description]
   * @Author   leezhua
   * @DateTime 2020-07-21
   * @param    [type]     $contractIds [description]
   * @return   [type]                  [description]
   */
  public function getRoomByContractId($contractIds)
  {
    if (!is_array($contractIds)) {
      $contractIds = str2Array($contractIds);
    }
    $room = ContractRoomModel::whereIn('contract_id', $contractIds)->get();
    if ($room) {
      return $room->toArray();
    } else {
      return array();
    }
  }

  /**
   * 根据房间ID获取 合同信息
   * @Author   leezhua
   * @DateTime 2020-06-30
   * @param    [int]     $roomId [description]
   * @return   [Array]             [返回所有合同信息]
   */
  public function getContractByRoomId($roomId)
  {
    $data = ContractModel::whereHas('contractRoom', function ($q) use ($roomId) {
      $q->where('room_id', $roomId);
    })
      ->select('start_date', 'end_date', 'room_type', 'contract_state', 'customer_name', 'sign_date', 'rental_price', 'rental_price_type')
      ->get();
    if ($data) {
      return $data->toArray();
    } else {
      return (object)[];
    }
  }


  /** 发送通知消息 */
  private function sendMsg($title, $content, $user, $receiveUid = 0)
  {
    try {
      $msg = new MessageService;
      $DA['company_id']   = $user['company_id'];
      $DA['title']        = $title;
      $DA['content']      = $content;
      $DA['receive_uid']  = $receiveUid;
      $msg->contractMsg($DA);
    } catch (Exception $e) {
      Log::error("发送通知消息失败" . $e->getMessage());
    }
  }

  /** 通过合同id 获取合同的日志信息 */
  public function getContractLogById($contractId)
  {
    $contractLog = ContractLogModel::where('contract_id', $contractId)
      ->orderBy('id', 'desc')->get();
    if ($contractLog) {
      return $contractLog->toArray();
    }
    return (object)[];
  }

  /** 获取合同的平均日单价 */
  public function contractAvgPrice($roomType = 1)
  {
    try {
      $contract = ContractModel::select('rental_price', 'rental_price_type', 'sign_area')
        ->whereHas('contractRoom', function ($q) use ($roomType) {
          $q->where('room_type', $roomType);
        })
        ->get()->toArray();

      if (empty($contract)) {
        Log::error('empty');
        return 0.00;
      }
      $totalAmount = 0.00;
      $totalArea = 0;
      foreach ($contract as $k => $v) {
        $totalArea += $v['sign_area'];
        if ($v['rental_price'] == 0) {
          Log::error('rental_price :0 ');
          return 0.00;
        } else {
          if ($v['rental_price_type'] == 1) {
            $totalAmount += $v['rental_price'] * $v['sign_area'];
          } else {
            $totalAmount += ($v['rental_price'] * 12) / 365 * $v['sign_area'];
          }
        }
      }
      Log::error('service' . $totalAmount);
      $price = numFormat($totalAmount / $totalArea);
      return $price;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return '0.00';
    }
  }


  /**
   * 保存合同免租列表
   *
   * @Author leezhua
   * @DateTime 2021-07-11
   * @param [type] $DA
   * @param [type] $contractId
   * @param [type] $tenantId
   *
   * @return void
   */
  public function saveFreeList($DA, $contractId, $tenantId)
  {
    try {
      $freeperiod  = new ContractFreePeriod();
      $freeperiod->contract_id = $contractId;
      $freeperiod->tenant_id       = $tenantId;
      $freeperiod->start_date   = $DA['start_date'];
      $freeperiod->free_num     = $DA['free_num'];
      if (isset($DA['end_date'])) {
        $freeperiod->end_date = $DA['end_date'];
      }
      $freeperiod->save();
      return true;
    } catch (Exception $e) {
      Log::error("保存免租时间失败:" . $e->getMessage());
      throw new Exception("保存免租时间失败");
      return false;
    }
  }

  /** 删除免租时间 */
  public function delFreeList($contractId)
  {
    return ContractFreePeriod::where('contract_id', $contractId)->delete();
  }
  /**
   * 保存合同账单
   *
   * @Author leezhua
   * @DateTime 2021-07-11
   * @param [type] $DA
   * @param [type] $user
   * @param [type] $projId
   * @param [type] $contractId
   *
   * @return void
   */
  public function saveContractBill($feeBill, $user, $projId, $contractId, $tenantId, $type = 1)
  {
    try {
      // 先删除
      // Log::error("bill" . json_encode($feeBill));
      $this->contractBillModel()->where('contract_id', $contractId)->where('type', $type)->delete();
      foreach ($feeBill as $key => $bill) {
        $data = array();
        foreach ($bill['bill'] as $k => $v) {
          $data[$k]['company_id']  = $user['company_id'];
          $data[$k]['proj_id']     = $projId;
          $data[$k]['contract_id'] = $contractId;
          $data[$k]['tenant_id']   = $tenantId;
          $data[$k]['type']        = isset($v['type']) ? $v['type'] : 1; // 1 收款 2 付款
          $data[$k]['fee_type']    = $v['fee_type']; // 费用类型
          $data[$k]['price']       = isset($v['price']) ? $v['price'] : "";
          $data[$k]['unit_price_label'] = isset($v['unit_price_label']) ? $v['unit_price_label'] : "";
          $data[$k]['amount']      = $v['amount'];
          $data[$k]['bill_date']   = $v['bill_date'];
          $data[$k]['charge_date'] = $v['charge_date'];
          $data[$k]['start_date']  = $v['start_date'];
          $data[$k]['end_date']    = $v['end_date'];
          $data[$k]['c_uid']       = $user['id'];
          $data[$k]['remark']      = isset($DA['remark']) ? $DA['remark'] : "";
          $data[$k]['created_at']  = nowTime();
        }
        $this->contractBillModel()->addAll($data);
      }

      return true;
    } catch (Exception $e) {
      Log::error("保存合同账单失败，详细信息：" . $e->getMessage());
      throw $e;
      return false;
    }
  }
}
