<?php

namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Bill\ChargeBill;
use App\Api\Models\Bill\ChargeBillRecord;
use App\Api\Services\Bill\TenantBillService;
use App\Enums\AppEnum;

class ChargeService
{
  public function model()
  {
    return new ChargeBill;
  }
  public function chargeBillRecord()
  {
    return new ChargeBillRecord;
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
        $charge->flow_no = getFlowNo();
      }
      $charge->company_id  = $user['company_id'];
      $charge->tenant_id   = $BA['tenant_id'];
      $charge->amount      = $BA['amount'];
      $charge->proj_id     = $BA['proj_id'];
      $charge->type        = $BA['type'];
      $charge->verify_amount =  isset($BA['verify_amount']) ? $BA['verify_amount'] : "0.00";

      $charge->tenant_name = isset($BA['tenant_name']) ? $BA['tenant_name'] : "";
      $charge->fee_type    = isset($BA['fee_type']) ? $BA['fee_type'] : 0;
      $charge->bank_id    = isset($BA['bank_id']) ? $BA['bank_id'] : 0;
      $charge->charge_date = $BA['charge_date'];
      $charge->status      = isset($BA['status']) ? $BA['status'] : 0;
      $charge->remark      = isset($BA['remark']) ? $BA['remark'] : "";
      $chargeRes = $charge->save();
      // });
      if ($chargeRes) {
        return $charge;
      } else {
        return false;
      }
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

        // $verifyAmt = $verifyAmount;
        $unreceiveAmt = $detailBill['amount'] - $detailBill['receive_amount'] - $detailBill['discount_amount'];
        if ($unreceiveAmt == $verifyAmt) {
          $detail_bill_data['receive_amount'] = numFormat($verifyAmt + $detailBill['receive_amount']);
          $detail_bill_data['status'] = 1;
        } elseif ($unreceiveAmt > $verifyAmt) {
          $detail_bill_data['receive_amount'] = $detailBill['receive_amount'] + $verifyAmt;
          $detail_bill_data['status'] = 0;
        }
        $chargeBill['unverify_amount'] = numFormat($chargeBill['unverify_amount'] - $verifyAmt);
        $chargeBill['verify_amount'] = $chargeBill['verify_amount'] + $verifyAmt;
        if ($chargeBill['unverify_amount'] == 0) {
          $chargeBill['status'] = AppEnum::chargeVerify;
        }
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
        $this->save($chargeBill, $user);  //更新 收款
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
            ];

            $billRecord = [
              'amount' => $verifyAmt,
              'charge_id' => $chargeBill['id'],
              'bill_detail_id' => $detailBill['id'],
              'type' => $detailBill['type'],
              'fee_type' => $detailBill['fee_type'],
              'proj_id' => $detailBill['proj_id'],
              'verify_date' => $verifyDate,
            ];

            $billService = new TenantBillService;
            $billService->billDetailModel()->where('id', $detailBill['id'])->update($detailBillData);

            $this->chargeBillRecordSave($billRecord, $user);
          }

          $chargeBill['unverify_amount'] = numFormat($chargeBill['unverify_amount'] - $totalVerifyAmt);
          $chargeBill['verify_amount'] = $chargeBill['verify_amount'] + $totalVerifyAmt;

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
            'amount' => $actVerifyAmt,
            'charge_id' => $chargeBill['id'],
            'bill_detail_id' => $detailBill['id'],
            'type' => $detailBill['type'],
            'fee_type' => $detailBill['fee_type'],
            'proj_id' => $detailBill['proj_id'],
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
      $billRecord = $this->chargeBillRecord();
      $billRecord->flow_no    = getFlowNo();
      $billRecord->charge_id  = $DA['charge_id'];
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
      throw $th;
      Log::error("保存冲抵记录失败:" . $th);
    }
  }
}
