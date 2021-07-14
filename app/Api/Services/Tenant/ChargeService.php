<?php

namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Bill\ChargeBill;
use App\Api\Models\Bill\ChargeBillRecord;
use App\Api\Models\Bill\ChargeDetail;
use App\Api\Models\Bill\TenantBillDetail;

class ChargeService
{
  public function model()
  {
    $model = new ChargeBill;
    return $model;
  }
  public function chargeBillRecord()
  {
    $model = new ChargeBillRecord;
    return $model;
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
      $charge->type       = $BA['type'];
      $charge->verify_amount =  isset($BA['verify_amount']) ? $BA['verify_amount'] : "0.00";

      $charge->tenant_name = isset($BA['tenant_name']) ? $BA['tenant_name'] : "";
      $charge->fee_type    = isset($BA['fee_type']) ? $BA['fee_type'] : 0;
      $charge->bank_id    = isset($BA['bank_id']) ? $BA['bank_id'] : 0;
      $charge->charge_date = $BA['charge_date'];
      $charge->remark      = isset($BA['remark']) ? $BA['remark'] : "";
      $chargeRes = $charge->save();
      // });
      return $chargeRes;
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
  public  function detailBillVerify(array $detailBill, array $chargeBill, $verifyDate, $user)
  {
    try {
      DB::transaction(function () use ($detailBill,  $chargeBill, $verifyDate, $user) {
        $verifyAmt = $chargeBill['unverify_amount'];
        $unreceiveAmt = $detailBill['amount'] - $detailBill['receive_amount'];
        if ($unreceiveAmt < $verifyAmt) {
          $detailBill['receive_amount'] = $detailBill['amount'];
          $detailBill['status'] = 1;
          // 收入
          $chargeBill['unverify_amount'] = $chargeBill['unverify_amount'] - $unreceiveAmt;
          $chargeBill['verify_amount'] = $chargeBill['verify_amount'] + $unreceiveAmt;
          // 记录
          $billRecord['amount'] = $unreceiveAmt;
        } else if ($unreceiveAmt == $verifyAmt) {
          $detailBill['receive_amount'] = $detailBill['amount'];
          $detailBill['status'] = 1;
          // 收入
          $chargeBill['unverify_amount'] = 0.00;
          $chargeBill['verify_amount'] = $chargeBill['verify_amount'] + $unreceiveAmt;
          $billRecord['amount'] = $unreceiveAmt;
        } elseif ($unreceiveAmt > $verifyAmt) {
          $detailBill['receive_amount'] = $detailBill['receive_amount'] + $verifyAmt;
          $detailBill['status'] = 0;
          $chargeBill['unverify_amount'] = 0.00;
          $chargeBill['verify_amount'] = $chargeBill['verify_amount'] + $verifyAmt;
          $billRecord['amount'] = $verifyAmt;
        }
        $detailBill['receive_date']   = $verifyDate;
        $billRecord['charge_id']      = $chargeBill['id'];
        $billRecord['bill_detail_id'] = $detailBill['id'];
        $billRecord['type']           = $detailBill['type'];
        $billRecord['fee_type']       = $detailBill['fee_type'];
        $billRecord['verify_date'] = $verifyDate;
        $billService = new TenantBillService;
        $billService->saveBillDetail($detailBill, $user);
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

  public function chargeBillRecordSave($DA, $user)
  {
    try {
      $billRecord = $this->chargeBillRecord();
      $billRecord->flow_no    = getFlowNo();
      $billRecord->charge_id  = $DA['charge_id'];
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
