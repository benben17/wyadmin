<?php

namespace App\Api\Services\Contract;

use App\Api\Models\Contract\BillRule;
use App\Api\Models\Contract\Contract;
use App\Api\Services\Company\FeeTypeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 *
 */
class ContractBillService

{


  /**
   * [createBillNormal description]
   * @Author   leezhua
   * @DateTime 2020-06-18
   * @param    [type]     $contract  [description]
   * @param    boolean    $increment [description]
   * @return   [type]                [description]
   */
  public function createBillParm($contract, $billType = "rental", $increment = false)
  {

    if (!$increment) {  // 递增之前
      $BA['signDate']  = $contract['sign_date'];
      $BA['monthAmount'] = $contract['rental_month_amount'];
      $BA['startDate'] = $contract['start_date'];
      if ($contract['rental_price_type'] == 1) {
        $BA['price'] =  $contract['rental_price'];
        $BA['dayAmount'] = $contract['rental_price'] * $contract['sign_area'];
      } else {
        $BA['price'] = $contract['rental_price'];
        // 日租金  = 月单价*12/365
        $BA['dayAmount'] = ($contract['rental_price'] * $contract['sign_area'] * 12) / 365;
      }
      // 没有递增 或者递增之前的周期。计算结束时间，
      // 如果没有递增直接取合同截止日期
      // 如果有递增 合同的开始日期后的第几年递增开始计算出递增之前的价格
      if ($contract['increase_show'] == 0  || $billType != "rental") {
        $BA['lease_term'] = $contract['lease_term'];
        $BA['endDate']    = $contract['end_date'];
      } else {
        $BA['lease_term'] = ($contract['increase_year'] * 12);
        $BA['endDate']    = getEndNextYmd($contract['start_date'], $BA['lease_term']);
      }
    } else {
      $increaseRate = $contract['increase_rate'] / 100 + 1;
      $BA['monthAmount'] = $contract['rental_month_amount'] * $increaseRate;
      $BA['startDate']  = $contract['increase_date'];
      //日租金
      if ($contract['rental_price_type'] == 1) {
        $BA['price'] = $contract['rental_price'] * $increaseRate;
        $BA['dayAmount'] = $contract['rental_price'] * $contract['sign_area'] * $increaseRate;
      } else {
        $BA['price'] = $contract['rental_price'] * $increaseRate;
        $BA['dayAmount'] = ($contract['rental_price'] * $contract['sign_area'] * 12) / 365 * $increaseRate;
      }
      //计算递增的租期  contract['increase_year']  1 代表 第二年。 2 代表第三年
      $BA['lease_term'] =  $contract['lease_term'] - ($contract['increase_year'] * 12);
      $BA['endDate'] = $contract['end_date'];
    }
    $BA['billDay'] = $contract['bill_day'];
    $BA['period'] = $contract['pay_method'];
    $BA['ahead_pay_month'] = $contract['ahead_pay_month']; // 提前几个月付款
    $BA['free_list'] = $contract['free_list'];
    $BA['free_type'] = $contract['free_type'];
    $BA['price_type'] = $contract['rental_price_type'];  // 传单价类型，显示单价单位
    if ($billType == "rental") {
      // $BA['free_list'] = $contract['free_list'];
      $BA['fee_type'] = "租金";

      $BA['room_type'] = isset($contract['room_type']) ? $contract['room_type'] : 1;
    } else {
      $BA['monthAmount'] = $contract['management_month_amount'];
      $BA['price'] = $contract['management_price'];
      $BA['dayAmount'] = $contract['management_price'] * $contract['sign_area']; //管理费日价
      $BA['fee_type'] = "管理费";
      $BA['room_type'] = 1;
    }
    return $BA;
  }

  /**
   * 按照正常周期进行出账单
   * @Author   leezhua
   * @DateTime 2020-07-05
   * @param    [type]     $DA       [description]
   * @param    string     $billType [description]
   * @return   [type]               [description]
   */
  public function createBill($contractId, $leaseTerm, $uid)
  {
    $data = array();
    $feeTypeService = new FeeTypeService;
    $feetype = $feeTypeService->getFeeIds(1, $uid);
    Log::error(json_encode($feetype) . "费用id");
    DB::enableQueryLog();
    $feeList = BillRule::where('contract_id', $contractId)->whereIn('fee_type', $feetype)->get();
    // return response()->json(DB::getQueryLog());
    Log::error(json_encode($feeList) . "");
    foreach ($feeList as $k => $v) {
      Log::error("创建账单" . $v['id']);
      $i = 0;
      $data[$k]['total'] = 0.00;
      $billNum = ceil($leaseTerm / $v['pay_method']);
      $bill = array();

      while ($i < $billNum) {

        $period  = $v['pay_method'];
        $bill[$i]['type'] = $v['fee_type'];
        $bill[$i]['price'] = $v['unit_price'] . $v['unit_price_label'];
        if ($i == 0) { // 第一次账单 收费日期为签合同日期开始日期为合同开始日期
          $startDate = $v['start_date'];
          $bill[$i]['charge_date'] = $v['start_date'];
        } else {
          // 收费日期根据提前几个月（ahead_pay_month）算出来的
          $chargeDate = getNextYmd($startDate, $period - $v['ahead_pay_month']);
          $startDate = getNextYmd($startDate, $period);
          $bill[$i]['charge_date'] = date("Y-m-" . $v['bill_day'], strtotime($chargeDate));
        }
        // 收款日期，根据合同的收款日走
        $endDate = getEndNextYmd($startDate, $period);
        $bill[$i]['start_date'] = $startDate;
        if ($i + 1 != $billNum) {  // 最后一个租期
          $bill[$i]['end_date'] = $endDate;
          $bill[$i]['amount'] = numFormat($period * $v['month_amt']);
        } else {
          $bill[$i]['end_date'] = $v['end_date'];
          $lastPeriod = $leaseTerm - ($period * $i);
          $bill[$i]['amount'] = numFormat($lastPeriod * $v['month_amt']);
        }
        $bill[$i]['bill_date'] =  $bill[$i]['start_date'] . '至' . $bill[$i]['end_date'];
        $data[$k]['total'] += $bill[$i]['amount'];
        $i++;
      }
      $data[$k]['bill'] = $bill;
      $data[$k]['fee_type'] =  $v['fee_type_label'];
    }

    return $data;
  }

  public function createBillziranyue($contractId, $leaseTerm, $uid)
  {
    $data = array();
    $feeTypeService = new FeeTypeService;
    $feetype = $feeTypeService->getFeeIds(1, $uid);
    Log::error(json_encode($feetype) . "费用id");
    DB::enableQueryLog();
    $feeList = BillRule::where('contract_id', $contractId)->whereIn('fee_type', $feetype)->get();
    // return response()->json(DB::getQueryLog());
    Log::error(json_encode($feeList) . "");
    foreach ($feeList as $k => $v) {
      Log::error("创建账单" . $v['id']);
      $i = 0;
      $data[$k]['total'] = 0.00;
      $bill = array();
      $startDate = $v['start_date'];
      while (strtotime($startDate) < strtotime($v['end_date'])) {
        $bill[$i]['type'] = $v['fee_type'];
        $bill[$i]['price'] = $v['unit_price'] . $v['unit_price_label'];
        $bill[$i]['start_date'] = $startDate;
        Log::error($startDate);
        $endDate = date("Y-m-t", strtotime($startDate));
        $bill[$i]['end_date'] = $endDate;
        $bill[$i]['charge_date'] = date("Y-m-" . $v['bill_day'], strtotime($startDate));
        $bill[$i]['amount'] = numFormat($v['month_amt']);
        if (strtotime($startDate) == strtotime($v['start_date'])) {

          if (date('d', strtotime($startDate)) != "01") {

            $days =  diffDays($startDate, $endDate);
            Log::error("第一期账单天数：" . $days);
            $bill[$i]['amount'] = numFormat($v['month_amt'] / 30 * $days);
          }
        }
        if (strtotime($endDate) > strtotime($v['end_date'])) {
          $days =  diffDays($startDate,  $v['end_date']);
          $bill[$i]['end_date'] = $v['end_date'];
          Log::error($days . "最后一期天数");
          $bill[$i]['amount'] = numFormat($v['month_amt'] * $leaseTerm - $data[$k]['total']);
        }
        $bill[$i]['bill_date'] =  $bill[$i]['start_date'] . '至' . $bill[$i]['end_date'];
        $data[$k]['total'] += $bill[$i]['amount'];
        $bill[$i]['mouth_amt'] = $v['month_amt'];
        $bill[$i]['bill_num']  = $i + 1;
        $i++;
        $startDate = getNextYmd(date('Y-m-01', strtotime($startDate)), 1);
      }
      $data[$k]['bill'] = $bill;
      $data[$k]['fee_type'] =  $v['fee_type_label'];
    }
    return $data;
  }
  /**
   * 各种押金计算
   * @Author   leezhua
   * @DateTime 2020-07-04
   * @param    [type]     $contract [description]
   * @return   [type]               [description]
   */
  public function createDepositBill($contractId, $uid)
  {
    $data = array();
    $feeTypeService = new FeeTypeService;
    $feetype = $feeTypeService->getFeeIds(2, $uid);
    $deposit = BillRule::where('contract_id', $contractId)->whereIn('fee_type', $feetype)->get();
    if ($deposit) {
      $data['total'] = 0.00;
      $i = 0;
      foreach ($deposit as $k => $v) {
        $data[$i]['amount']     = $v['amount'];
        $data[$i]['start_date'] = $v['start_date'];
        $data[$i]['end_date']   = $v['end_date'];
        $data[$i]['bill_date']  = $v['start_date'] . "至" . $v['end_date'];
        $data[$i]['remark']     = $v['remark'];
        $data['total'] += $v['amount'];
        $i++;
      }
    }
    return $data;
  }

  /**
   * 正常账单 免租期处理
   * @Author   leezhua
   * @DateTime 2020-07-04
   * @param    [array]     $freeList    [description]
   * @param    [datetime]     $startDate   [description]
   * @param    [datetime]     $endDate     [description]
   * @param    [int]     $monthAmount [月租金金额]
   * @param    [int]     $dayAmount   [天租金金额]
   * @return   [type]                  [description]
   */
  private function billFree($freeList, $startDate, $endDate, float $monthAmount, $dayAmount, $freeType)
  {
    $data = ['remark' => "", 'freeAmount' => 0];
    foreach ($freeList as $k => $v) {
      // 免租开始日期在帐期中进行减免，不处理跨帐期
      if (strtotime($v['start_date']) <= strtotime($endDate) && strtotime($v['start_date']) >= strtotime($startDate)) {
        if ($freeType == 1) {
          $data['freeAmount'] += $v['free_num'] * $monthAmount;
          $data['remark'] .= "免租" . $v['free_num'] . "个月|开始" . $v['start_date'];
        } else {
          // 如果是按天免租，换算成月在进行免租计算
          if ($v['free_num'] > 0) {
            $freeMonth = sprintf("%.6f", ($v['free_num'] / (365 / 12)));
          }
          $data['freeAmount'] += $v['free_num'] * $dayAmount;
          // $data['freeAmount'] += $v['free_num'] * $monthAmount;
          $data['remark'] .= "免租" . $v['free_num'] . "天,开始" . $v['start_date'];
        }
      }
    }
    return $data;
  }

  /**
   * 按照帐期生成应缴账单， 有免租期，缴费周期延长（固定一次收三个月的租金）
   * @Author   leezhua
   * @DateTime 2020-07-04
   * @param    [type]     $DA       [description]
   * @param    string     $billType [description]
   * @return   [type]               [description]
   */
  public function createBillByzhangqi($DA, $billType = "rental")
  {
    $i = 0;
    $data['total'] = 0;
    $period  = $DA['period'];
    // $startDate = $DA['startDate'];
    $totalFreeNum = $this->freeCount($DA);    // 获取免租总时长
    $freeType = $DA['free_type'];
    if ($freeType != 1) {
      $totalFreeNum = sprintf("%.6f", ($totalFreeNum / (365 / 12)));
    }
    Log::info('total_free_num' . $totalFreeNum);
    // $remainder = $freeNum % $period; // 取余数，计算最后一个账期减多少个月
    $remainder = sprintf("%.6f", fmod(($DA['lease_term'] - $totalFreeNum), $period));
    if ($remainder == 0) {
      $remainder = 0;
    } else {
      $remainder = $period - $remainder;
    }

    $ceil = ceil($DA['lease_term'] / $period);
    $bill = array();

    while ($i <= $ceil) {
      // $bill[$i]['price'] = numFormat($DA['price'])." 元/㎡·天";
      $bill[$i]['price'] = numFormat($DA['price']) . $this->priceUnit($DA['room_type'], $DA['price_type']);
      if ($i == 0) {
        // $chargeDate  = $DA['startDate'];
        $startDate = $DA['startDate'];
        $bill[$i]['charge_date'] = $DA['startDate'];
      } else {
        $startDate = $endDate;
        $chargeDate = getPreYmd($endDate, $DA['ahead_pay_month']);
        $bill[$i]['charge_date'] = date("Y-m-" . $DA['billDay'], strtotime($chargeDate));
      }

      $endDate = getNextYmd($startDate, $period);
      if (!empty($DA['free_list'])) {
        if ($freeType == 1) { // 免租类型为1 的时候 按月免租
          $free = $this->endDateByMonth($startDate, $period, $DA['free_list'], 0, $DA['endDate']);
          $endDate = $free['end_date'];
          Log::info('$free[free_num]' . $free['free_num']);
          if ($billType == "rental") { //管理费不免，有免租期要加上
            $bill[$i]['amount'] = numFormat($DA['monthAmount'] * $period);
            if ($free['free_num'] > 0) {
              $bill[$i]['remark'] = $free['remark'];
            }
          } else { // 管理费不免 要加上免租时间的 管理费 账单周期+ 免租时间（月）
            $bill[$i]['amount'] = numFormat($DA['monthAmount'] * ($period + $free['free_num']));
          }
        } else {
          // 按天免租 获取免租后账单的结束日期
          // 开始时间 周期 免租列表 0 合同结束时间
          $free = $this->endDateByDays($startDate, $period, $DA['free_list'], 0, $DA['endDate']);
          // 天转换成月
          $free['free_num'] = sprintf("%.4f", ($free['free_num'] / 30.5));
          Log::info('$free[free_num]' . $free['free_num']);
          $endDate = $free['end_date'];
          if ($billType == "rental") {
            $bill[$i]['amount'] = numFormat($DA['monthAmount'] * $period);
            if ($free['free_num'] > 0) {
              $bill[$i]['remark'] = $free['remark'];
            }
          } else { // 管理费不免，有免租期要加上 帐期内的管理费。加 免租天数* 管理费日单价
            $bill[$i]['amount'] = numFormat($DA['monthAmount'] * ($period + $free['free_num']));
          }
        }
      } else { // 免租列表为空的时候正常计算
        $bill[$i]['amount'] = numFormat($DA['monthAmount'] * $period);
      }

      $bill[$i]['start_date'] = $startDate;
      $bill[$i]['type'] = $DA['fee_type'];
      if ($endDate >= $DA['endDate']) {  //如果账单结束日期大于或者等于合同日期的时候 处理最后一个账单 并跳出
        $bill[$i]['end_date'] = $DA['endDate'];   // 结束日期为合同结束日期
        if ($freeType == 1) {
          // 按月 最后一个帐期 一个帐期的月数 减去免租期的月（remainder 除以 帐期取余数） 在乘以 月租金
          $bill[$i]['amount'] = numFormat($DA['monthAmount'] * ($period - $remainder));
        } else {
          // 按天。不计算是否跨帐期 直接减肥所有的免租的天数，最后一个帐期的金额可能为负数，需要手工更新
          $bill[$i]['amount'] = numFormat($DA['monthAmount'] * ($period - $remainder));
        }
        $bill[$i]['bill_date'] = $startDate . "至" . $bill[$i]['end_date'];
        $data['total'] += $bill[$i]['amount'];
        break;
      } else {
        $data['total'] += $bill[$i]['amount'];
        $bill[$i]['end_date'] = getPreYmdByDay($endDate, 1);
        $bill[$i]['bill_date'] = $startDate . "至" . $bill[$i]['end_date'];
      }
      // Log::info(json_encode($bill));
      $i++;
    }
    $data['bill'] = $bill;
    return $data;
  }


  private function priceUnit($roomType, $priceType)
  {
    try {
      if ($priceType == 1) {
        if ($roomType == 1) {
          return '元/㎡·天';
        } else {
          return '元·天';
        }
      } else {
        if ($roomType == 1) {
          return '元/㎡·天';
        } else {
          return '元·月';
        }
      }
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return "";
    }
  }

  /**
   * 免租期为月：计算帐期的开始时间结束时间
   * @Author   leezhua
   * @DateTime 2020-07-15
   * @param    [date]     $startDate   [开始日期]
   * @param    [int]     $period      [账单周期]
   * @param    [type]     $freeList    [免租列表]
   * @param    [type]     $months      [月]
   * @param    [type]     $signEndDate [合同结束时间]
   * @param    string     $freeRemark  [备注]
   * @return   [type]                  [array]
   */
  private function endDateByMonth($startDate, $period, $freeList, int $months, $signEndDate, $freeRemark = "")
  {

    if ($period == 0) {
      $endDate = $startDate;
    } else {
      $endDate = getNextYmd($startDate, $period);
    }
    $endDate = getNextYmd($endDate, $months);

    $free = explode(".", $months);
    // 免租是小数的
    if (sizeof($free) > 1 && $free[1] > 0) {
      $intger = intval($months); //整数bai部du分zhi
      $month  = $months - $intger;
      $days   = round((365 / 12) * $month);
      $endDate = getNextYmdByDay($endDate, $days);
    }

    $free_num = 0;
    foreach ($freeList as $k => &$v) {
      if (strtotime($v['start_date']) >= strtotime($startDate) && strtotime($v['start_date']) < strtotime($endDate) && strtotime($endDate) < strtotime($signEndDate)) {
        Log::error($startDate . "开始时间" . $endDate . "结束时间" . '合同结束时间：' . $signEndDate);
        $free_num   += $v['free_num'];
        $months     += $v['free_num'];
        $freeRemark .= "免租" . $v['free_num'] . "个月|开始时间" . $v['start_date'];
      }
    }
    // Log::info(response()->json(DB::getQueryLog()));
    if ($free_num == 0) {
      return ['end_date' => $endDate, 'free_num' => $months, 'remark' => $freeRemark];
    }
    return $this->endDateByMonth($endDate, 0, $freeList, $free_num, $signEndDate, $freeRemark);
  }



  /**
   * 免租期为天计算 帐期的结束日期
   * @Author   leezhua
   * @DateTime 2020-07-04
   * @param    [type]     $startDate  [开始时间]
   * @param    [type]     $period     [周期]
   * @param    [type]     $freeList   [免租列表]
   * @param    [type]     $days       [免租天数]
   * @param    string     $freeRemark [免租备注]
   * @return   [type]                 [结束时间]
   */
  private function endDateByDays($startDate, $period, $freeList, int $days, $signEndDate, $freeRemark = "")
  {
    if ($period == 0) {
      $endDate = $startDate;
    } else {
      $endDate = getNextYmd($startDate, $period);
    }

    // Log::error("=======".$endDate.$days);
    $endDate = getNextYmdByDay($endDate, $days);
    // Log::error("-------".$endDate);
    $free_num = 0;
    foreach ($freeList as $k => &$v) {
      if (strtotime($v['start_date']) >= strtotime($startDate) && strtotime($v['start_date']) < strtotime($endDate)  && strtotime($endDate) < strtotime($signEndDate)) {
        // Log::error($v['start_date'].'++++'.$startDate."开始时间".$endDate."结束时间");
        $free_num += $v['free_num'];
        $days += $v['free_num'];
        $freeRemark .= "免租" . $v['free_num'] . "天|开始时间" . $v['start_date'];
        // Log::error("free num ".$free_num);
      }
    }
    Log::error("if else" . $free_num);
    if ($free_num == 0) {
      return ['end_date' => $endDate, 'free_num' => $days, 'remark' => $freeRemark];
    }
    return $this->endDateByDays($endDate, 0, $freeList, $free_num, $signEndDate, $freeRemark);
  }

  private function freeCount($DA)
  {
    $freeNum = 0;
    foreach ($DA['free_list'] as $k => $v) {
      // 统计在租期内的免租期
      if (strtotime($v['start_date']) >= strtotime($DA['startDate']) && strtotime($v['start_date']) < strtotime($DA['endDate'])) {
        $freeNum += $v['free_num'];
      }
    }
    return $freeNum;
  }
}
