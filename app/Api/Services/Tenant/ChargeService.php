<?php

namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Bill\ChargeBill;
use App\Api\Models\Bill\ChargeBillRecord;
use App\Api\Models\Bill\TenantBill;
use App\Api\Models\Bill\TenantBillDetail;
use App\Api\Services\Bill\TenantBillService;
use App\Enums\AppEnum;
use Doctrine\DBAL\Query\QueryException;
use Illuminate\Support\Arr;

class ChargeService
{
  public function model()
  {
    return new ChargeBill;
  }
  public function chargeRecord()
  {
    return new ChargeBillRecord;
  }

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
        $charge->unverify_amount = isset($BA['unverify_amount']) ? $BA['unverify_amount'] : 0.00;
        $charge->u_uid  = $user['id'];
      } else {
        $charge         = $this->model();
        $charge->c_uid  = $user['id'];
        $charge->unverify_amount = $BA['amount'];
        $charge->flow_no = getChargeNo($BA['type']);
      }
      $charge->company_id  = $user['company_id'];
      $charge->tenant_id   = $BA['tenant_id'];
      $charge->amount      = $BA['amount'];
      $charge->proj_id     = $BA['proj_id'];
      $charge->type        = $BA['type'];
      $charge->category     = $BA['category'] ?? AppEnum::chargeCategoryFee;
      $charge->source      = $BA['source'];
      $charge->verify_amount =  isset($BA['verify_amount']) ? $BA['verify_amount'] : "0.00";
      if ($BA['type'] == AppEnum::chargeIncome) {
        $charge->unverify_amount = $BA['amount'];
      }
      $charge->tenant_name = isset($BA['tenant_name']) ? $BA['tenant_name'] : "";
      $charge->fee_type    = isset($BA['fee_type']) ? $BA['fee_type'] : 0;
      $charge->bank_id    = isset($BA['bank_id']) ? $BA['bank_id'] : 0;
      $charge->charge_date = $BA['charge_date'];
      $charge->status      = isset($BA['status']) ? $BA['status'] : 0;
      $charge->remark      = isset($BA['remark']) ? $BA['remark'] : "";
      $charge->charge_id      = $BA['charge_id'] ?? 0;
      $chargeRes = $charge->save();
      // });
      return $chargeRes ? $charge : false;
    } catch (Exception $e) {
      Log::error("保存收款失败:" . $e);
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
          $detail_bill_data['receive_amount'] = numFormat($verifyAmt + $detailBill['receive_amount']);
          $detail_bill_data['status'] = 1;
        } else if ($unreceiveAmt > $verifyAmt) {
          $detail_bill_data['receive_amount'] = $detailBill['receive_amount'] + $verifyAmt;
          $detail_bill_data['status'] = 0;
        }
        $charge->unverify_amount = numFormat($chargeBill['unverify_amount'] - $verifyAmt);
        $charge->verify_amount = $chargeBill['verify_amount'] + $verifyAmt;
        if ($chargeBill['unverify_amount'] == 0) {
          $charge->status = AppEnum::chargeVerify;
        }

        $charge->save();
        //更新 收款

        $billRecord['amount'] = $verifyAmt;
        $detail_bill_data['receive_date']   = $verifyDate;
        $billRecord['charge_id']      = $chargeBill['id'];
        $billRecord['bill_detail_id'] = $detailBill['id'];
        $billRecord['type']           = $detailBill['type'];
        $billRecord['fee_type']       = $detailBill['fee_type'];
        $billRecord['proj_id']        = $detailBill['proj_id'];
        $billRecord['verify_date'] = $verifyDate;
        $billService = new TenantBillService;
        $billService->billDetailModel()->where('id', $detailBill)->update($detail_bill_data); // 更新费用信息

        $this->chargeBillRecordSave($billRecord, $user); // 更新核销记录表
      }, 3);
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
  public function detailBillListWriteOff(array $detailBillList, $chargeBill, $verifyDate, $user)
  { {
      $totalVerifyAmt = 0.00;

      try {
        DB::transaction(function () use (&$totalVerifyAmt, $detailBillList, $chargeBill, $verifyDate, $user) {
          foreach ($detailBillList as $detailBill) {
            $verifyAmt = $detailBill['amount'] - $detailBill['receive_amount'] - $detailBill['discount_amount'];
            $totalVerifyAmt += $verifyAmt;

            $detailBillData = [
              'status' => 1,
              'receive_date' => $verifyDate,
              'receive_amount' => $verifyAmt,
            ];

            $billRecord = [
              'amount' => $verifyAmt,
              'charge_id' => $chargeBill['id'],
              'bill_detail_id' => $detailBill['id'],
              'type' => $chargeBill['type'],
              'fee_type' => $detailBill['fee_type'],
              'proj_id' => $detailBill['proj_id'],
              'verify_date' => $verifyDate,
            ];

            $billService = new TenantBillService;
            $billService->billDetailModel()->where('id', $detailBill['id'])->update($detailBillData);

            $this->chargeBillRecordSave($billRecord, $user);
          }

          // 收款记录更新
          $charge = $this->model()->find($chargeBill['id']);
          $charge->unverify_amount = numFormat($chargeBill['unverify_amount'] - $totalVerifyAmt);
          $charge->verify_amount = $chargeBill['verify_amount'] + $totalVerifyAmt;

          if ($chargeBill['unverify_amount'] == 0) {
            $charge->status = AppEnum::chargeVerify;
          }
          $charge->save();
        }, 3);

        return true;
      } catch (\Exception $e) {
        Log::error("核销失败: " . $e->getMessage());
        return false;
      }
    }
  }

  /**
   *  核销一条应收
   * 共计3种情况
   * 收款金额小于 应收 
   * 收款金额等于应收
   * 收款金额 大于应收 
   *
   * @Author leezhua
   * @DateTime 2024-01-29
   * @param array $detailBill
   * @param array $chargeBill
   * @param [type] $verifyDate
   * @param [type] $user
   *
   * @return void
   */


  public function detailBillListWriteOffOne(array $detailBill, array $chargeBill, $verifyDate, $user)
  { {


      try {
        DB::transaction(function () use ($detailBill, $chargeBill, $verifyDate, $user) {
          $verifyAmt = $detailBill['amount'] - $detailBill['receive_amount'] - $detailBill['discount_amount'];

          $detailBillData = [

            'receive_date' => $verifyDate,
          ];
          if ($chargeBill['unverify_amount'] >= $verifyAmt) {
            $detailBillData['status'] = 1;
            $actVerifyAmt = $verifyAmt;
            $chargeBill['unverify_amount'] = numFormat($chargeBill['unverify_amount'] - $actVerifyAmt);
          } else {
            $actVerifyAmt = $chargeBill['unverify_amount'];
            $chargeBill['unverify_amount'] = 0;
          }
          // 充值账单剩余金额


          $billRecord = [
            'amount'      => $actVerifyAmt,
            'charge_id'   => $chargeBill['id'],
            'bill_detail_id' => $detailBill['id'],
            'type'        => $chargeBill['type'],
            'fee_type'    => $detailBill['fee_type'],
            'proj_id'     => $detailBill['proj_id'],
            'verify_date' => $verifyDate,
          ];

          $billService = new TenantBillService;
          $billService->billDetailModel()->where('id', $detailBill['id'])->update($detailBillData);

          $this->chargeBillRecordSave($billRecord, $user);

          $chargeBill['verify_amount'] = $chargeBill['verify_amount'] + $actVerifyAmt;

          if ($chargeBill['unverify_amount'] == 0) {
            $chargeBill['status'] = AppEnum::chargeVerify;
          }
          $this->save($chargeBill, $user);
        }, 3);

        return true;
      } catch (\Exception $e) {
        Log::error("核销失败: " . $e->getMessage());
        return false;
      }
    }
  }



  public function chargeBillRecordSave($DA, $user)
  {
    try {
      $billRecord = $this->chargeRecord();
      $billRecord->flow_no    = getChargeVerifyNo();   // 核销单编号
      $billRecord->charge_id  = $DA['charge_id'];
      // $billRecord->charge_type  = $DA['charge_id'];
      $billRecord->company_id  = $user['company_id'];
      $billRecord->proj_id  = $DA['proj_id'];
      $billRecord->bill_detail_id = isset($DA['bill_detail_id']) ? $DA['bill_detail_id'] : 0;
      $billRecord->amount     = $DA['amount'];
      $billRecord->type       = $DA['type'];
      $billRecord->fee_type   = $DA['fee_type'];
      $billRecord->verify_date = $DA['verify_date'];
      $billRecord->remark     = isset($DA['remark']) ? $DA['remark'] : "";
      $billRecord->c_uid      = $user['id'];
      $billRecord->c_username = $user['realname'];
      $billRecord->save();
    } catch (Exception $th) {
      Log::error("保存冲抵记录失败:" . $th);
      throw $th;
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
      $charge         = $this->model();
      $charge->c_uid  = $user['id'];
      $charge->unverify_amount = 0;
      $charge->flow_no = getChargeNo($BA['type']);
      $charge->company_id  = $user['company_id'];
      $charge->tenant_id   = $billDetail['tenant_id'];
      $charge->amount      = $BA['amount'];
      $charge->proj_id     = $billDetail['proj_id'];
      $charge->type        = 1; // 收入 // 2 支出
      $charge->category     = AppEnum::chargeCategoryDeposit; // 押金转收入;
      $charge->source      = 1; // 费用
      $charge->verify_amount = 0.00;
      $charge->tenant_name = $billDetail['tenant_name'];
      $charge->fee_type    =  0;
      $charge->bank_id    =  $billDetail['bank_id'] ?? 0;
      $charge->charge_date = nowYmd();
      $charge->status      =  0; // 未核销
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
          $charge = $this->model()->findOrFail($record->charge_id);
          $charge->unverify_amount += $record->amount;
          $charge->verify_amount -= $record->amount;
          $charge->save();

          $billDetail = $this->billDetailModel()->findOrFail($record->bill_detail_id);
          $billDetail->receive_amount -= $record->amount;
          $billDetail->updated_at = nowYmd();
          $billDetail->status = 0; // 未结清
          if ($billDetail['bill_id'] != 0) {
            $this->billModel()->where('id', $billDetail['bill_id'])->update(['status' => 0]);
          }
          $billDetail->save();
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

      if ($charge->verify_amount === 0) {
        return $charge->delete();
      }

      DB::transaction(function () use ($charge) {
        $chargeRecords = $this->chargeRecord()->where('charge_id', $charge->id)->get();

        foreach ($chargeRecords as $record) {
          $billDetail = $this->billDetailModel()->findOrFail($record->bill_detail_id);
          $billDetail->receive_amount -= $record->amount;
          $billDetail->charge_date = now()->format('Ymd');
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


  public function chargeRefund($chargeId, $refundAmt,  $user): bool
  {
    try {
      DB::transaction(function () use ($chargeId, $refundAmt,  $user) {
        $charge = $this->model()->findOrFail($chargeId);
        $charge->unverify_amount -= $refundAmt;
        if ($charge->unverify_amount == 0) {
          $charge->status = 1;
        }
        $charge->save();
        $charge->id = 0;
        $charge->charge_id = $chargeId;
        $charge->type = AppEnum::chargeRefund;
        $charge->amount = $refundAmt;
        $charge->status = 1;
        $charge->charge_date = nowYmd();
        $charge->category     = AppEnum::chargeCategoryRefund; // 收入退款;
        $this->save($charge->toArray(), $user);
      }, 2);
      return true;
    } catch (Exception $e) {
      Log::error("收款退款失败" . $e);
    }
    return false;
  }
}
