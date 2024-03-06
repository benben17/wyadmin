<?php

namespace App\Api\Services\Bill;

use Exception;
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
        $dRecord->company_id    = $user['company_id'];
        $dRecord->operate_date  = nowTime();
        $dRecord->proj_id        = $deposit['proj_id'];
        $dRecord->bill_detail_id = $DA['id'];
        $dRecord->amount         = $DA['amount'];
        $dRecord->type          = $DA['type'];
        $dRecord->bank_id          = $deposit['bank_id'];
        $dRecord->remark        = isset($DA['remark']) ? $DA['remark'] : "";
        $dRecord->c_user        = $user['realname'];
        $dRecord->c_uid         = $user['id'];
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
        if ($v1['type'] == 1) {
          $BA['receive_amt'] = $v1['amount'];
        } elseif ($v1['type'] == 2) {
          $BA['refund_amt'] +=  $v1['amount'];
        } elseif ($v1['type'] == 3) {
          $BA['charge_amt'] +=  $v1['amount'];
        }
      }
      $BA['available_amt'] = $BA['receive_amt'] - $BA['refund_amt'] - $BA['charge_amt'];
    }
    return $BA;
  }
}
