<?php

namespace App\Api\Services\Contract;

use App\Api\Models\Contract\BillRule;
use App\Api\Models\Contract\ContractFreePeriod;
use App\Api\Services\Company\FeeTypeService;
use App\Api\Services\Contract\BillRuleService;
use App\Enums\AppEnum;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * 合同账单
 *
 * @Author leezhua
 * @DateTime 2021-07-11
 */
class ContractBillService

{

  /**
   * 按照正常周期进行出账单
   *
   * @param [type] $contract
   * @param integer $leaseTerm
   * @param integer $uid
   * @param integer $type
   * @return void
   */
  public function createBill($contract, $rule, int $uid)
  {
    $type = 1; //  费用
    $data = array();
    // Log::error(json_encode($feetype) . "费用id");
    DB::enableQueryLog();

    $leaseTerm = $contract['lease_term'];

    // Log::error("创建账单" . $rule['id']);
    $i = 0;
    $data['total'] = 0.00;
    $billNum = ceil($leaseTerm / $rule['pay_method']);
    $bill = array();

    while ($i < $billNum) {
      $period  = $rule['pay_method'];
      $bill[$i]['fee_type'] = $rule['fee_type'];
      $bill[$i]['price'] = $rule['unit_price'] . $rule['unit_price_label'];
      if ($i == 0) { // 第一次账单 收费日期为签合同日期开始日期为合同开始日期
        $startDate = formatYmd($rule['start_date']);
        $bill[$i]['charge_date'] = formatYmd($rule['start_date']);
      } else {
        // 收费日期根据提前几个月（ahead_pay_month）算出来的
        $chargeDate = getNextYmd($startDate, $period - $rule['ahead_pay_month']);
        $startDate = getNextYmd($startDate, $period);
        $bill[$i]['charge_date'] = date("Y-m-" . $rule['bill_day'], strtotime($chargeDate));
      }
      // 收款日期，根据合同的收款日走
      $endDate = getEndNextYmd($startDate, $period);
      $bill[$i]['start_date'] = formatYmd($startDate);
      if ($i + 1 != $billNum) {
        $bill[$i]['end_date'] = $endDate;
        $bill[$i]['amount'] = numFormat($period * $rule['month_amt']);
      } else { // 最后一个租期
        $bill[$i]['end_date'] = formatYmd($rule['end_date']);
        $lastPeriod = $leaseTerm - ($period * $i);
        $bill[$i]['amount'] = numFormat($lastPeriod * $rule['month_amt']);
      }
      // 如有免租，账单期内减免
      if ($rule['fee_type'] == AppEnum::rentFeeType && $contract['free_type'] && !$contract['free_list']) {
        $free = $this->freeByMonth($contract['free_list'], $rule, $bill[$i]['start_date'], $bill[$i]['end_date'], $contract['free_type'], $uid);
        if ($free) {
          $bill[$i]['amount'] = $bill[$i]['amount'] - $free['free_amt'];
          $bill[$i]['remark'] = $free['remark'];
        }
      }
      $bill[$i]['bill_date'] =  formatYmd($bill[$i]['start_date']) . '至' . formatYmd($bill[$i]['end_date']);
      $data['total'] += $bill[$i]['amount'];
      $i++;
    }
    $data['total'] = numFormat($data['total']);
    $data['bill'] = $bill;
    $data['fee_type'] =  getFeeNameById($rule['fee_type'])['fee_name'];

    return $data;
  }

  /**
   * 根据自然月计算账单
   *
   * @param [array] $contract
   * @param [integer] $leaseTerm
   * @param [integer] $uid
   * @param integer $type
   * @return void
   */
  public function createBillziranyue($contract, $rule, int $uid)
  {
    $type = 1; //  费用
    // Log::error(json_encode($feetype) . "费用id");
    $leaseTerm = $contract['lease_term'];
    $i = 0;
    $data['total'] = 0.00;
    $bill = array();
    $startDate = formatYmd($rule['start_date']);
    while (strtotime($startDate) < strtotime($rule['end_date'])) {
      $bill[$i]['type'] = $type;
      $bill[$i]['price'] = $rule['unit_price'] . $rule['unit_price_label'];
      $bill[$i]['start_date'] = $startDate;
      // Log::error($startDate);
      $bill[$i]['charge_date'] = formatYmd(formatYmd(date("Y-m-" . $rule['bill_day'], strtotime(getPreYmd($startDate, $rule['ahead_pay_month'])))));
      $bill[$i]['amount'] = numFormat($rule['month_amt'] * $rule['pay_method']);
      $endDate = date("Y-m-t", strtotime(getYmdPlusMonths($startDate, $rule['pay_method'] - 1)));
      $bill[$i]['end_date'] = formatYmd($endDate);
      if (strtotime($startDate) == strtotime($rule['start_date'])) {
        if (date('d', strtotime($startDate)) != "01") {
          $days =  diffDays($startDate, date('Y-m-t', strtotime($startDate)));
          // Log::error("第一期账单天数：" . numFormat($rule['month_amt'] * ($rule['pay_method'] - 1)));
          $daysAmt = $this->countDaysAmt($rule, $days, $uid);
          $bill[$i]['amount'] = numFormat($daysAmt + ($rule['month_amt'] * ($rule['pay_method'] - 1)));
          $bill[$i]['charge_date'] = $startDate;
        } else {
          $bill[$i]['charge_date'] = formatYmd(date("Y-m-" . $rule['bill_day'], strtotime(getPreYmd($startDate, 1))));
        }
      }
      if (strtotime($endDate) > strtotime($rule['end_date'])) {
        $days =  diffDays($startDate,  $rule['end_date']);
        $bill[$i]['end_date'] = formatYmd($rule['end_date']);
        // Log::error($days . "最后一期天数");
        $bill[$i]['amount'] = numFormat($rule['month_amt'] * $leaseTerm - $data['total']);
      }
      $bill[$i]['bill_date'] =  formatYmd($bill[$i]['start_date']) . '至' . formatYmd($bill[$i]['end_date']);

      $bill[$i]['mouth_amt'] = $rule['month_amt'];
      $bill[$i]['fee_type']  = $rule['fee_type'];
      $bill[$i]['bill_num']  = $i + 1;
      $data['total'] += $bill[$i]['amount'];
      $i++;
      $startDate = getNextYmd(date('Y-m-01', strtotime($endDate)), 1);
    }
    $data['total'] = numFormat($data['total']);
    $data['bill'] = $bill;
    $data['fee_type'] =  getFeeNameById($rule['fee_type'])['fee_name'];

    return $data;
  }

  /**
   * 根据不同单价计算金额
   *
   * @param [type] $rules
   * @param integer $days
   * @param [type] $uid
   * @return void
   */
  private function countDaysAmt($rules, int $days, int $uid): float
  {
    $amount = 0.00;
    if (!$rules) {
      return $amount;
    }
    $yearDays = getVariable(getCompanyId($uid), 'year_days');
    if ($rules['price_type'] == 1) {
      $amount = numFormat($rules['unit_price'] * $days * $rules['area_num']);
      // Log::info($amount);
      // Log::error("info:" . $rules['unit_price'] . "-" . $rules['area_num'] . "-" . $days);
    } else if ($rules['price_type'] == 2) {
      $amount = numFormat($rules['month_amt'] * 12 / $yearDays * $days);
    }
    return $amount;
  }

  /**
   * 各种押金计算
   *
   * @param [type] $contractId
   * @param [type] $uid
   * @param integer $type
   * @return void
   */
  public function createDepositBill($billRules)
  {
    $type = 2;
    $data = array();
    $total = 0.00;
    $bill = array();
    if ($billRules) {
      $i = 0;
      foreach ($billRules as $k => $v) {
        Log::error($v['type'] . "---------" . $type);
        if ($v['type'] == $type) {
          $bill[$i]['type']       = $type;
          $bill[$i]['amount']     = isset($v['amount']) ? $v['amount'] : 0.00;
          $bill[$i]['charge_date'] = formatYmd($v['start_date']);
          $bill[$i]['start_date'] = formatYmd($v['start_date']);
          $bill[$i]['end_date']   = formatYmd($v['end_date']);
          $bill[$i]['bill_date']  = formatYmd($v['start_date']) . "至" . formatYmd($v['end_date']);
          $bill[$i]['remark']     = isset($v['remark']) ? $v['remark'] : "";
          $bill[$i]['fee_type']   = $v['fee_type'];
          $bill[$i]['fee_type_label']   = getFeeNameById($v['fee_type'])['fee_name'];
          $total += $bill[$i]['amount'];
          $i++;
        }
        if ($bill) {
          $data['total'] = numFormat($total);
          $data['bill'] = $bill;
          $data['fee_type'] = '押金';
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
          return '元/㎡·月';
        }
      }
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return "";
    }
  }

  /**
   * 正常账单，计算免租
   *
   * @param [type] $contractId
   * @param [type] $billStart
   * @param [type] $billEnd
   * @param [type] $freeList
   * @param string $freeRemark
   * @return void
   */
  private function freeByMonth($freeList, $rentRule, String $billStart, String $billEnd, $freeType, $uid)
  {
    $free_num = 0;
    $free_amt = 0.00;
    $freeRemark = "";
    if (!$freeList) {
      return false;
    }
    foreach ($freeList as $k => $v) {
      if (strtotime($v['start_date']) >= strtotime($billStart) && strtotime($v['start_date']) < strtotime($billEnd)) {
        Log::error($billStart . "开始时间" . $billEnd . "结束时间");
        $free_num   += $v['free_num'];
        if ($freeType == 1) {
          $free_amt += numFormat($rentRule['mounth_amt'] * $free_num);
          $freeRemark .= "免租" . $v['free_num'] . "个月|免租时间" . $v['start_date'] . "-" . $v['end_date'];
        } else if ($freeType == 2) {
          $freeRemark .= "免租" . $v['free_num'] . "天|免租时间" . $v['start_date'] . "-" . $v['end_date'];
          $free_amt += $this->countDaysAmt($rentRule, $free_num, $uid);
        }
      }
    }
    // 当有免租期生成对应的数据并返回
    if ($free_num > 0) {
      return array(
        'free_amt' => $free_amt,
        'free_num' => $free_num,
        'remark' => $freeRemark
      );
    } else {
      return false;
    }
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

  /**
   * 统计免租时长
   *
   * @Author leezhua
   * @DateTime 2021-07-11
   * @param [type] $DA
   *
   * @return void
   */
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
