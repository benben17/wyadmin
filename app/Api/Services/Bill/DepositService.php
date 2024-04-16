<?php

namespace App\Api\Services\Bill;

use Exception;
use App\Enums\AppEnum;
use App\Enums\DepositEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Bill\DepositRecord;
use App\Api\Models\Bill\TenantBillDetail;

/**
 * 押金流水记录
 *
 * @Author leezhua
 * @DateTime 2024-03-06
 */
class DepositService
{
  // 押金收款记录
  public function recordModel()
  {
    return new DepositRecord;
  }

  public function depositBillModel()
  {
    return new TenantBillDetail;
  }

  /**
   * 押金流水
   *
   * @Author leezhua
   * @DateTime 2024-03-06
   * @param [type] $deposit
   * @param [type] $DA
   * @param [type] $user
   *
   * @return bool
   */
  public function saveDepositRecord($deposit, $DA, $user): bool
  {
    try {
      DB::transaction(function () use ($deposit, $DA, $user) {
        $dRecord = $this->recordModel();
        $dRecord->deposit_record_no = $this->depositRecordNo();
        $dRecord->company_id     = $user['company_id'];
        $dRecord->operate_date   = nowTime();
        $dRecord->proj_id        = $deposit['proj_id'];
        $dRecord->bill_detail_id = $DA['id'];
        $dRecord->amount         = $DA['amount'];
        $dRecord->common_date    = $DA['common_date'] ?? nowYmd();
        $dRecord->type           = $DA['type'];
        $dRecord->bank_id        = $deposit['bank_id'];
        $dRecord->remark         = isset($DA['remark']) ? $DA['remark'] : "";
        $dRecord->c_user         = $user['realname'];
        $dRecord->c_uid          = $user['id'];
        $dRecord->save();
      }, 2);
      return true;
    } catch (Exception $e) {
      Log::error("收款记录保存失败." . $e);
      throw new Exception($e);
    }
  }

  private function depositRecordNo()
  {
    return "DR-" . date('ymdHis') . mt_rand(10, 99);
  }


  /**
   * 格式化 统计  押金流水
   *
   * @Author leezhua
   * @DateTime 2024-03-06
   * @param array $recordList
   *
   * @return array
   */
  public function formatDepositRecord(array $recordList): array
  {
    $BA = ['receive_amt' => 0.00, 'refund_amt' => 0.00, 'charge_amt' => 0.00, 'available_amt' => 0.00];
    if (!empty($recordList)) {
      foreach ($recordList as $v1) {
        switch ($v1['type']) {
          case DepositEnum::RecordReceive: // 押金收入
            $BA['receive_amt'] += $v1['amount'];
            break;
          case DepositEnum::RecordRefund: // 押金退款
            $BA['refund_amt'] +=  $v1['amount'];
            break;
          case DepositEnum::RecordToCharge: // 转收款
            $BA['charge_amt'] +=  $v1['amount'];
            break;
        }
      }
      $BA['available_amt'] = $BA['receive_amt'] - $BA['refund_amt'] - $BA['charge_amt'];
    }
    return $BA;
  }


  /**
   * list  列表统计
   *
   * @Author leezhua
   * @DateTime 2024-03-07
   * @param array $list
   *
   * @return array
   */
  public function depositStat(array $list): array
  {
    $stat = ['total_amt' => 0.00, 'receive_amt' => 0.00, 'refund_amt' => 0.00, 'charge_amt' => 0.00, 'discount_amt' => 0.00];
    if (empty($list)) {
      return $stat;
    }
    foreach ($list as $k => $v) {
      $stat['total_amt'] += $v['amount'];
      $stat['discount_amt'] += $v['discount_amount'];
      $record = $this->formatDepositRecord($v['deposit_record']);
      $stat['refund_amt'] += $record['refund_amt'];
      $stat['charge_amt'] += $record['charge_amt'];
      $stat['receive_amt'] += $v['receive_amount'];
      $stat['available_amt'] = $stat['receive_amt'] - $stat['refund_amt'] - $stat['charge_amt'];
    }
    $statData = array(
      ["label" => '总应收金额', "amount" =>  $stat['total_amt'], 'remark' => '押金账单总金额'],
      ["label" => '总已收金额', "amount" => $stat['receive_amt'], 'remark' => '押金账单总收款金额'],
      ["label" => '退款金额', "amount" => $stat['refund_amt'], 'remark' => '押金账单总退款金额'],
      ["label" => '转收入金额', "amount" => $stat['charge_amt'], 'remark' => '押金账单总转收入金额'],
      ["label" => '押金余额', "amount" => $stat['available_amt'], 'remark' => '押金总可用金额，总收入-退款-转收入'],
    );
    foreach ($statData as &$v) {
      $v['amount'] = number_format($v['amount'], 2);
    }
    return $statData;
  }
}
