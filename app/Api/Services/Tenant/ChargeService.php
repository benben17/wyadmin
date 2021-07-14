<?php

namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Bill\Charge;
use App\Api\Models\Bill\ChargeBillRecord;
use App\Api\Models\Bill\ChargeDetail;

class ChargeService
{
  public function model()
  {
    $model = new Charge;
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


  public function detailSave($DA, $user)
  {
    $detail = $this->chargeBillRecord();
    $detail->charge_id  = $DA['charge_id'];
    $detail->amount     = $DA['amount'];
    $detail->type       = $DA['type'];
    $detail->fee_type       = $DA['fee_type'];
    $detail->bill_detail_id    = isset($DA['bill_detail_id']) ? $DA['bill_detail_id'] : 0;
    $detail->remark     = isset($DA['remark']) ? $DA['remark'] : "";
    $detail->c_uid      = $user['id'];
    $detail->c_username = $user['realname'];
    $detail->u_uid      = $user['id'];
    $res = $detail->save();
    return $res;
  }
}
