<?php

namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Bill\ChargeBill;
use App\Api\Models\Bill\ChargeBillRecord;
use App\Api\Models\Bill\ChargeDetail;

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
        $charge         = $this->model()->where('audit_status', '!=', 2)->where('id', $BA['id'])->first();
        if (!$charge) {
          return false;
        }
        $charge->u_uid  = $user['id'];
      } else {
        $charge         = $this->model();
        $charge->c_uid  = $user['id'];
      }
      $charge->company_id  = $user['company_id'];
      $charge->tenant_id   = $BA['tenant_id'];
      $charge->amount      = $BA['amount'];
      $charge->proj_id     = $BA['proj_id'];
      $charge->type       = $BA['type'];
      $charge->tenant_name = isset($BA['tenant_name']) ? $BA['tenant_name'] : "";
      $charge->fee_type    = isset($BA['fee_type']) ? $BA['fee_type'] : 1;
      $charge->charge_date = $BA['charge_date'];
      $charge->remark      = isset($BA['remark']) ? $BA['remark'] : "";
      $chargeRes = $charge->save();
      // });
      return $chargeRes;
    } catch (Exception $e) {
      Log::error($e->getMessage());
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
  public  function detailBillVerify($detailBill, array $chargeBill, $verifyDate, $user)
  {
    $verifyAmt = $chargeBill['unverify_amount'];
    $unreceiveAmt = $detailBill['amount'] - $detailBill['receive_amount'];
    if ($unreceiveAmt > $verifyAmt) {
      $detailBill['receive_amount'] = $detailBill['amount'];
      $detailBill['receive_date'] = $verifyDate;
      $detailBill['status'] = 1;
      $chargeBill['unverify_amount'] = $chargeBill['unverify_amount'] - $unreceiveAmt;
      $billRecord['amount'] = $unreceiveAmt;
    } else if ($unreceiveAmt == $verifyAmt) {
    }

    $billRecord['charge_id']      = $chargeBill['id'];
    $billRecord['bill_detail_id'] = $detailBill['id'];
    $billRecord['type']           = $detailBill['type'];
    $billRecord['fee_type']       = $detailBill['fee_type'];
    $this->model()->save($chargeBill);  //更新 收款
    $this->chargeBillRecordSave($billRecord, $user); // 更新核销记录表
  }

  public function chargeBillRecordSave($DA, $user)
  {
    try {
      $billRecord = $this->chargeBillRecord();
      $billRecord->charge_id  = $DA['charge_id'];
      $billRecord->bill_detail_id = isset($DA['bill_detail_id']) ? $DA['bill_detail_id'] : 0;
      $billRecord->amount     = $DA['amount'];
      $billRecord->type       = $DA['type'];
      $billRecord->fee_type   = $DA['fee_type'];
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
