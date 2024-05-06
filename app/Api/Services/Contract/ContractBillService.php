<?php

namespace App\Api\Services\Contract;


use UnitEnum;
use Exception;
use App\Enums\AppEnum;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Contract\ContractBill;

/**
 * 合同账单
 *
 * @Author leezhua
 * @DateTime 2021-07-11
 */
class ContractBillService
{
  public function contractBillModel()
  {
    $model = new ContractBill;
    return $model;
  }

  /**
   * 按照正常周期进行出账单
   *
   * @param array $contract
   * @param integer $leaseTerm
   * @param array $rule
   * @param integer $type
   * @return void
   */
  public function createBill($contract, $rule, int $uid)
  {
    $type = 1; //  费用
    $data = array();
    $leaseTerm = $rule['lease_term'];
    $i = 0;
    $data['total'] = 0.00;
    $billNum = ceil($leaseTerm / $rule['pay_method']);
    $bill = array();
    $freeType = $contract['free_type']; // 免租类型 1 按月 2 按天
    while ($i < $billNum) {
      $remark = "";
      $period  = $rule['pay_method'];
      $bill[$i]['fee_type']         = $rule['fee_type'];
      $bill[$i]['price']            = $rule['unit_price'];
      $bill[$i]['unit_price_label'] = $rule['price_type'] == 1 ? AppEnum::dayPrice : AppEnum::monthPrice;

      if ($i == 0) { // 第一次账单 收费日期为签合同日期开始日期为合同开始日期
        $startDate = formatYmd($rule['start_date']);
        $bill[$i]['charge_date'] = getPreYmd($startDate, 1);
      } else {
        // 收费日期根据提前几个月（ahead_pay_month）算出来的
        $chargeDate = getNextYmd($startDate, $period - $rule['ahead_pay_month']);
        $startDate = getNextYmd($startDate, $period);
        $bill[$i]['charge_date'] = date("Y-m-" . $rule['bill_day'], strtotime($chargeDate));
      }

      // 收款日期，根据合同的收款日走
      $endDate = getEndNextYmd($startDate, $period);
      $increaseAmt = 0.00;
      $this->increaseMonthAmt($rule, $startDate, $endDate, $freeType, 0, $increaseAmt, $remark);
      $bill[$i]['amount'] =  $increaseAmt;
      $bill[$i]['start_date'] = formatYmd($startDate);
      if ($i + 1 != $billNum) {
        $bill[$i]['end_date'] = $endDate;
        $bill[$i]['amount'] = $increaseAmt;
        // numFormat($period * $rule['month_amt']);
      } else { // 最后一个租期
        $bill[$i]['end_date'] = formatYmd($rule['end_date']);
        // $lastPeriod = $leaseTerm - ($period * $i);
        // $bill[$i]['amount'] = numFormat($lastPeriod * $rule['month_amt']);
        $bill[$i]['amount'] = $increaseAmt;
      }

      // 如有免租，账单期内减免
      $freeList = $contract['free_list'];
      if ($rule['fee_type'] == AppEnum::rentFeeType && $contract['free_type'] && $freeList) {
        $free = $this->freeRental($freeList, $rule, $bill[$i]['start_date'], $bill[$i]['end_date'], $contract['free_type'], $uid);
        if ($free) {
          $remark = $free['remark'];
          $bill[$i]['amount'] -= $free['free_amt'];
        }
      }
      $bill[$i]['remark'] = $remark;
      $bill[$i]['bill_date'] = formatYmd($bill[$i]['start_date']) . '至' . formatYmd($bill[$i]['end_date']);
      $bill[$i]['bill_num'] = $i + 1;
      $data['total'] += $bill[$i]['amount'];
      $i++;
    }
    $data['total'] = numFormat($data['total']);
    $data['bill'] = $bill;
    $data['fee_type_label'] = getFeeNameById($rule['fee_type'])['fee_name'];

    return $data;
  }

  /**
   *  遇免租计算管理费收款账单
   *
   * @param [array] $contract
   * @param [integer] $leaseTerm
   * @param [integer] $uid
   * @param integer $type
   * @return void
   */
  public function createManagerFeeBill($contract, $rule, int $uid)
  {
    $type = 1; //  费用
    // Log::error("遇免租顺延" . "费用id");
    $leaseTerm = $contract['lease_term'];
    $i = 0;
    $data['total'] = 0.00;
    $bill = array();
    $startDate = formatYmd($rule['start_date']);
    $freeType = $contract['free_type']; // 免租类型 1 按月 2 按天
    while (strtotime($startDate) < strtotime($rule['end_date'])) {
      $bill[$i]['type'] = $type;
      $bill[$i]['price'] = $rule['unit_price'];
      $bill[$i]['unit_price_label']  = $rule['unit_price_label'];
      $bill[$i]['start_date'] = $startDate;

      if ($i == 0) {
        Log::error($startDate);
        $bill[$i]['charge_date']  = getPreYmd($startDate, 1);
      } else {
        $bill[$i]['charge_date'] = formatYmd(formatYmd(date("Y-m-" . $rule['bill_day'], strtotime(getPreYmd($startDate, $rule['ahead_pay_month'])))));
      }

      // 当 第二期账单收款日小于合同签订日期取合同签订时间
      // if (strtotime($bill[$i]['charge_date']) < strtotime($rule['start_date'])) {
      //   $bill[$i]['charge_date'] = $rule['start_date'];
      // }
      $bill[$i]['amount'] = numFormat($rule['month_amt'] * $rule['pay_method']);
      $endDate = date("Y-m-t", strtotime(getYmdPlusMonths($startDate, $rule['pay_method'] - 1)));
      $bill[$i]['end_date'] = formatYmd($endDate);

      // 最后一期账单
      if (strtotime($endDate) >= strtotime($rule['end_date'])) {
        // $days =  diffDays($startDate,  $rule['end_date']);
        $bill[$i]['end_date'] = formatYmd($rule['end_date']);
        // Log::error($days . "最后一期天数");
        $bill[$i]['amount'] = numFormat($rule['month_amt'] * $leaseTerm - $data['total']);
      }

      // 如有免租，账单期内不减免但是会加长收缴管理费时间
      $freeList = $contract['free_list'];
      if ($rule['fee_type'] == AppEnum::rentFeeType && $contract['free_type'] && $freeList) {
        $free = $this->freeRental($freeList, $rule, $bill[$i]['start_date'], $bill[$i]['end_date'], $contract['free_type'], $uid);
        if ($free) {
          $bill[$i]['amount'] = $bill[$i]['amount'] + $free['free_amt'];
          $bill[$i]['remark'] = $free['remark'];
          if ($freeType == AppEnum::freeMonth) {
            $bill[$i]['end_date'] = getNextYmd($endDate, $free['free_num']);
          } else if ($freeType == AppEnum::freeDay) {
            $bill[$i]['end_date'] = getNextYmdByDay($endDate, $free['free_num']);
          }
          $endDate = $bill[$i]['end_date'];
        }
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
    $data['fee_type_label'] =  getFeeNameById($rule['fee_type'])['fee_name'];

    return $data;
  }

  /**
   * 根据不同单价计算金额
   *
   * @param array   $rules 规则信息
   * @param integer $days  计算天数
   * @param integer $uid   用户ID
   *
   * @return float 计算得到的金额
   */
  private function countDaysAmt($monthAmt, int $days, int $uid): float
  {
    if ($days == 0) {
      return 0.00;
    }
    $companyId = getCompanyId($uid);
    $yearDays = getVariable($companyId, 'year_days');
    // 按天计算
    return $monthAmt * ($days / ($yearDays / 12));
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
        if ($v['type'] == $type) {
          $bill[$i]['type']       = $type;
          $bill[$i]['price']      = isset($v['price']) ? $v['price'] : 0.00;
          $bill[$i]['amount']     = isset($v['amount']) ? $v['amount'] : 0.00;
          $bill[$i]['charge_date'] = isset($v['charge_date']) ? $v['charge_date'] : $v['start_date'];
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
          $data['fee_type_label'] = '押金';
        }
      }
    }
    return $data;
  }

  private $delayFreeNum = 0; // 首期免租期 天或者月
  /**
   * 根据免租规则做是否延期或者核减金额计算
   * 按照帐期生成应缴账单 
   * @Author   leezhua
   * @DateTime 2020-07-04
   * @param    [type]     $DA       [description]
   * @param    string     $billType [description]
   * @return   [type]               [description]
   */
  public function createBillByDelay($DA, $rule, $uid)
  {
    $i = 0;
    $data['total'] = 0;
    $period  = $rule['pay_method'];
    // $startDate = $DA['startDate'];
    $freeType = $DA['free_type'];
    $ceil = ceil($rule['lease_term'] / $period);
    Log::error($ceil . "aaa" . $DA['lease_term']);
    $bill = array();
    $remark = "";
    for ($i = 0; $i <= $ceil; $i++) {
      $remark = "";
      $bill[$i]['type'] = 1;
      $bill[$i]['fee_type'] = $rule['fee_type'];
      $bill[$i]['price'] = numFormat($rule['unit_price']);
      if (!$rule['unit_price_label']) {  // 处理标签
        $rule['unit_price_label'] = $rule['price_type'] == 1 ? AppEnum::dayPrice : AppEnum::monthPrice;
      }
      $bill[$i]['unit_price_label'] = $rule['unit_price_label'];
      if ($i == 0) {
        $startDate = $rule['start_date'];
        $bill[$i]['charge_date'] = getPreYmd($startDate, 1);
      } else {  // 其他累加
        $startDate = $endDate;
        $chargeDate = getPreYmd($endDate, $rule['ahead_pay_month']);
        $bill[$i]['charge_date'] = date("Y-m-" . $rule['bill_day'], strtotime($chargeDate));
      }

      $endDate = getYmdPlusMonths($startDate, $period);
      // 免租处理
      $freeNum = 0; // 免租时间 月或者天
      if (!empty($DA['free_list'])) {
        $freeList = $DA['free_list'];
        if ($freeType == AppEnum::freeMonth) {
          // 按月免租，计算账单结束日期
          $billEndDate = $this->endDateByMonth($startDate, $period, $freeList, $rule,  $freeNum, $remark);
        } elseif ($freeType == AppEnum::freeDay) {
          // 按天免租，计算账单结束日期
          $billEndDate = $this->endDateByDays($startDate, $period, $freeList,  $rule, $freeNum, $remark);
        }
        // 设置结束日期
        $endDate = $billEndDate;
      }

      // 账单逻辑
      $bill[$i]['start_date'] = $startDate;
      // 判断是否最后一个账单
      $increaseAmt = 0.00;
      // $freeAmt = 0.00;
      if ($endDate >= $rule['end_date']) {  //如果账单结束日期大于或者等于合同日期的时候 处理最后一个账单 并跳出
        $lastBillEndDate  = $rule['end_date'];
        //  本期免租加上顺延免租 除以周期 取余数（因为根据时间 ，免租时长大于一个周期，就不生成最后一个周期）
        $freeNum = ($this->delayFreeNum + $freeNum) % $period;
        $this->increaseMonthAmt($rule, $startDate, $lastBillEndDate, $freeType, $freeNum, $increaseAmt, $remark);
        $remark .= "最后一个账期";

        $bill[$i]['end_date'] = $rule['end_date']; // 结束日期为合同结束日期
      } else {
        $this->increaseMonthAmt($rule, $startDate, $endDate, $freeType, $freeNum, $increaseAmt, $remark);
        $bill[$i]['end_date'] = getPreYmdByDay($endDate, 1);
      }

      $bill[$i]['amount']     = $increaseAmt;
      $bill[$i]['bill_date']  = $startDate . "至" . $bill[$i]['end_date'];
      $data['total']         += $bill[$i]['amount'];
      $bill[$i]['remark']     = "面积:" . $rule['area_num'] . "|" . $remark;

      if (strtotime($endDate) >= strtotime($rule['end_date'])) {
        break;
      }
    }
    $data['bill'] = $bill;
    $data['fee_type_label'] = getFeeNameById($rule['fee_type'])['fee_name'];
    return $data;
  }

  /**
   * 处理租金涨租
   * @Author leezhua
   * @Date 2024-04-09
   * @param mixed $rule 
   * @param mixed $startDate 
   * @param mixed $endDate 
   * @param float $increaseAmt 
   * @param string $remark 
   * @return void 
   */
  public function increaseMonthAmt($rule, $startDate, $endDate, $freeType, $freeNum, &$increaseAmt = 0.00, &$remark = "")
  {
    $uid = auth('api')->user()->id;
    $increaseAmt  = 0.00;
    $isFree = $rule['fee_type'] == AppEnum::rentFeeType;

    // 没有涨租规则
    if (!$rule['increase_date'] && !$rule['increase_start_period']) {
      $increaseAmt = $rule['month_amt'] * $rule['pay_method'];
      if ($isFree) {
        $increaseAmt -= $this->countFreeAmt($rule['month_amt'], $rule, $endDate, $freeNum, $freeType, $uid);
      }
      return;
    }

    // 涨租规则
    $increaseStartDate = $rule['increase_date'];
    $increaseTotalPeriod = $rule['lease_term'] - $rule['increase_start_period'] + 1;
    $increaseEndDate = getYmdPlusMonths($increaseStartDate, $increaseTotalPeriod);
    $freeAmt = 0.00;
    // $endDate = ;
    // 计算的最后一天日期 = 账单结束日期+1
    $calculateEndDate = getPreYmdByDay($endDate, 1);
    for ($monthIndex = 1; $monthIndex <= $rule['pay_method']; $monthIndex++) {
      $monthAmt = $rule['month_amt'];
      $feeStartDate = getYmdPlusMonths($startDate, $monthIndex - 1); // 涨租开始日期
      $feeEndDate = getYmdPlusMonths($startDate, $monthIndex);
      if (strtotime($feeStartDate) >= strtotime($calculateEndDate)) {
        break;
      }
      // 判断是否在涨租期内
      $isIncrease = strtotime($increaseStartDate) <= strtotime($feeStartDate) && strtotime($increaseEndDate) >= strtotime($feeEndDate);
      if ($isIncrease) {
        $monthAmt = $monthAmt * (100 + $rule['increase_rate']) / 100;
        $remarkEndDate = getPreYmdByDay($feeEndDate, 1);
        $remark .=  "|" . $feeStartDate . "至" . $remarkEndDate . "|涨租" . $rule['increase_rate'] . "%|";
      }
      $increaseAmt += $monthAmt;
    }
    if ($isFree) {
      $freeAmt = $this->countFreeAmt($monthAmt, $rule, $endDate, $freeNum, $freeType, $uid);
      $remark .= "请核对金额";
      $increaseAmt -= $freeAmt;
    }
  }

  // 计算免租金额
  public function countFreeAmt($monthAmt, $rule, $endDate, $freeNum, $freeType, $uid)
  {
    $isLastBill = strtotime($rule['end_date']) == strtotime($endDate);
    if ($isLastBill) {
      if (AppEnum::freeMonth == $freeType) {
        // 按月的不做处理
      } elseif (AppEnum::freeDay == $freeType) {
        // 按天的才去减免
        $freeNum = $this->delayFreeNum;
      }
    }

    return $freeType == AppEnum::freeMonth ?
      $monthAmt * $freeNum
      : $this->countDaysAmt($monthAmt, $freeNum, $uid);
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
  private function freeRental($freeList, $rentRule, String $billStart, String $billEnd, $freeType, $uid)
  {
    $free_num = 0;
    $free_amt = 0.00;
    $freeRemark = "";
    if (!$freeList) {
      return false;
    }
    foreach ($freeList as $k => $v) {
      if (strtotime($v['start_date']) >= strtotime($billStart) && strtotime($v['start_date']) < strtotime($billEnd)) {
        // Log::error($billStart . "开始时间" . $billEnd . "结束时间");
        $free_num += $v['free_num'];
        if ($freeType == AppEnum::freeMonth) {
          $free_amt += numFormat($rentRule['month_amt'] * $free_num);
          $freeRemark .= "免租" . $v['free_num'] . "个月|免租时间" . $v['start_date'] . "-" . $v['end_date'];
        } else if ($freeType == AppEnum::freeDay) {
          $freeRemark .= "免租" . $v['free_num'] . "天|免租时间" . $v['start_date'] . "-" . $v['end_date'];
          $free_amt += $this->countDaysAmt($rentRule['month_amount'], $free_num, $uid);
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
   * @Author leezhua
   * @DateTime 2021-07-12
   * @param [type] $startDate
   * @param [type] $period
   * @param [type] $freeList
   * @param int $days
   * @param array $rule
   * @param string $freeRemark
   * @return String $endDate
   */

  private function endDateByDays($startDate, $period, $freeList, array $rule, int &$days,  &$freeRemark = "")
  {
    $signEndDate = $rule['end_date'];
    $endDate = getNextYmd($startDate, $period);
    $free_num = 0;

    // foreach ($freeList as $k => $free) {
    //   $freeStartDate = strtotime($free['start_date']);
    //   $freeEndDate = strtotime($free['end_date']);
    //   $billStartDate = strtotime($startDate);
    //   $billEndDate = strtotime($endDate);
    //   // 免租开始时间大于等于账单开始时间，免租开始时间小于账单结束时间，账单结束时间小于合同结束时间
    //   if (
    //     $freeStartDate >= $billStartDate &&
    //     $freeStartDate <= $billEndDate &&
    //     $freeEndDate <= $billEndDate
    //   ) {
    //     $free_num += $free['free_num'];
    //     // $days += $free_num;
    //     $remarkDate = $free['start_date'];
    //     if ($free['bill_date_delay']) {
    //       $this->delayFreeNum = $free['free_num'];
    //       $endDate = getNextYmdByDay($endDate, $free_num);
    //     } else {
    //       $days += $free_num;
    //     }
    //     $freeRemark .= "免租" . $days . "天｜开始" . $remarkDate;
    //     // if ($freeStartDate == strtotime($rule['start_date'])) {
    //   } else if ( // 免租开始时间小于账单开始时间，免租结束时间大于账单开始时间，免租结束时间小于账单结束时间
    //     $freeStartDate < $billStartDate &&
    //     $freeEndDate > $billStartDate &&
    //     $freeEndDate <= $billEndDate
    //   ) {
    //     $free_num += diffDays(getPreYmdByDay($startDate, 1), $free['end_date']);
    //     $remarkDate = $startDate;
    //     if ($free['bill_date_delay']) {
    //       $this->delayFreeNum = $free['free_num'];
    //       $endDate = getNextYmdByDay($endDate, $free_num);
    //     } else {
    //       $days += $free_num;
    //     }
    //     $freeRemark .= "免租" . $days . "天｜开始" . $remarkDate;
    //   } else if ( // 免租开始时间大于账单开始时间，免租结束时间大于账单结束时间，免租开始时间小于账单结束时间
    //     $freeStartDate > $billStartDate &&
    //     $freeEndDate > $billEndDate &&
    //     $freeStartDate < $billEndDate
    //   ) {
    //     $free_num += diffDays($free['start_date'], $endDate);
    //     $remarkDate = $free['start_date'];
    //     if ($free['bill_date_delay']) {
    //       $this->delayFreeNum = $free['free_num'];
    //       $endDate = getNextYmdByDay($endDate, $free_num);
    //     } else {
    //       $days += $free_num;
    //     }
    //     $freeRemark .= "免租" . $days . "天｜开始" . $remarkDate;
    //   }
    // }
    foreach ($freeList as $k => $free) {
      $freeStartDate = strtotime($free['start_date']);   // 免租开始时间
      $freeEndDate   = strtotime($free['end_date']); // 免租结束时间
      $billStartDate = strtotime($startDate); // 账单开始时间
      $billEndDate   = strtotime($endDate); // 账单结束时间

      $isFreeDuringBill = false;
      $remarkDate = '';
      // 免租开始时间大于等于账单开始时间，免租开始时间小于账单结束时间，账单结束时间小于合同结束时间
      if ($freeStartDate >= $billStartDate && $freeStartDate <= $billEndDate && $freeEndDate <= $billEndDate) {
        $free_num += $free['free_num'];
        $remarkDate = $free['start_date'];
        $isFreeDuringBill = true;
      } else if ($freeStartDate < $billStartDate && $freeEndDate > $billStartDate && $freeEndDate <= $billEndDate) {
        // 免租开始时间小于账单开始时间，免租结束时间大于账单开始时间，免租结束时间小于账单结束时间
        $free_num += diffDays(getPreYmdByDay($startDate, 1), $free['end_date']);
        $remarkDate = $startDate;
        $isFreeDuringBill = true;
      } else if ($freeStartDate > $billStartDate && $freeEndDate > $billEndDate && $freeStartDate < $billEndDate) {
        // 免租开始时间大于账单开始时间，免租结束时间大于账单结束时间，免租开始时间小于账单结束时间
        $free_num += diffDays($free['start_date'], $endDate);
        $remarkDate = $free['start_date'];
        $isFreeDuringBill = true;
      }

      if ($isFreeDuringBill) {
        if ($free['bill_date_delay'] == AppEnum::billDelay) {
          $this->delayFreeNum += $free['free_num'];
          $endDate = getNextYmdByDay($endDate, $free['free_num']);
        } else {
          $days += $free_num;
        }
        $freeRemark .= "免租" . $free['free_num'] . "天｜开始:" . $remarkDate;
      }
    }

    if ($free_num == 0) {
      return $endDate;
    }
    return $this->endDateByDays($endDate, 0, $freeList, $rule, $free_num, $freeRemark);
  }


  /**
   * 免租期为月计算 帐期的结束日期
   * @Author leezhua
   * @Date 2024-04-14
   * @param string $startDate 账单开始时间
   * @param mixed $period  帐期 几个月
   * @param mixed $freeList  免租期列表
   * @param array $rule  费用规则
   * @param int $months  免租月数
   * @param string $freeRemark  免租备注
   * @return mixed  $endDate
   */
  private function endDateByMonth(String $startDate, $period, $freeList, array $rule, int &$months, &$freeRemark = "")
  {
    // Log::error(json_encode($rule) . "规则");
    $signEndDate = $rule['end_date'];
    $endDate = getNextYmd($startDate, $period);
    $free_num = 0;
    foreach ($freeList as $free) {
      // 备注：免租开始时间大于等于账单开始时间，免租开始时间小于账单结束时间，账单结束时间小于合同结束时间
      if (
        strtotime($free['start_date']) >= strtotime($startDate)
        && strtotime($free['start_date']) < strtotime($endDate)
        && strtotime($endDate) < strtotime($signEndDate)
      ) {
        $free_num   += $free['free_num'];
        $freeRemark .= "免租" . $free['free_num'] . "个月|开始:" . $free['start_date'];
        if ($free['bill_date_delay'] == AppEnum::billDelay) {
          $this->delayFreeNum = $free['free_num'];
          $endDate = getNextYmd($endDate, $free_num);
        } else {
          $months += $free['free_num'];
        }
      }
    }

    if ($free_num == 0) {
      return $endDate;
    }

    return $this->endDateByMonth($endDate, 0, $freeList, $rule, $months,  $freeRemark);
  }


  /**
   * 租户分摊
   *
   * @param array $bills      账单信息数组
   * @param mixed $shareRule  分摊规则对象
   * @param float $monthAmt   单价
   *
   * @return array 优化后的账单信息数组
   */
  public function applyShareTenant(array $bills, $shareRule, float $monthAmt): array
  {
    $newBills = [];
    $shareType = $shareRule->share_type;
    $shareNum = $shareRule->share_num;
    $shareFeeType = $shareRule->fee_typ;

    foreach ($bills['bill'] as &$bill) {
      $newBill = ($bill['fee_type'] == $shareFeeType) ?
        $this->calculateNewBillAmount($bill, $shareType, $shareNum, $monthAmt) : $bill;
      $bill['amount'] =  $bill['amount'] - $newBill['amount'];
      $newBills[] = $newBill;
    }

    $tenantId = $shareRule['id'];
    $bills[$tenantId]['bill'] = $newBills;

    return $bills;
  }

  /**
   * 计算新的账单金额
   *
   * @param array $bill      原始账单信息
   * @param int   $shareType 分摊类型 1 比例 2 固定金额 3 固定面积
   * @param float $shareNum  分摊数值
   * @param float $monthAmt  单价
   *
   * @return array 新的账单信息
   */
  private function calculateNewBillAmount(array $bill, int $shareType, float $shareNum, float $monthAmt): array
  {
    $newBill = $bill;
    switch ($shareType) {
      case 1: // 比例
        $newBill['amount'] = numFormat($bill['amount'] * $shareNum / 100);
        break;

      case 2: // 面积
        $newBill['amount'] = numFormat($bill['amount'] - $monthAmt * $shareNum);
        break;

      case 3: // 固定金额
        $newBill['amount'] = $shareNum;
        break;

      default:
        // Handle unsupported share types or provide a default behavior
        break;
    }

    return $newBill;
  }



  /**
   * 处理合同旧账单
   *
   * @param array $feeRule
   * @param integer $contractId
   * @return array
   */
  public function processOldBill(int $contractId, array $feeRule): array
  {

    $oldBills = $this->contractBillModel()->where('contract_id', $contractId)->get();
    /**
     *  费用规则开始时间大于账单的结束时间 is_valid 设置为 1 
     *  费用规则开始时间在账单的开始时间和结束时间之间  is_valid 设置为 1
     *  费用规则的开始时间小于账单的开始时间 is_valid 设置为 0
     */
    foreach ($oldBills as $bill) {
      $bill->is_valid = 0;
      $billStartTime = strtotime($bill->start_date);
      $billEndTime = strtotime($bill->end_date);

      foreach ($feeRule as $rule) {
        $ruleStartTime = strtotime($rule['start_date']);

        if ($ruleStartTime > $billEndTime) {
          $bill->is_valid = 1;
          break;
        } else if ($ruleStartTime >= $billStartTime && $ruleStartTime <= $billEndTime) {
          $bill->is_valid = 1;
          break;
        }
      }
      // 未收款的账单设置为可删除状态
      if ($bill->status == AppEnum::feeStatusUnReceive && $bill->receive_amount == 0) {
        $bill->is_deleted = 1;
      }
    }
    return $oldBills->isEmpty() ? [] : $oldBills->toArray();
  }

  /**
   * 生成账单
   *
   * @param [type] $contract
   * @param [type] $rule
   * @param integer $type
   * @return void
   */
  // 根据规则生成合同账单
  public function generateBill($contract, $uid)
  {
    $data = array();
    $fee_list = array();
    foreach ($contract['bill_rule'] as $rule) {
      $feeList = array();
      if ($rule['type'] != 1) { // 押金类型不生成的
        continue;
      }
      // if ($rule['bill_type'] == 1) {  // 正常账期
      //     $feeList = $billService->createBill($contract, $rule, $this->uid);
      // } else if ($rule['bill_type'] == 2) { // 自然月账期
      //     $feeList = $billService->createBillziranyue($contract, $rule, $this->uid);
      // } 
      // } else if ($rule['bill_type'] == 2) { // 只有租金走账期顺延
      if (strtotime($rule['end_date']) > strtotime($contract['end_date'])) {
        throw new Exception("账单结束日期不能大于合同结束日期");
      }

      if ($rule['fee_type'] == AppEnum::rentFeeType || $rule['fee_type'] == AppEnum::managerFeeType) {
        $feeList = $this->createBillByDelay($contract, $rule, $uid);
      }
      // else {
      //   $feeList = $this->createBill($contract, $rule, $uid);
      // }
      array_push($fee_list, $feeList);
    }
    $data['fee_bill']  = $fee_list;
    if ($contract['deposit_rule']) {
      $data['deposit_bill'] = array();
      $depositBill = $this->createDepositBill($contract['deposit_rule'], $uid);
      if ($depositBill) {
        array_push($data['deposit_bill'], $depositBill);
      }
    }

    return $data;
  }


  // /**
  //  * 更新账单，单条数据更新
  //  * 传入bill 根据bill 里面id 重新复值并更新
  //  * 
  //  */
  public function updateContractBill($bill)
  {
    $billModel = $this->contractBillModel();
    $billId = $bill['id'];
    $bill = array(
      'company_id'  => $bill['company_id'],
      'project_id'  => $bill['project_id'],
      'contract_id' => $bill['contract_id'],
      'fee_type'    => $bill['fee_type'],
      'type'        => $bill['type'],
      'price'       => $bill['price'],
      'price_label' => $bill['price_label'],
      'amount'      => $bill['amount'],
      'remark'      => $bill['remark'],
      'bill_date'   => $bill['bill_date'],
      'bill_num'    => $bill['bill_num'],
      'charge_date' => $bill['charge_date'],
      'start_date'  => $bill['start_date'],
      'end_date'    => $bill['end_date'],
    );
    $billModel->where('id', $billId)->update($bill);
  }
}
