<?php

namespace App\Api\Services\Bill;

use Exception;
use App\Enums\AppEnum;
use App\Enums\ChargeEnum;
use Illuminate\Support\Facades\DB;
use App\Api\Models\Bill\ChargeBill;
use App\Api\Models\Bill\TenantBill;
use Illuminate\Support\Facades\Log;
use Doctrine\DBAL\Query\QueryException;
use App\Api\Models\Bill\ChargeBillRecord;
use App\Api\Models\Bill\TenantBillDetail;
use App\Api\Services\Bill\TenantBillService;

class ChargeService
{
  // 收款模型
  public function model()
  {
    return new ChargeBill;
  }
  // 收款核销记录模型
  public function chargeRecord()
  {
    return new ChargeBillRecord;
  }
  // 获取收款编号
  function getChargeNo($type)
  {
    $no = date('ymdHis', strtotime(nowTime()));
    if ($type == ChargeEnum::Income) {
      return 'IE-' . $no . mt_rand(10, 99); // 收入
    } else {
      return 'EX-' . $no . mt_rand(10, 99); // 支出
    }
  }
  // 租户应收明细
  public function billDetailModel()
  {
    return new TenantBillDetail;
  }

  // 租户账单
  public function billModel()
  {
    return new TenantBill;
  }
  public function save($BA, $user)
  {
    try {
      // DB::transaction(function () use ($user, $BA) {
      if (isset($BA['id']) && $BA['id'] > 0) {
        $charge         = $this->model()->where('id', $BA['id'])->first();
        $charge->u_uid  = $user['id'];
      } else {
        $charge         = $this->model();
        $charge->c_uid  = $user['id'];
        $charge->flow_no = $this->getChargeNo($BA['type']);
      }
      $charge->pay_person  = $BA['pay_person'] ?? "";
      $charge->company_id  = $user['company_id'];
      $charge->tenant_id   = $BA['tenant_id'];
      $charge->amount      = $BA['amount'];
      $charge->proj_id     = $BA['proj_id'];
      $charge->type        = $BA['type'];
      $charge->category    = $BA['category'] ?? ChargeEnum::CategoryFee; // 费用
      $charge->source      = $BA['source'];
      $charge->pay_method  = $BA['pay_method'] ?? 0;
      $charge->verify_amount =  isset($BA['verify_amount']) ? $BA['verify_amount'] : "0.00";
      $charge->tenant_name = isset($BA['tenant_name']) ? $BA['tenant_name'] : "";
      $charge->fee_type    = isset($BA['fee_type']) ? $BA['fee_type'] : 0;
      $charge->bank_id     = isset($BA['bank_id']) ? $BA['bank_id'] : 0;
      $charge->charge_date = $BA['charge_date'];
      $charge->status      = isset($BA['status']) ? $BA['status'] : 0;
      $charge->remark      = isset($BA['remark']) ? $BA['remark'] : "";
      $charge->charge_id   = $BA['charge_id'] ?? 0;
      $chargeRes = $charge->save();
      // });
      return $chargeRes ? $charge : false;
    } catch (Exception $e) {
      Log::error("保存收款失败:" . $e);
      throw new Exception("保存收款/退款失败");
      return false;
    }
  }


  /**
   * 账单核销
   *
   * @Author leezhua
   * @DateTime 2021-07-14
   * @param [type] $detailBill
   * @param [type] $chargeBill
   * @param [type] $verifyDate
   *
   * @return void
   */
  public function detailBillVerify(array $detailBill, array $chargeBill, $verifyAmt, $verifyDate, $user)
  {

    try {
      DB::transaction(function () use ($detailBill,  $chargeBill, $verifyAmt, $verifyDate, $user) {
        $charge = $this->model()->find($chargeBill['id']);
        $unreceiveAmt = $detailBill['amount'] - $detailBill['receive_amount'] - $detailBill['discount_amount'];
        if ($unreceiveAmt == $verifyAmt) {
          $detail_bill_data['status'] = ChargeEnum::chargeVerify;
        } else if ($unreceiveAmt > $verifyAmt) {
          $detail_bill_data['status'] = ChargeEnum::chargeUnVerify;
        }
        $detail_bill_data['receive_amount'] = bcadd($detailBill['receive_amount'], $verifyAmt, 2);
        $charge->verify_amount   = bcadd($charge->verify_amount, $verifyAmt, 2);

        // 未核销金额 = 充值未核销金额 - 核销金额 - 退款金额
        $availableAmt = bcsub(bcsub($charge->amount, $charge->verify_amount, 2), $charge->refund_amount, 2);
        if ($availableAmt == 0 || $availableAmt == 0.00) {
          $charge->status = ChargeEnum::chargeVerify;
        }

        $charge->save();
        //更新 收款

        $billRecord['amount']             = $verifyAmt;
        $detail_bill_data['receive_date'] = $verifyDate;
        $billRecord['charge_id']          = $chargeBill['id'];
        $billRecord['bill_detail_id']     = $detailBill['id'];
        $billRecord['type']               = $detailBill['type'];
        $billRecord['fee_type']           = $detailBill['fee_type'];
        $billRecord['proj_id']            = $detailBill['proj_id'];
        $billRecord['verify_date']        = $verifyDate;
        $billService = new TenantBillService;
        $billService->billDetailModel()->where('id', $detailBill)->update($detail_bill_data); // 更新费用信息

        $this->chargeBillRecordSave($billRecord, $user); // 更新核销记录表
      }, 2);
      return true;
    } catch (Exception $th) {
      Log::error("核销失败" . $th);
      throw $th;
      return false;
    }
  }


  /**
   * 在充值记录中进行 应收核销 ，可以核销多个应收款项
   *
   * @Author leezhua
   * @DateTime 2024-01-13
   * @param array $detailBillList
   * @param array $chargeBill
   * @param [type] $verifyDate
   * @param [type] $user
   *
   * @return void
   */
  public function detailBillListWriteOff(array $detailBillList, $chargeId, $verifyDate, $user)
  {
    try {
      DB::transaction(function () use ($detailBillList, $chargeId, $verifyDate, $user) {
        $billService = new TenantBillService;
        $charge = $this->model()->findOrFail($chargeId);

        $availableAmt = bcsub($charge->amount, $charge->refund_amount, 2);  //  充值未核销金额
        foreach ($detailBillList as $detailBill) {
          $verifyAmt = 0;
          if ($availableAmt == 0 || $charge->status == ChargeEnum::chargeVerify) { // 充值金额已经核销完毕
            break; // 跳出循环
          }
          $unreceiveAmt = bcsub(bcsub($detailBill['amount'], $detailBill['receive_amount'], 2), $detailBill['discount_amount'], 2);
          if ($unreceiveAmt <= $availableAmt) { // 应收金额小于等于充值金额
            $availableAmt = bcsub($availableAmt, $unreceiveAmt, 2);  // 充值金额减去应收金额
            $verifyAmt = $unreceiveAmt;  // 核销金额
            $feeStatus = AppEnum::feeStatusReceived;           // 应收状态
          } else { // 应收金额大于充值金额
            $verifyAmt = $availableAmt;   // 核销金额
            $availableAmt = 0;         // 充值金额为0
            $feeStatus = AppEnum::feeStatusUnReceive; // 应收 未结清
          }
          if ($availableAmt == 0 || $availableAmt === 0.00) {
            $charge->status = ChargeEnum::chargeVerify;
          }

          // 更新应收费用
          $detailBillData = [
            'status'         => $feeStatus,
            'receive_date'   => $verifyDate,
            'receive_amount' => bcadd($verifyAmt, $detailBill['receive_amount'], 2),
          ];
          // 核销记录
          $billRecord = [
            'amount'         => $verifyAmt,
            'charge_id'      => $charge->id,
            'bill_detail_id' => $detailBill['id'],
            'type'           => $charge->type,
            'fee_type'       => $detailBill['fee_type'],
            'proj_id'        => $detailBill['proj_id'],
            'verify_date'    => $verifyDate,
          ];
          // 更新应收费用

          $billService->billDetailModel()->where('id', $detailBill['id'])->update($detailBillData);
          $this->chargeBillRecordSave($billRecord, $user);
          // 更新充值信息
          $charge->verify_amount   = bcadd($charge->verify_amount, $verifyAmt, 2);
        }
        $charge->save();
      }, 2);
      return true;
    } catch (\Exception $e) {
      Log::error("核销失败: " . $e->getMessage());
      return false;
    }
  }

  // /**
  //  *  核销一条应收
  //  * 共计3种情况
  //  * 收款金额小于 应收 
  //  * 收款金额等于应收
  //  * 收款金额 大于应收 
  //  *
  //  * @Author leezhua
  //  * @DateTime 2024-01-29
  //  * @param array $detailBill
  //  * @param array $chargeBill
  //  * @param [type] $verifyDate
  //  * @param [type] $user
  //  *
  //  * @return void
  //  */

  // public function detailBillListWriteOffOne(array $detailBill, array $chargeBill, $verifyDate, $user)
  // {


  //   try {
  //     DB::transaction(function () use ($detailBill, $chargeBill, $verifyDate, $user) {
  //       $verifyAmt = $detailBill['amount'] - $detailBill['receive_amount'] - $detailBill['discount_amount'];

  //       $detailBillData = [

  //         'receive_date' => $verifyDate,
  //       ];
  //       if ($chargeBill['unverify_amount'] >= $verifyAmt) {
  //         $detailBillData['status'] = 1;
  //         $actVerifyAmt = $verifyAmt;
  //         $chargeBill['unverify_amount'] = numFormat($chargeBill['unverify_amount'] - $actVerifyAmt);
  //       } else {
  //         $actVerifyAmt = $chargeBill['unverify_amount'];
  //         $chargeBill['unverify_amount'] = 0;
  //       }
  //       // 充值账单剩余金额


  //       $billRecord = [
  //         'amount'      => $actVerifyAmt,
  //         'charge_id'   => $chargeBill['id'],
  //         'bill_detail_id' => $detailBill['id'],
  //         'type'        => $chargeBill['type'],
  //         'fee_type'    => $detailBill['fee_type'],
  //         'proj_id'     => $detailBill['proj_id'],
  //         'verify_date' => $verifyDate,
  //       ];

  //       $billService = new TenantBillService;
  //       $billService->billDetailModel()->where('id', $detailBill['id'])->update($detailBillData);

  //       $this->chargeBillRecordSave($billRecord, $user);

  //       $chargeBill['verify_amount'] = $chargeBill['verify_amount'] + $actVerifyAmt;

  //       if ($chargeBill['unverify_amount'] == 0) {
  //         $chargeBill['status'] = ChargeEnum::chargeVerify;
  //       }
  //       $this->save($chargeBill, $user);
  //     }, 3);

  //     return true;
  //   } catch (\Exception $e) {
  //     Log::error("核销失败: " . $e->getMessage());
  //     return false;
  //   }
  // }


  /**
   * 保存核销记录
   *
   * @Author leezhua
   * @DateTime 2024-01-29
   * @param array $DA
   * @param array $user
   *
   * @return void
   */
  public function chargeBillRecordSave($DA, $user)
  {
    try {
      $billDetail = $this->billDetailModel()->findOrFail($DA['bill_detail_id']);
      $billRecord = $this->chargeRecord();
      $billRecord->flow_no    = getChargeVerifyNo();   // 核销单编号
      $billRecord->charge_id  = $DA['charge_id'];
      // $billRecord->charge_type  = $DA['charge_id'];
      $billRecord->company_id     = $user['company_id'];
      $billRecord->proj_id        = $DA['proj_id'];
      $billRecord->bill_detail_id = $billDetail->id;
      $billRecord->tenant_id      = $billDetail->tenant_id;
      $billRecord->amount         = $DA['amount'];
      $billRecord->type           = $DA['type'];
      $billRecord->fee_type       = $DA['fee_type'];
      $billRecord->verify_date    = $DA['verify_date'];
      $billRecord->remark         = isset($DA['remark']) ? $DA['remark'] : "";
      $billRecord->c_uid          = $user['id'];
      $billRecord->c_username     = $user['realname'];
      $billRecord->save();
    } catch (Exception $th) {
      Log::error("保存冲抵记录失败:" . $th);
      throw new Exception("保存核销记录失败");
    }
  }


  /**
   * 押金转充值
   *
   * @Author leezhua
   * @DateTime 2024-03-06
   * @param object $billDetail
   * @param [object] $BA
   * @param [string] $remark
   * @param array $user
   *
   * @return boolean
   */
  public function depositToCharge($billDetail, $BA, $user): bool
  {
    try {
      $charge              = $this->model();
      $charge->c_uid       = $user['id'];
      $charge->flow_no     = $this->getChargeNo($BA['type']);
      $charge->company_id  = $user['company_id'];
      $charge->tenant_id   = $billDetail['tenant_id'];
      $charge->tenant_name = $billDetail['tenant_name'];
      $charge->amount      = $BA['amount'];
      $charge->proj_id     = $billDetail['proj_id'];
      $charge->type        = ChargeEnum::Income;               // 收入 // 2 支出
      $charge->category    = ChargeEnum::CategoryDeposit;      // 押金转收入;
      $charge->source      = 1;                                // 费用
      $charge->verify_amount = 0.00;
      $charge->unverify_amount = 0.00;

      // $charge->fee_type    =  0;
      $charge->bank_id     =  $billDetail['bank_id'] ?? 0;
      $charge->charge_date = $BA['charge_date'] ?? nowYmd();
      $charge->status      =  ChargeEnum::chargeUnVerify;
      $charge->remark      =  $BA['remark'];
      $chargeRes = $charge->save();
      return $chargeRes;
    } catch (Exception $e) {
      Log::error("押金转收入失败，信息如下:" . $e);
      throw $e;
    }
  }


  /**
   * 删除收款核销记录
   *
   * @Author leezhua
   * @DateTime 2024-03-14
   * @param integer $recordId
   *
   * @return boolean
   */
  public function deleteChargeRecord(int $recordId): bool
  {
    try {
      DB::transaction(function () use ($recordId) {
        $record = $this->chargeRecord()->findOrFail($recordId);
        if ($record->type == 1) {
          // 更新收款信息
          $charge = $this->model()->findOrFail($record->charge_id);
          $charge->verify_amount = bcsub($charge->verify_amount, $record->amount, 2);
          $charge->status = ChargeEnum::chargeUnVerify;
          $charge->save();

          // 更新应收费用收款信息
          $billDetail = $this->billDetailModel()->findOrFail($record->bill_detail_id);
          if ($billDetail->receive_amount < $record->amount) {
            throw new Exception("核销金额大于应收金额");
          }
          $billDetail->receive_amount = bcsub($billDetail->receive_amount, $record->amount, 2);
          $billDetail->updated_at = nowYmd();
          $billDetail->status = AppEnum::feeStatusUnReceive; // 未结清
          $billDetail->save();
          // 删除核销记录
          $record->delete();
        }
      }, 2);
      return true;
    } catch (QueryException $e) {
      Log::error("数据库操作失败:" . $e->getMessage());
    } catch (Exception $e) {
      Log::error("删除核销记录失败:" . $e->getMessage());
    }
    return false;
  }


  /**
   * 删除收款
   *
   * @Author leezhua
   * @DateTime 2024-03-14
   * @param integer $recordId
   *
   * @return boolean
   */
  public function deleteCharge(int $chargeId): bool
  {
    try {
      $charge = $this->model()->findOrFail($chargeId);

      if ($charge->verify_amount == 0) {
        return $charge->delete();
      }

      DB::transaction(function () use ($charge) {
        $chargeRecords = $this->chargeRecord()->where('charge_id', $charge->id)->get();

        foreach ($chargeRecords as $record) {
          $billDetail = $this->billDetailModel()->findOrFail($record->bill_detail_id);
          $billDetail->receive_amount -= $record->amount;
          $billDetail->charge_date = nowYmd();
          $billDetail->save();
        }

        $this->chargeRecord()->where('charge_id', $charge->id)->delete(); // 删除核销记录
        $charge->delete();  //删除充值记录
      }, 2);

      return true;
    } catch (QueryException $e) {
      Log::error("数据库操作失败:" . $e->getMessage());
    } catch (Exception $e) {
      Log::error("删除收款失败:" . $e->getMessage());
    }
    return false;
  }


  /**
   * 收款退款
   *
   * @Author leezhua
   * @DateTime 2024-03-14
   * @param integer $chargeId
   * @param float $refundAmt
   * @param array $user
   *
   * @return boolean
   */
  public function chargeRefund($DA,  $user): bool
  {
    try {
      DB::transaction(function () use ($DA,  $user) {
        $charge = $this->model()->findOrFail($DA['id']);
        $availableAmt = bcsub($charge->amount, $charge->verify_amount, 2);
        $availableAmt = bcsub(bcsub($availableAmt, $charge->refund_amount, 2), $DA['refund_amt'], 2);
        $charge->refund_amount = bcadd($charge->refund_amount, $DA['refund_amt'], 2);
        if ($availableAmt == 0 || $availableAmt == 0.00) {
          $charge->status = ChargeEnum::chargeVerify;
        }
        $charge->save();
        $refund = [
          'id'          => 0,
          'source'      => 1, // 1 费用
          'proj_id'     => $charge['proj_id'],
          'pay_method'  => $charge['pay_method'],
          'charge_id'   => $DA['id'],
          'tenant_id'   => $charge['tenant_id'],
          'type'        => ChargeEnum::Refund,
          'amount'      => $DA['refund_amt'],
          'status'      => ChargeEnum::chargeVerify,
          'charge_date' => $DA['common_date'] ?? nowYmd(),
          'category'    => ChargeEnum::CategoryRefund,   // 收入退款;
          'bank_id'     => $charge['bank_id'],
          'remark'      => $DA['remark']
        ];
        $this->save($refund, $user);
      }, 2);
      return true;
    } catch (Exception $e) {
      Log::error("退款失败" . $e);
      throw new Exception("退款失败");
    }
    return false;
  }

  /**
   * 收款列表表头统计
   * @Author leezhua
   * @Date 2024-04-12
   * @param mixed $query 
   * @return array 
   */
  public function listStat($query, &$data)
  {
    $statQuery = clone $query;
    $statSelect = 'count(id) count,ifnull(sum(amount),0.00) as amount, 
                  ifnull(sum(verify_amount),0.00) as verify_amount,
                  ifnull(sum(refund_amount),0.00) as refund_amount';
    $statData = $statQuery->selectRaw($statSelect)->first();
    $total['total_amt'] = $statData['amount'] ?? 0.00;
    $total['verify_amt'] = $statData['verify_amount'] ?? 0.00;
    $total['refund_amt'] = $statData['refund_amount'] ?? 0.00;
    $total['available_amt'] = bcsub(bcsub($total['total_amt'], $total['refund_amt'], 2), $total['verify_amt'], 2);

    $data['total'] = num_format($total);
  }
}
