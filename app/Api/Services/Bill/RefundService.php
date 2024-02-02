<?php

namespace App\Api\Services\Bill;

use App\Api\Models\Bill\RefundRecord;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Tenant\ChargeService;
use App\Enums\AppEnum;

class RefundService
{
  public function model()
  {
    return new RefundRecord;
  }

  public function save($DA, $user)
  {
    try {
      DB::transaction(function () use ($DA, $user) {
        $refund = $this->model();
        $refund->company_id = $user['company_id'];
        $refund->proj_id = $DA['proj_id'];
        $refund->bill_detail_id = $DA['bill_detail_id'];
        $refund->amount = $DA['amount'];
        $refund->charge_id = $DA['charge_id'];
        $refund->refund_date = $DA['refund_date'];
        $refund->bank_id = isset($DA['bank_id']) ? $DA['bank_id'] : 0;
        $refund->remark = isset($DA['remark']) ? $DA['remark'] : "";
        $refund->c_user = $user['realname'];
        $refund->c_uid = $user['id'];
        $refund->save();
      }, 2);
      return true;
    } catch (Exception $e) {
      Log::error("退款记录保存失败." . $e);
      throw new Exception($e);
    }
  }

  /**
   * 退款
   *
   * @Author leezhua
   * @DateTime 2024-01-31
   *
   * @return void
   */
  public function refund($billDetail, $request, $user)
  {
    try {
      DB::transaction(function () use ($billDetail, $request, $user) {
        $chargeService = new ChargeService;
        $charge = $chargeService->model();
        $charge->type     = AppEnum::chargeRefund;
        $charge->amount   = $request->amount;
        $charge->charge_date = $request->refund_date;
        $charge->bank_id  = $request->bank_id;
        $charge->proj_id  = $billDetail->proj_id;
        $charge->fee_type = $billDetail->fee_type;
        $charge->tenant_id = $billDetail->tenant_id;
        $charge->tenant_name = $billDetail->tenant_name;
        $charge->remark   = $request->filled('remark') ? $request->remark : "";
        $charge->flow_no  = "RF-" . date('ymdHis') . mt_rand(10, 99);;
        $charge->source   = 2;
        $charge->c_uid    = $user->id; // Assuming $user is an Eloquent model instance
        $charge->company_id    = $user->company_id; //
        $charge->save();

        // Update the bill status based on the refund amount
        // $billData = ['status' => 1]; // Assuming the default status is 1
        if ($request->amount == $billDetail->receive_amount) {
          $billData['status'] = 2;
        } elseif ($request->amount < $billDetail->receive_amount) {
          $billData['status'] = 3; // Partial refund
        }

        $billService = new TenantBillService;
        $billService->billDetailModel()->where('id', $billDetail->id)->update($billData);

        // Create a refund record
        $refundService = new RefundService;
        $refundData = $request->all();
        $refundData['charge_id'] = $charge->id;
        $refundData['proj_id'] = $billDetail->proj_id;
        $refundService->save($refundData, $user);
      }, 3);
      return true;
    } catch (Exception $th) {
      Log::error("退款失败." . $th);
      return false;
    }
  }
}
