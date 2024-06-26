<?php

namespace App\Api\Services\Bill;

use Exception;
use App\Enums\AppEnum;
use App\Enums\DepositEnum;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Api\Models\Company\FeeType;
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
    $BA = [
      'receive_amt' => 0.00,
      'refund_amt' => 0.00,
      'charge_amt' => 0.00,
      'available_amt' => 0.00
    ];
    if (!empty($recordList)) {
      foreach ($recordList as $v1) {
        switch ($v1['type']) {
          case DepositEnum::RecordReceive: // 押金收入
            $BA['receive_amt'] = bcadd($BA['receive_amt'], $v1['amount'], 2);
            break;
          case DepositEnum::RecordRefund: // 押金退款
            $BA['refund_amt'] =  bcadd($BA['refund_amt'], $v1['amount'], 2);
            break;
          case DepositEnum::RecordToCharge: // 转收款
            $BA['charge_amt'] =  bcadd($BA['charge_amt'], $v1['amount'], 2);
            break;
        }
      }
      $BA['available_amt'] = bcsub(bcsub($BA['receive_amt'], $BA['refund_amt'], 2), $BA['charge_amt'], 2);
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
  public function depositStat($query, &$data, $uid)
  {
    $feeStat = FeeType::select('fee_name', 'id', 'type')
      ->where('type', AppEnum::depositFeeType)
      ->whereIn('company_id', getCompanyIds($uid))->get()->toArray();

    $feeCount = $query->selectRaw('ifnull(sum(fee_amount),0.00) fee_amt,
        ifnull(sum(amount),0.00) total_amt,
        ifnull(sum(receive_amount),0.00) receive_amt,
        ifnull(sum(discount_amount),0.00) as discount_amt ,
        fee_type,GROUP_CONCAT(id) as detail_ids')
      ->groupBy('fee_type')->get()
      ->keyBy('fee_type');
    // $data['aaa'] = $feeCount;
    $emptyFee = [
      "total_amt" => 0.00,
      "receive_amt" => 0.00,
      "discount_amt" => 0.00,
      "unreceive_amt" => 0.00,
      'charge_amt' => 0.00,
      'refund_amt' => 0.00,
    ];
    $total = $emptyFee;

    $detail_ids = "";
    foreach ($feeStat as $k => &$fee) {
      $fee = array_merge($fee, $emptyFee);
      if (isset($feeCount[$fee['id']])) {
        $v1 = $feeCount[$fee['id']];
        $fee['total_amt']      = $v1->total_amt;
        $fee['receive_amt']    = $v1->receive_amt;
        $fee['discount_amt']   = $v1->discount_amt;
        $fee['unreceive_amt']  = bcsub(bcsub($fee['total_amt'], $fee['receive_amt'], 2), $fee['discount_amt'], 2);
        $detail_ids .= $v1->detail_ids . ",";
      }
      $fee['label'] = $fee['fee_name'];
      $fee['amount'] = $fee['total_amt'];

      $total['total_amt']     = bcadd($total['total_amt'], $fee['total_amt'], 2);
      $total['receive_amt']   = bcadd($total['receive_amt'], $fee['receive_amt'], 2);
      $total['discount_amt']  = bcadd($total['discount_amt'], $fee['discount_amt'], 2);
      $total['unreceive_amt'] = bcadd($total['unreceive_amt'], $fee['unreceive_amt'], 2);
      // $total['charge_amt']    = bcadd($total['charge_amt'], $fee['charge_amt'], 2);
      // $total['refund_amt']    = bcadd($total['refund_amt'], $fee['refund_amt'], 2);
    }
    $totalRecord = $this->recordModel()
      ->selectRaw('ifnull(sum(case type when 2 then amount else 0 end),0.00) charge_amt,
                ifnull(sum(case type when 3 then amount else 0 end),0.00) refund_amt')
      ->whereIn('bill_detail_id', explode(",", $detail_ids))->first();
    $total['charge_amt'] = $totalRecord->charge_amt; // 转收款金额
    $total['refund_amt'] = $totalRecord->refund_amt;
    $total['available_amt'] = bcsub(bcsub($total['receive_amt'], $total['refund_amt'], 2), $total['charge_amt'], 2);

    $data['total'] = num_format($total);
    $data['stat']  = num_format($feeStat);
  }


  /**
   * 押金收款
   *
   * @Author leezhua
   * @DateTime 2024-03-07
   * @param [type] $depositFee
   * @param [type] $DA
   * @param [type] $unreceiveAmt
   * @param [type] $user
   *
   * @return void
   */
  public function depositReceive($depositFee, $DA, $unreceiveAmt, $user)
  {
    try {
      DB::transaction(function () use ($depositFee, $DA, $unreceiveAmt, $user) {
        // 已收款金额+ 本次收款金额
        $receiveAmt = $DA['amount'];
        $totalReceiveAmt = bcadd($depositFee['receive_amount'], $receiveAmt, 2);
        // $unreceiveAmt = bcsub($depositFee['amount'], $depositFee['receive_amount']);
        $updateData['receive_amount'] = $totalReceiveAmt;
        // 收款金额大于未收金额
        if ($receiveAmt > $unreceiveAmt) {
          throw new Exception("收款金额不允许大于未收金额!");
        }
        // 应收和实际收款 相等时
        $totalAmt = bcsub($depositFee['amount'], $depositFee['discount_amount'], 2);
        if ($totalAmt == $totalReceiveAmt) {
          $updateData['status'] = DepositEnum::Received;
        }
        $updateData['receive_date'] = $DA['common_date'] ?? nowYmd();
        // 保存押金流水记录
        $this->saveDepositRecord($depositFee, $DA, $user);
        // 更新 押金信息 【状态，收款金额】
        $this->depositBillModel()->whereId($DA['id'])->update($updateData);
      }, 2);
    } catch (Exception $e) {
      Log::error("押金收款失败." . $e);
      throw new Exception("押金收款失败");
    }
  }

  /**
   * 押金退款
   *
   * @Author leezhua
   * @DateTime 2024-03-07
   * @param [type] $depositFee
   * @param [type] $DA
   * @param [type] $user
   *
   * @return void
   */
  public function depositRefund($depositFee, $DA, $user)
  {
    try {
      DB::transaction(function () use ($depositFee, $DA, $user) {
        $record = $this->formatDepositRecord($depositFee['deposit_record']);
        $availableAmt = $record['available_amt'];
        // Log::error($availableAmt);

        if ($availableAmt < $DA['amount']) {
          throw new Exception("此押金可用余额小于退款金额，不可操作!");
        }
        if ($availableAmt > $DA['amount']) {
          $updateData['status'] = DepositEnum::Refund; // 退款
        } else {
          $updateData['status'] = DepositEnum::Clear; // 已结清
        }
        // 插入记录
        $this->saveDepositRecord($depositFee, $DA, $user);
        // 更新押金信息
        $this->depositBillModel()->where('id', $DA['id'])->update($updateData);
      }, 2);
    } catch (Exception $e) {
      Log::error("押金退款失败." . $e);
      throw new Exception("押金退款失败");
    }
  }

  /**
   * 押金收款删除
   *
   * @Author leezhua
   * @DateTime 2024-03-07
   * @param [type] $receiveId
   *
   * @return void
   */
  public function depositReceiveDel($receiveId)
  {
    try {
      DB::transaction(function () use ($receiveId) {
        $record = $this->recordModel()->find($receiveId);
        if (empty($record)) {
          throw new Exception("收款记录不存在");
        }
        $DA = $this->depositBillModel()->find($record->bill_detail_id);
        if (empty($DA)) {
          throw new Exception("押金信息不存在");
        }
        $updateData = [
          'receive_amount' => bcsub($DA->receive_amount, $record->amount, 2),
          'status' => DepositEnum::UnReceive
        ];
        $this->depositBillModel()->where('id', $record->bill_detail_id)->update($updateData);
        $record->delete();
      }, 2);
    } catch (Exception $e) {
      Log::error("押金收款删除失败." . $e);
      throw new Exception("押金收款删除失败");
    }
  }
}
