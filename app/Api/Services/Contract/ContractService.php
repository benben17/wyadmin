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
use App\Api\Models\Contract\ContractBill as ContractBillModel;
use App\Api\Models\Contract\ContractFreePeriod;
use App\Api\Services\Building\BuildingService;
use App\Enums\AppEnum;

/**
 *合同更新 日志处理 合同审核 客户更新为租户
 *contract_state  0 待提交 1 提交待审核 2 正式合同。98 退租。99 作废合同
 */

class ContractService
{


  /**
   * 合同查看
   * @Author   leezhua
   * @DateTime 2020-07-05
   * @param    [type]     $contractId [description]
   * @param     $show [true 查看主合同信息以及账单信息 false 只查看主合同信息]
   * @return   [array]                 [合同以及账单信息根据所需返回]
   */
  public function showContract($contractId, $bill = true)
  {
    $data = ContractModel::with('contractRoom')
      ->with('freeList')
      ->with('project:id,proj_name')->find($contractId)->toarray();
    if (!$bill) {
      return $data;
    }
    // return response()->json(DB::getQueryLog());
    $billType = ContractBillModel::select('type', DB::Raw('sum(amount) as amount'))
      ->where('contract_id', $contractId)
      ->groupBy('type')->get()->toArray();
    $data = $data + ['rental_bill' => [], 'rental_total' => 0, 'management_bill' => [], 'management_total' => 0, 'deposit_bill' => [], 'deposit_total' => 0];
    foreach ($billType as $k => $v) {
      if ($v['type'] == '租金') {
        $data['rental_total'] = $v['amount'];
        $data['rental_bill']  = $this->getBillByType($contractId, $v['type']);;
      } else if ($v['type'] == '管理费') {
        $data['management_total'] = $v['amount'];
        $data['management_bill'] = $this->getBillByType($contractId, $v['type']);
      } else if ($v['type'] == '租赁押金' || $v['type'] == '管理押金') {
        $data['deposit_total'] += $v['amount'];
        $type = ['租赁押金', '管理押金'];
        $data['deposit_bill'] = $this->getBillByType($contractId, $type);
      }
    }
    return $data;
  }

  /**
   * 根据合同账单类型获取账单信息
   * @Author   leezhua
   * @DateTime 2020-07-05
   * @param    [type]     $contractId [description]
   * @param    [type]     $billType   [description]
   * @return   [type]                 [description]
   */
  public function getBillByType($contractId, $billType)
  {

    is_array($billType) || $billType = str2Array($billType);
    return ContractBillModel::where('contract_id', $contractId)
      ->whereIn('type', $billType)
      ->get();
  }

  //合同日志保存
  public function saveLog($DA)
  {
    $contractLog = new ContractLogModel;
    $contractLog->contract_id = $DA['id'];
    $contractLog->title       = $DA['title'];
    // $contractLog->type = isset($DA['type'];
    $contractLog->contract_state = $DA['contract_state'];
    if (isset($DA['audit_state']) && !empty($DA['audit_state'])) {
      $contractLog->audit_state = $DA['audit_state'];
    };
    $contractLog->remark      = $DA['remark'];
    $contractLog->c_uid       = $DA['c_uid'];
    $contractLog->c_username  = $DA['c_username'];
    $res = $contractLog->save();
    if ($res) {
      return $contractLog;
    } else {
      return $res;
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
        $contractId = $DA['id'];
        $contract = ContractModel::find($DA['id']);
        if ($DA['audit_state'] == 1) {
          $DA['contract_state'] = 2;  // 审核通过 ，合同状态 为正式合同
          // 更新客户状态 为租户
          $customer  = Tenant::find($contract['tenant_id']);
          $customer->type = 2;   //2 租户 1 客户 3 退租
          $customer->state = '成交客户';   //2 租户 1 客户 3 退租
          $customer->save();
          // 保存租户联系人
          $user['parent_type']  = AppEnum::Tenant;

          //更新渠道佣金
          $customer['rental_month_amount'] = $contract['rental_month_amount'];
          $customer['contract_id'] = $DA['id'];
          $channel = new ChannelService;
          $res = $channel->saveBrokerage($customer);
          if (!$res) {
            throw new Exception("佣金更新失败");
          }
          // 更新房间信息
          $contractRoom = ContractRoomModel::selectRaw('group_concat(room_id) as room_id')->where('contract_id', $DA['id'])->first();
          Log::info($contractRoom);
          $roomService = new BuildingService;
          $res = $roomService->updateRoomState($contractRoom['room_id'], 0);
          $msgContent =  $contract['customer_name'] . "-已被-" . $user['realname'] . " 在 " . nowTime() . "审核完成。";
          $msgTitle = '合同审核通过';
        } else {
          $DA['contract_state'] = 0; //审核不通过 进入草稿箱编辑
          $msgContent =  $contract['customer_name'] . "-已被-" . $user['realname'] . " 在 " . nowTime() . "退回修改！";
          $msgTitle = '合同审核不通过!';
        }
        // 更新合同状态
        $contract->contract_state = $DA['contract_state'];
        $contract->save();

        // 写入合同日志
        $DA['remark'] .= $msgContent;
        $this->saveLog($DA);
        // 给合同提交人发送系统通知消息
        $msgContent .= '</br>' . $DA['remark'];
        $this->sendMsg($msgTitle, $msgContent, $user, $contract['belong_uid']);
      });
      return true;
    } catch (Exception $e) {
      log::error("audit" . $e->getMessage());
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
    if ($DA['contract_state'] == 1) {
      $DA['title'] = "待审核";
      $DA['remark'] = $user->realname . '在' . nowTime() . '提交' . $DA['title'];
    } else {
      $DA['title'] = "保存合同";
      $DA['remark'] = $user->realname . '在' . nowTime() . $DA['title'];
    }
    $DA['c_uid'] = $user->id;
    $DA['c_username'] = $user->realname;
    // $DA['type'] = 2;

    try {
      $res = $this->saveLog($DA);
      return true;
    } catch (Exception $e) {
      throw new Exception("合同日志保存失败", 1);
      return false;
    }
  }

  /** 通过客户id 获取客户合同信息 */
  public function getByContractID($contractId)
  {
    $contract = ContractModel::select('rental_price', 'rental_price_type', 'rental_month_amount', 'management_price', 'management_month_amount', 'sign_date', 'pay_method', 'start_date', 'end_date', 'lease_term', 'sign_area', 'free_type', 'room_type', 'manager_show', 'rental_deposit_month', 'manager_deposit_amount', 'customer_id', 'id', 'contract_no')
      ->find($contractId);
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
      foreach ($contract as $k => &$v) {
        if ($v['rental_price_type'] == 1) {
          $v['rental_price'] = $v['rental_price'] . '天/㎡';
        } else if ($v['rental_price_type'] == 2) {
          $v['rental_price'] = $v['rental_price'] . '月/㎡';
        }
        if ($v['room_type'] == 1) {
          $v['sign_area']     = $v['sign_area'] . '㎡';
          $v['room_type_label']     = '房源';
        } else if ($v['room_type'] == 2) {
          $v['sign_area']             = intval($v['sign_area']) . '个';
          $v['room_type_label']       = '工位';
        }
        $room = $this->getContractRoomByCusId($v['id']);
        if ($room) {
          $v['build_floor']   = $room['build_floor'];
          $v['room_no']       = $room['room_no'];
        } else {
          $v['build_floor']   = "";
          $v['room_no']       = "";
        }
      }
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
      foreach ($data as $k => &$v) {
        // Log::error($v['room_type']);
        if ($v['rental_price_type'] == 1) {
          if ($v['room_type'] == 1) {
            $v['rental_price'] .= "元/㎡·天";
          } else {
            $v['rental_price'] .= "元/天";
          }
        } else if ($v['rental_price_type'] == 2) {

          if ($v['room_type'] == 1) {
            $v['rental_price'] .= "元/㎡·月";
          } else {
            $v['rental_price'] .= "元/月";
          }
        }
        $v['contract_state'] = $this->getState($v['contract_state']);
      }
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
      $DA['company_id']   =  $user['company_id'];
      $DA['title']        = $title;
      $DA['content']      = $content;
      $DA['receive_uid']  = $receiveUid;
      $msg->contractMsg($DA);
    } catch (Exception $e) {
      Log::error($e->getMessage());
    }
  }

  /** 通过合同id 获取合同的日志信息 */
  public function getContractLogById($contractId)
  {
    $contractLog = ContractLogModel::where('contract_id', $contractId)
      ->orderBy('id', 'desc')->get();
    if ($contractLog) {
      foreach ($contractLog as &$v) {
        $v['contract_state_lable'] = $this->getState($v['contract_state']);
      }
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
   * 获取合同状态中文名
   * @Author   leezhua
   * @DateTime 2020-07-04
   * @param    [type]     $value [description]
   * @return   [type]            [description]
   */
  public function getState($value)
  {
    switch ($value) {
      case '0':
        return "待提交";
        break;
      case '1':
        return "待审核";
        break;
      case '2':
        return '正常执行';
        break;
      case '98':
        return '退租合同';
        break;
      case '99':
        return '作废合同';
        break;
    }
  }

  /** 保存合同免租时间 */
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
      Log::error($e->getMessage());
      throw new Exception("系统错误");
      return false;
    }
  }

  /** 删除免租时间 */
  public function delFreeList($contractId)
  {
    $res = ContractFreePeriod::where('contract_id', $contractId)->delete();
    return $res;
  }
}
