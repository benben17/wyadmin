<?php

namespace App\Api\Services\Contract;

use Exception;
use App\Enums\AppEnum;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Contract\ContractBill;
use App\Api\Services\Common\MessageService;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Channel\ChannelService;
use App\Api\Services\Company\FeeTypeService;
use App\Api\Services\Building\BuildingService;
use App\Api\Models\Contract\ContractFreePeriod;
use App\Api\Models\Contract\Contract as ContractModel;
use App\Api\Models\Contract\ContractLog as ContractLogModel;
use App\Api\Models\Contract\ContractRoom as ContractRoomModel;

/**
 *合同更新 日志处理 合同审核 客户更新为租户
 *contract_state  0 待提交 1 提交待审核 2 正式合同。98 退租。99 作废合同
 */

class ContractService
{

  // 合同模型
  public function model()
  {
    return new ContractModel;
  }

  //MARK: - 合同免租模型
  public function freeModel()
  {
    return new ContractFreePeriod;
  }
  //MARK: - 合同费用模型
  public function contractBillModel()
  {
    return new ContractBill;
  }

  //MARK: - 合同房间模型
  public function contractRoomModel()
  {
    return new ContractRoomModel;
  }


  //MARK: 生成合同编号
  /**
   * 获取合同编号
   *  生成规则：前缀+年月日时分秒+3位随机数
   * @return string
   */
  function getContractNo($companyId): string
  {
    $contractPrefix = getVariable($companyId, 'contract_prefix');
    $contractNo = $contractPrefix . date("ymdHis") . mt_rand(10, 99);
    return $contractNo;
  }


  //MARK: - 检查合同编号是否重复
  /**
   * 检查合同编号是否重复
   * @Author   leezhua
   * @DateTime 2020-07-05
   * @param    [str]     $contractNo  合同编号
   * @param    integer    $projId     项目ID
   * @param    integer    $contractId  合同ID
   * @return   [bool]                 [description]
   */
  public function checkContractNoRepeat($contractNo, $projId, $contractId = 0)
  {
    if (!$contractNo || empty($contractNo)) {
      return false;
    }
    if ($contractId == 0) {
      return $this->model()
        ->where('contract_no', $contractNo)
        ->where('proj_id', $projId)->exists();
    } else {
      return $this->model()
        ->where('contract_no', $contractNo)
        ->where('proj_id', $projId)
        ->where('id', '!=', $contractId)
        ->exists();
    }
  }

  // MARK: - 合同详情
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
      $data['deposit_bill'] = $this->getContractBillDetail($contractId, array(2, 3), $uid);
    }

    return $data;
  }

  //MARK: 账单明细
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
    foreach ($types as $v) {
      $feeTypeIds = $feeTypeService->getFeeIds($v, $uid);
      foreach ($feeTypeIds as $v1) {
        $subQuery = $this->contractBillModel()->where('type', $v)
          ->where('contract_id', $contractId)
          ->whereIn('fee_type', str2Array($v1))
          ->with('tenantBillDetail:contract_bill_id,status,bill_id');

        $bill = $subQuery->orderBy("charge_date")->get();
        $total = $subQuery->sum('amount');

        if ($bill && $total > 0) {
          $feeList = $bill->toArray();
          foreach ($feeList as $k => &$fee) {
            if ($fee['tenant_bill_detail']) {
              unset($fee['tenant_bill_detail']['fee_type_label']);
              $fee =  array_merge($fee, $fee['tenant_bill_detail']);
              unset($fee['tenant_bill_detail']);
            }
          }
          $feeBill[$i]['bill'] = $feeList;
          $feeBill[$i]['total'] = $total;
          $feeBill[$i]['fee_type'] = $v;
          switch ($v) {
            case 1:
              $feeBill[$i]['fee_type_label'] = getFeeNameById($v1)['fee_name'];
              break;
            case 2:
              $feeBill[$i]['fee_type_label'] = '押金';
              break;
            case 3:
              $feeBill[$i]['fee_type_label'] = '其他费用';
              break;
            default:
              // 处理未知费用类型的情况，如果有必要
              break;
          }

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
      $contractLog->contract_state = isset($DA['contract_state']) ? $DA['contract_state'] : "";
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
      throw new Exception("保存合同日志失败" . $th->getMessage());
    }
  }

  public function saveContractLog($contract, $user, $title, $remark = "")
  {
    $BA['contract_state'] = $contract['contract_state'];
    $BA['id']         = $contract['id'];
    $BA['c_uid']      = $user['id'];
    $BA['c_username'] = $user['realname'];
    $BA['title']      = $title;
    $BA['remark']     = $remark ?? "";
    $this->saveLog($BA);
  }

  /** 合同作废 */
  public function disuseContract($DA, $user)
  {
    $DA['c_uid'] = $user->id;
    $DA['c_username'] = $user->realname;
    $DA['title'] = "作废合同";
    $DA['contract_state'] = 99;
    $contract = $this->model()->find($DA['id']);
    if ($contract->contract_state == 99 || $contract->contract_state == 2) {
      return array('code' => 0, 'msg' => '合同为取消状态或者正式合同不允许取消');
    }
    try {
      DB::transaction(function () use ($contract, $DA) {
        // 更新合同状态
        $contract->contract_state = AppEnum::contractCancel;
        $contract->save();

        $detailBill = new TenantBillService;
        $detailBill->billDetailModel()->where('contract_id', $DA['id'])->delete();
        // 写入合同日志
        $this->saveLog($DA);
      });
      return array('code' => 1, 'msg' => '');
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return array('code' => 0, 'msg' => '');
    }
  }
  //#region 合同审核
  // MARK: 合同审核 
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
          $tenant->on_rent = 1;
          $tenant->state = '成交客户';
          $tenant->save();
          // 更新房间信息
          $contractRoom = ContractRoomModel::selectRaw('group_concat(room_id) as room_id')->where('contract_id', $DA['id'])->first();
          // Log::info($contractRoom);
          $roomService = new BuildingService;
          $roomService->updateRoomState($contractRoom['room_id'], 0);
          $msgContent =  $contract['tenant_name'] . "-已被-" . $user['realname'] . " 在 " . nowTime() . "审核完成。";
          $msgTitle = '合同审核通过';
          // 同步押金信息到 tenant_bill_detail
          $tenantBillService  = new TenantBillService;
          $tenantBillService->batchSaveBillDetail($contract['id'], $user, $contract['proj_id']);

          // 更新渠道佣金
          $channelService = new ChannelService;
          $channelService->updateBrokerage($tenant['channel_id'], $DA['id'], $tenant, $user['company_id']);
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
  //#endregion

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
        $DA['title']  = "待审核";
        $DA['remark'] = $user->realname . '在' . nowTime() . '提交' . $DA['title'] . "等待审核";
      } else {
        $DA['title']  = "保存合同";
        $DA['remark'] = $user->realname . '在' . nowTime() . $DA['title'];
      }
      $DA['c_uid']      = $user->id;
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
    // try {
    $data = ContractModel::select('id', 'start_date', 'end_date', 'room_type', 'contract_state', 'tenant_name', 'sign_date', 'rental_price', 'rental_price_type', 'tenant_id')
      ->whereHas('contractRoom', function ($q) use ($roomId) {
        $q->where('room_id', $roomId);
      })
      ->with(['tenant' => function ($query) {
        $query->select('id', 'name');
      }])
      ->get();
    if ($data) {
      $data->map(function ($v) {
        $v['cus_name'] = $v['tenant']['name'];
        unset($v['tenant']);
      });
      return $data->toArray();
    } else {
      return [];
    }
    // } catch (Exception $e) {
    //   Log::error("getContractByRoomId" . $e->getMessage());
    //   return (object)[];
    // }
  }

  public function getTenantNameFromRoomId($roomId)
  {
    // DB::enableQueryLog();
    $contractRoom = ContractRoomModel::where('room_id', $roomId)
      ->whereHas('contract', function ($q) {
        $q->where('contract_state', AppEnum::contractExecute);
      })

      ->orderBy('created_at')->first();
    // Log::info(DB::getQueryLog());
    if ($contractRoom) {
      // Log::alert($contractRoom->contract->tenant_id ?? "hahha");
      $tenantId = $contractRoom->tenant_id ?? 0;
      if ($tenantId == 0 || !$tenantId || empty($tenantId)) {
        return "";
      } else {
        return getTenantNameById($tenantId);
      }
    }
    return "";
  }


  /**
   * @Desc: roomId 获取合同信息
   * @Author leezhua
   * @Date 2024-07-10
   * @param [int] $roomId
   * @return array
   */
  public function getContractInfo(int $roomId)
  {
    // DB::enableQueryLog();
    $contractRoom = ContractRoomModel::where('room_id', $roomId)
      ->whereHas('contract', function ($q) {
        $q->where('contract_state', AppEnum::contractExecute);
      })
      ->with(['contract' => function ($query) {
        $query->select('id', 'end_date');
        $query->where('contract_state', AppEnum::contractExecute);
      }])
      ->orderBy('created_at')->first();
    // Log::info(DB::getQueryLog());

    if (!$contractRoom) {
      return ['tenant_name' => '', 'end_date' => '', 'days' => 0];
    }

    $tenantId = $contractRoom->tenant_id ?? 0;
    $tenantName = $tenantId ? getTenantNameById($tenantId) : "";

    $endDate = $contractRoom->contract->end_date ?? "";
    $days = !empty($endDate) ? diffDays($endDate, nowTime()) : 0;

    return ['tenant_name' => $tenantName, 'end_date' => $endDate, 'days' => $days];
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

  //MARK: 获取合同的平均日单价
  /**
   * 获取合同的平均日单价
   *
   * @Author leezhua
   * @DateTime 2021-07-11
   * @param integer $roomType
   *
   * @return void
   */
  public function contractAvgPrice($roomType = 1)
  {
    try {
      $contracts = ContractModel::whereHas('contractRoom', function ($q) use ($roomType) {
        $q->where('room_type', $roomType);
      })->get(['rental_price', 'rental_price_type', 'sign_area']);

      if ($contracts->isEmpty()) {
        return 0.00;
      }

      $totalAmount = 0.00;
      $totalArea = 0.00;

      foreach ($contracts as $contract) {
        if ($contract->rental_price == 0) {
          Log::error('rental_price :0 ');
          return 0.00;
        }

        $area      = $contract->sign_area;
        $price     = $contract->rental_price;
        $priceType = $contract->rental_price_type;

        $totalArea    += $area;
        $totalAmount  += ($priceType == 1) ? $price * $area : ($price * 12 / 365) * $area;
      }

      return $totalArea > 0 ? numFormat($totalAmount / $totalArea) : 0.00;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return 0.00;
    }
  }

  //MARK: - 合同免租列别
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
      $freePeriod  = new ContractFreePeriod();
      $freePeriod->contract_id = $contractId;
      $freePeriod->tenant_id   = $tenantId;
      $freePeriod->start_date  = $DA['start_date'];
      $freePeriod->free_num    = $DA['free_num'];
      $freePeriod->bill_date_delay = $DA['bill_date_delay'] ?? 0; // 账期是否顺延
      if (isset($DA['end_date'])) {
        $freePeriod->end_date = $DA['end_date'];
      }
      $freePeriod->save();
      return true;
    } catch (Exception $e) {
      Log::error("保存免租时间失败:" . $e->getMessage());
      throw new Exception("保存免租时间失败");
      return false;
    }
  }

  //MARK: 删除免租时间
  /**
   * 删除合同免租时间
   *
   * @Author leezhua
   * @DateTime 2021-07-11
   * @param [type] $contractId
   *
   * @return void
   */
  public function delFreeList($contractId)
  {
    return ContractFreePeriod::where('contract_id', $contractId)->delete();
  }
  //MARK: - 合同账单
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

      $this->contractBillModel()->where('contract_id', $contractId)->where('type', $type)->delete();
      if (empty($feeBill) || !$feeBill) {
        return;
      }
      foreach ($feeBill as $key => $bill) {
        $data = array();
        foreach ($bill['bill'] as $k => $v) {
          $data[$k]['company_id']  = $user['company_id'];
          $data[$k]['proj_id']     = $projId;
          $data[$k]['contract_id'] = $contractId;
          $data[$k]['tenant_id']   = $tenantId;
          $data[$k]['type']        = $v['type'] ?? 1; // 1 收款 2 付款
          $data[$k]['fee_type']    = $v['fee_type']; // 费用类型
          $data[$k]['price']       = isset($v['price']) ? $v['price'] : "";
          $data[$k]['unit_price_label'] = isset($v['unit_price_label']) ? $v['unit_price_label'] : "";
          $data[$k]['amount']      = $v['amount'];
          $data[$k]['bill_date']   = $v['bill_date'];
          $data[$k]['charge_date'] = $v['charge_date'];
          $data[$k]['start_date']  = $v['start_date'];
          $data[$k]['end_date']    = $v['end_date'];
          $data[$k]['c_uid']       = $user['id'];
          $data[$k]['remark']      = $v['remark'] ?? "";
          $data[$k]['created_at']  = nowTime();
        }
        $this->contractBillModel()->insert($data);
      }

      return true;
    } catch (Exception $e) {
      Log::error("保存合同账单失败，详细信息：" . $e->getMessage());
      throw $e;
      return false;
    }
  }

  //MARK: 退租处理 更新房源信息
  public function roomUpdateLeaseBack(int $contractId)
  {
    try {
      $contractRoomIds = $this->contractRoomModel()->where('contract_id', $contractId)->pluck('room_id')->toArray();
      $roomService = new BuildingService;
      $roomIds = [];
      if (sizeof($contractRoomIds) > 0) {
        $roomService->updateRoomState($roomIds, 1);
      }
    } catch (Exception $e) {
      Log::error("更新房间状态失败，详细信息：" . $e->getMessage());
      throw new Exception("更新房间状态失败");
    }
  }

  /**
   * 合同变更老账单处理 处理合同中的费用账单以及 租户表中的账单信息
   *
   * @Author leezhua
   * @DateTime 2021-07-11
   * @param [type] $feeBill
   *
   * @return void
   */
  public function changeOldContractBill($feeBill): bool
  {
    // 把合同中的
    try {
      DB::transaction(function () use ($feeBill) {
        $tenantBillService = new TenantBillService;
        foreach ($feeBill as $fee) {
          if ($fee['is_valid'] == 1) {
            continue;
          }
          $this->contractBillModel()->whereId($fee['id'])->delete();
          $tenantBillService->billDetailModel()->where('contract_bill_id', $fee['id'])->delete();
        }
      }, 2);

      return true;
    } catch (Exception $e) {
      Log::error("合同变更老账单处理失败，详细信息：" . $e->getMessage());
      throw new Exception("合同变更账单处理失败");
    }
    return false;
  }

  /**
   * 合同变更账单处理 保存合同中的费用账单以及 租户表中的账单信息
   *
   * @Author leezhua
   * @DateTime 2021-07-11
   * @param [type] $feeBill
   *
   * @return bool
   */
  public function changeContractBill($feeBill, $contract, $user): bool
  {
    // 把合同中的
    try {
      DB::transaction(function () use ($feeBill, $contract, $user) {
        foreach ($feeBill as  $list) {
          foreach ($list['bill'] as $v) {
            // 保存新的合同费用
            $billFee = $this->formatFeeBill($v, $contract, $user);
            $fee = $this->contractBillModel()->create($billFee);
            // 保存新的应收
            $tenantBillService = new TenantBillService;
            $billFee['contract_bill_id'] = $fee->id;
            $billFee['id'] = 0;
            $tenantBillService->saveBillDetail($billFee, $user);
          }
        }
      }, 2);

      return true;
    } catch (Exception $e) {
      Log::error("合同变更新账单处理失败，详细信息：" . $e);
      throw $e;
    }
    return false;
  }

  /**
   * 通过租户id获取合同房间
   *
   * @Author leezhua
   * @DateTime 2021-07-13
   * @param [type] $tenantId
   *
   * @return void
   */
  public function getRoomsByTenantId($tenantId)
  {
    return ContractRoomModel::whereHas('contract', function ($q) use ($tenantId) {
      $q->where('tenant_id', $tenantId);
    })->get();
  }

  public function getRoomsByTenantIdSelect($tenantId)
  {
    $data = ContractRoomModel::whereHas('contract', function ($q) use ($tenantId) {
      $q->where('tenant_id', $tenantId);
    })->get();

    return $data->map(function ($room) {
      return [
        'room_no' => $room->build_no . "-" . $room->floor_no . "-" . $room->room_no,
        'room_id' =>  $room->room_id,
        'contract_id' => $room->contract_id,
      ];
    });
  }
  //MARK: - 合同费用格式化
  /**
   * 合同费用格式化
   *
   * @Author leezhua
   * @DateTime 2024-03-13
   * @param [type] $fee
   * @param [type] $contract
   * @param [type] $user
   *
   * @return array
   */
  private function formatFeeBill($fee, $contract, $user): array
  {
    return [
      'company_id'       => $user['company_id'],
      'proj_id'          => $contract['proj_id'],
      'contract_id'      => $contract['id'],
      'tenant_id'        => $contract['tenant_id'],
      'type'             => isset($fee['type']) ? $fee['type'] : 1,                            // 1 收款 2 付款
      'fee_type'         => $fee['fee_type'],                                                  // 费用类型
      'price'            => isset($fee['price']) ? $fee['price'] : "",
      'unit_price_label' => isset($fee['unit_price_label']) ? $fee['unit_price_label'] : "",
      'amount'           => $fee['amount'],
      'bill_date'        => $fee['bill_date'],
      'charge_date'      => $fee['charge_date'],
      'start_date'       => $fee['start_date'],
      'end_date'         => $fee['end_date'],
      'is_valid'         => 1,
      'c_uid'            => $user['id'],
      'remark'           => isset($fee['remark']) ? $fee['remark'] : "",
      'created_at'       => nowTime()
    ];
  }

  /**
   * 通过合同id 获取room 信息
   *
   * @Author leezhua
   * @DateTime 2024-03-14
   * @param [type] $contractId
   *
   * @return void
   */
  public function getContractRoom($contractId)
  {
    $room = $this->contractRoomModel()
      ->selectRaw('build_no,floor_no,case when room_type = 1 then GROUP_CONCAT(room_no) else GROUP_CONCAT(station_no)   end  rooms ')
      ->where('contract_id', $contractId)->groupBy('contract_id')->first();
    if ($room) {
      return $room['build_no'] . "-" . $room['floor_no'] . "-" . $room['rooms'];
    } else {
      return "";
    }
  }

  /**
   * @Desc: 通过租户id获取房间信息
   * @Author leezhua
   * @Date 2024-04-08
   * @param [type] $tenantId
   * @return void
   */
  public function getContractRoomByTenantId($tenantId)
  {
    $room = $this->contractRoomModel()->selectRaw('build_no,floor_no,case when room_type = 1 then GROUP_CONCAT(room_no) else GROUP_CONCAT(station_no) end  rooms ')
      ->where('tenant_id', $tenantId)->groupBy('tenant_id')->first();
    if ($room) {
      return $room['build_no'] . "-" . $room['floor_no'] . "-" . $room['rooms'];
    } else {
      return "";
    }
  }

  //MARK: 合同退回 ,
  /**
   * @Desc:合同退回 管理员权限才有
   * @Author leezhua
   * @Date 2024-07-11
   * @param [int] $contractId
   * @param [str] $remark
   * @param [array] $user
   * @return void
   */
  public function adminReturn($contractId, $remark, $user)
  {
    try {
      DB::transaction(function () use ($contractId, $remark, $user) {
        // 删除应收
        $billDetail = new TenantBillService;
        $billDetail->billDetailModel()->where('contract_id', $contractId)->delete();
        // 更改状态
        $contract = $this->model()->find($contractId);
        $contract->contract_state = AppEnum::contractSave;
        $contract->save();
        // 写入日志
        $DA['id']             = $contractId;
        $DA['c_uid']          = $user['id'];
        $DA['c_username']     = $user['realname'];
        $DA['title']          = "合同退回";
        $DA['remark']         = ($remark ?? '合同退回') . "删除对应所有的应收";
        $DA['contract_state'] = 0;
        $this->saveLog($DA);
      }, 2);
      return true;
    } catch (Exception $e) {
      Log::error("合同退回失败" . $e->getMessage());
      throw new Exception("合同退回失败" . $e->getMessage());
    }
  }


  // 合同完成
  public function contractComplete($contractId, $user): bool
  {
    try {
      DB::transaction(function () use ($contractId, $user) {
        $contract = $this->model()->find($contractId);
        $contract->contract_state = AppEnum::contractComplete;
        $contract->save();
        // 写入日志
        $DA['id']             = $contractId;
        $DA['c_uid']          = $user['id'];
        $DA['c_username']     = $user['realname'];
        $DA['title']          = "合同执行完成";
        $DA['remark']         = "合同执行完成";
        $DA['contract_state'] = 3;
        $this->saveLog($DA);
      }, 2);
      return true;
    } catch (Exception $e) {
      Log::error("合同完成失败" . $e->getMessage());
      throw new Exception("合同完成失败" . $e->getMessage());
    }
  }

  //MARK: - 格式化合同并保存
  /**
   * 格式化合同并保存
   * @Author   leezhua
   * @DateTime 2021-06-01
   * @param    [type]     $DA   [description]
   * @param    string     $type [description]
   * @return   [type]           [description]
   */
  public function saveContract($DA, $user, $type = "add")
  {
    if ($type == "add") {
      $contract = $this->model();
      $contract->c_uid = $user->id;
      $contract->company_id = $user->company_id;
    } else {
      //编辑
      $contract = $this->model()->find($DA['id']);
      $contract->u_uid = $user->id;
    }
    // 合同编号不设置的时候系统自动生成
    if (!isset($DA['contract_no']) || empty($DA['contract_no'])) {
      $contractNo = $this->getContractNo($user->company_id);
      $contract->contract_no = $contractNo;
    } else {
      $contract->contract_no = $DA['contract_no'];
    }
    $contract->free_type             = isset($DA['free_type']) ? $DA['free_type'] : 0;
    $contract->proj_id               = $DA['proj_id'];
    $contract->contract_state        = $DA['contract_state'];
    $contract->contract_type         = $DA['contract_type'];
    $contract->violate_rate          = isset($DA['violate_rate']) ? $DA['violate_rate'] : 0;
    $contract->sign_date             = $DA['sign_date'];
    $contract->start_date            = $DA['start_date'];
    $contract->end_date              = $DA['end_date'];
    $contract->belong_uid            = $DA['belong_uid'] ?? $user->id;
    $contract->belong_person         = $DA['belong_person'] ?? $user->realname;
    $contract->tenant_id             = $DA['tenant_id'];
    $contract->tenant_name           = $DA['tenant_name'];
    $contract->lease_term            = $DA['lease_term'];
    $contract->industry              = $DA['industry'];
    $contract->tenant_sign_person    = isset($DA['tenant_sign_person']) ? $DA['tenant_sign_person'] : "";
    $contract->tenant_legal_person   = isset($DA['tenant_legal_person']) ? $DA['tenant_legal_person'] : "";
    $contract->sign_area             = $DA['sign_area'];
    $contract->rental_deposit_amount = isset($DA['rental_deposit_amount']) ? $DA['rental_deposit_amount'] : 0.00;
    $contract->rental_deposit_month  = isset($DA['rental_deposit_month']) ? $DA['rental_deposit_month'] : 0;
    if (isset($DA['increase_date'])) {
      $contract->increase_date = $DA['increase_date'];
    }
    $contract->increase_rate           = isset($DA['increase_rate']) ? $DA['increase_rate'] : 0;
    $contract->increase_year           = isset($DA['increase_year']) ? $DA['increase_year'] : 0;
    $contract->bill_type               = isset($DA['bill_type']) ? $DA['bill_type'] : 1;
    $contract->ahead_pay_month         = isset($DA['ahead_pay_month']) ? $DA['ahead_pay_month'] : "";
    $contract->rental_price            = isset($DA['rental_price']) ? $DA['rental_price'] : 0.00;
    $contract->rental_price_type       = isset($DA['rental_price_type']) ? $DA['rental_price_type'] : 1;
    $contract->management_price        = isset($DA['management_price']) ? $DA['management_price'] : 0.00;
    $contract->management_month_amount = isset($DA['management_month_amount']) ? $DA['management_month_amount'] : 0;
    $contract->rental_month_amount    = isset($DA['rental_month_amount']) ? $DA['rental_month_amount'] : 0.00;
    $contract->manager_bank_id        = isset($DA['manager_bank_id']) ? $DA['manager_bank_id'] : 0;
    $contract->rental_bank_id         = isset($DA['rental_bank_id']) ? $DA['rental_bank_id'] : 0;
    $contract->manager_deposit_month  = isset($DA['manager_deposit_month']) ? $DA['manager_deposit_month'] : 0;
    $contract->manager_deposit_amount = isset($DA['manager_deposit_amount']) ? $DA['manager_deposit_amount'] : 0.00;
    $contract->increase_show          = isset($DA['increase_show']) ? $DA['increase_show'] : 0;
    $contract->manager_show           = isset($DA['manager_show']) ? $DA['manager_show'] : 0;
    $contract->rental_usage           = isset($DA['rental_usage']) ? $DA['rental_usage'] : "";
    $contract->room_type              = isset($DA['room_type']) ? $DA['room_type'] : 1;
    $res = $contract->save();
    if ($res) {
      return $contract;
    } else {
      return false;
    }
  }
}
