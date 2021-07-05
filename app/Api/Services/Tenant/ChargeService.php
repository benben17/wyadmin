<?php

namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Tenant\Charge;
use App\Api\Models\Tenant\ChargeDetail;

class ChargeService
{
  public function model()
  {
    $model = new Charge;
    return $model;
  }
  public function detailModel()
  {
    $model = new ChargeDetail();
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
      $charge->tenant_name = isset($BA['tenant_name']) ? $BA['tenant_name'] : "";
      $charge->charge_type = isset($BA['charge_type']) ? $BA['charge_type'] : 1;
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


  public function detailSave($DA, $user)
  {
    $detail = $this->detailModel();
    $detail->charge_id  = $DA['charge_id'];
    $detail->amount     = $DA['amount'];
    $detail->charge_type = $DA['charge_type'];
    $detail->bill_id    = isset($DA['bill_id']) ? $DA['bill_id'] : 0;
    $detail->bill_name  = isset($DA['bill_name']) ? $DA['bill_name'] : "";
    $detail->remark     = isset($DA['remark']) ? $DA['remark'] : "";
    $detail->c_uid      = $user['id'];
    $detail->u_uid      = $user['id'];
    $res = $detail->save();
    return $res;
  }

  /**
   * 充值审核
   *
   * @param [int] $chargeId
   * @param [Array] $user
   * @param [int] $auditStatus
   * @return void
   */
  public function audit($chargeId, $user, $auditStatus)
  {
    $charge = $this->model()->find($chargeId);
    try {
      DB::transaction(function () use ($user, $charge, $auditStatus) {
        $charge->audit_uid = $user['id'];
        $charge->audit_user = $user['realname'];
        $charge->audit_status = $auditStatus;
        $charge->save();
        if ($auditStatus == 2) {
          $detail['charge_id']    = $charge->id;
          $detail['charge_type']  = 1;
          $detail['amount']       = $charge->amount;
          $detail['remark']       = "预充审核通过";
          $this->detailSave($detail, $user);
        }
      });
      return true;
    } catch (Exception $e) {
      Log::error($e);
      return false;
    }
  }
}
