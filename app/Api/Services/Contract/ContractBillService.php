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
    // Log::error(json_encode($feetype) . "费用id");
    $leaseTerm = $rule['lease_term'];
    // Log::error("创建账单" . $rule['id']);
    $i = 0;
    $data['total'] = 0.00;
    $billNum = ceil($leaseTerm / $rule['pay_method']);
    $bill = array();

    while ($i < $billNum) {
      $remark = "";
      $period  = $rule['pay_method'];
      $bill[$i]['fee_type'] = $rule['fee_type'];
      $bill[$i]['price'] = $rule['unit_price'];
      $bill[$i]['unit_price_label']  = $rule['unit_price_label'];

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
      if ($rule['fee_type'] == AppEnum::rentFeeType && $contract['free_type'] && $contract['free_list']) {
        $free = $this->freeRental($contract['free_list'], $rule, $bill[$i]['start_date'], $bill[$i]['end_date'], $contract['free_type'], $uid);
        if ($free) {
          $bill[$i]['amount'] = $bill[$i]['amount'] - $free['free_amt'];
          $remark = $free['remark'];
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
    $data['fee_type'] = getFeeNameById($rule['fee_type'])['fee_name'];

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
    // Log::error("遇免租顺延" . "费用id");
    $leaseTerm = $contract['lease_term'];
    $i = 0;
    $data['total'] = 0.00;
    $bill = array();
    $startDate = formatYmd($rule['start_date']);
    while (strtotime($startDate) < strtotime($rule['end_date'])) {
      $bill[$i]['type'] = $type;
      $bill[$i]['price'] = $rule['unit_price'];
      $bill[$i]['unit_price_label']  = $rule['unit_price_label'];
      $bill[$i]['start_date'] = $startDate;

      if ($i == 0) {
        Log::error($startDate);
        $bill[$i]['charge_date']  = $startDate;
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
      // if (strtotime($startDate) == strtotime($rule['start_date'])) {
      //   if (date('d', strtotime($startDate)) != "01") {
      //     $days =  diffDays($startDate, date('Y-m-t', strtotime($startDate)));
      //     // Log::error("第一期账单天数：" . numFormat($rule['month_amt'] * ($rule['pay_method'] - 1)));
      //     $daysAmt = $this->countDaysAmt($rule, $days, $uid);
      //     $bill[$i]['amount'] = numFormat($daysAmt + ($rule['month_amt'] * ($rule['pay_method'] - 1)));
      //     $bill[$i]['charge_date'] = $startDate;
      //   } else {
      //     $bill[$i]['charge_date'] = formatYmd(date("Y-m-" . $rule['bill_day'], strtotime(getPreYmd($startDate, 1))));
      //   }
      // }
      if (strtotime($endDate) > strtotime($rule['end_date'])) {
        $days =  diffDays($startDate,  $rule['end_date']);
        $bill[$i]['end_date'] = formatYmd($rule['end_date']);
        // Log::error($days . "最后一期天数");
        $bill[$i]['amount'] = numFormat($rule['month_amt'] * $leaseTerm - $data['total']);
      }
      if ($rule['fee_type'] == AppEnum::rentFeeType && $contract['free_type'] && $contract['free_list']) {
        $free = $this->freeRental($contract['free_list'], $rule, $bill[$i]['start_date'], $bill[$i]['end_date'], $contract['free_type'], $uid);
        if ($free) {
          $bill[$i]['amount'] = $bill[$i]['amount'] - $free['free_amt'];
          $bill[$i]['remark'] = $free['remark'];
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
    $data['fee_type'] =  getFeeNameById($rule['fee_type'])['fee_name'];

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
  private function countDaysAmt(array $rules, int $days, int $uid): float
  {
    if (empty($rules)) {
      return 0.00;
    }

    $amount = 0.00;
    $companyId = getCompanyId($uid);
    $yearDays = getVariable($companyId, 'year_days');

    if ($rules['price_type'] == 1) {
      $amount = numFormat($rules['unit_price'] * $days * $rules['area_num']);
      // 可能的日志记录
      // Log::info($amount);
      // Log::error("info:" . $rules['unit_price'] . "-" . $rules['area_num'] . "-" . $days);
    } elseif ($rules['price_type'] == 2) {
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
  public function createBillByzhangqi($DA, $rule, $billType = "rental")
  {
    $i = 0;
    $data['total'] = 0;
    $period  = $rule['pay_method'];
    // $startDate = $DA['startDate'];

    $freeType = $DA['free_type'];
    $ceil = ceil($DA['lease_term'] / $period);
    Log::error($ceil . "aaa" . $DA['lease_term']);
    $bill = array();
    $freeNum = 0;
    $remark = "";
    for ($i = 0; $i <= $ceil; $i++) {

      $remark = "";
      $bill[$i]['type'] = 1;
      $bill[$i]['fee_type'] = $rule['fee_type'];
      $bill[$i]['price'] = numFormat($rule['unit_price']);
      $bill[$i]['unit_price_label'] = $rule['unit_price_label'];
      if ($i == 0) {
        $startDate = $rule['start_date'];
        $bill[$i]['charge_date'] = $startDate;
      } else {  // 其他累加
        $startDate = $endDate;
        $chargeDate = getPreYmd($endDate, $rule['ahead_pay_month']);
        $bill[$i]['charge_date'] = date("Y-m-" . $rule['bill_day'], strtotime($chargeDate));
      }

      $endDate = getNextYmd($startDate, $period);

      // 免租处理
      if (!empty($DA['free_list'])) {
        if ($freeType == AppEnum::freeMonth) { // 免租类型为1 的时候 按月免租
          $billEndDate = $this->endDateByMonth($startDate, $period, $DA['free_list'], $freeNum, $rule['end_date'], $remark);

          $endDate = $billEndDate;
        } else if ($freeType == AppEnum::freeDay) {
          // 按天免租 获取免租后账单的结束日期
          // 开始时间 周期 免租列表 0 合同结束时间
          $billEndDate = $this->endDateByDays($startDate, $period, $DA['free_list'], $freeNum, $rule['end_date'], $remark);
          // 天转换成月
          $endDate = $billEndDate;
        }
      }

      // 账单逻辑
      $bill[$i]['start_date'] = $startDate;
      if ($endDate >= $rule['end_date']) {  //如果账单结束日期大于或者等于合同日期的时候 处理最后一个账单 并跳出
        $bill[$i]['end_date'] = $rule['end_date'];   // 结束日期为合同结束日期
        // 按月 最后一个帐期 总金额 - 之前账单金额
        if ($freeType == AppEnum::freeMonth) {
          if ($period === $freeNum) {
            $bill[$i]['amount'] = numFormat($rule['month_amt'] * ($period - $freeNum));
          } else {
            $bill[$i]['amount'] = numFormat($rule['month_amt'] * $period);
          }
        } else { // 按天免租
          $freeAmt = $rule['month_amt'] / 30 * $freeNum;
          $bill[$i]['amount'] = numFormat($rule['month_amt'] * $period - $freeAmt);
        }

        $bill[$i]['bill_date'] = $startDate . "至" . $bill[$i]['end_date'];
        $data['total'] += $bill[$i]['amount'];
        $bill[$i]['remark'] = $remark;
        break;
      } else {
        $bill[$i]['amount'] = numFormat($rule['month_amt'] * $period);
        $bill[$i]['end_date'] = getPreYmdByDay($endDate, 1);
        $bill[$i]['bill_date'] = $startDate . "至" . $bill[$i]['end_date'];
        $data['total'] += $bill[$i]['amount'];
        $bill[$i]['remark'] = $remark;
      }
    }
    $data['bill'] = $bill;
    $data['fee_type'] = getFeeNameById($rule['fee_type'])['fee_name'];
    return $data;
  }


  // private function priceUnit($roomType, $priceType)
  // {
  //   try {
  //     if ($priceType == 1) {
  //       if ($roomType == 1) {
  //         return '元/㎡·天';
  //       } else {
  //         return '元·天';
  //       }
  //     } else {
  //       if ($roomType == 1) {
  //         return '元/㎡·天';
  //       } else {
  //         return '元/㎡·月';
  //       }
  //     }
  //   } catch (Exception $e) {
  //     Log::error($e->getMessage());
  //     return "";
  //   }
  // }

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
        $free_num   += $v['free_num'];
        if ($freeType == AppEnum::freeMonth) {
          $free_amt += numFormat($rentRule['month_amt'] * $free_num);
          $freeRemark .= "免租" . $v['free_num'] . "个月|免租时间" . $v['start_date'] . "-" . $v['end_date'];
        } else if ($freeType == AppEnum::freeDay) {
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
  private function endDateByDays($startDate, $period, $freeList, int &$days, $signEndDate, &$freeRemark = "")
  {

    $endDate = getNextYmd($startDate, $period);
    $free_num = 0;
    foreach ($freeList as $v) {
      if (strtotime($v['start_date']) >= strtotime($startDate) && strtotime($v['start_date']) < strtotime($endDate)  && strtotime($endDate) < strtotime($signEndDate)) {
        $free_num += $v['free_num'];
        $days += $v['free_num'];
        $freeRemark .= "免租" . $v['free_num'] . "天|开始时间" . $v['start_date'];
        $endDate = getNextYmdByDay($endDate, $free_num);
      }
    }

    if ($free_num == 0) {
      return $endDate;
    }
    return $this->endDateByDays($endDate, 0, $freeList, $free_num, $signEndDate, $freeRemark);
  }


  /**
   * 顺延账期 ，免租
   *
   * @Author leezhua
   * @DateTime 2021-07-12
   * @param [type] $startDate
   * @param [type] $period
   * @param [type] $freeList
   * @param [type] $months
   * @param [type] $signEndDate
   * @param string $freeRemark
   *
   * @return void
   */
  private function endDateByMonth(String $startDate, $period, $freeList, int &$months, $signEndDate, &$freeRemark = "")
  {

    $endDate = getNextYmd($startDate, $period);
    $free_num = 0;
    foreach ($freeList as $v) {
      if (strtotime($v['start_date']) >= strtotime($startDate) && strtotime($v['start_date']) < strtotime($endDate) && strtotime($endDate) < strtotime($signEndDate)) {

        $free_num += $v['free_num'];
        $months += $v['free_num'];
        $freeRemark .= "免租" . $v['free_num'] . "个月|开始时间" . $v['start_date'];
        $endDate = getNextYmd($endDate, $free_num);
      }
    }

    if ($free_num == 0) {
      return $endDate;
    }

    return $this->endDateByMonth($endDate, 0, $freeList, $months, $signEndDate, $freeRemark);
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
}
