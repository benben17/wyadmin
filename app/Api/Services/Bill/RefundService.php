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
        $charge['type']         = AppEnum::chargeRefund;
        $charge['amount']       = $request->amount;
        $charge['charge_date']  = $request->refund_date;
        $charge['bank_id']      = $request->bank_id;
        $charge['proj_id']      = $billDetail->proj_id;
        $charge['fee_type']     = $billDetail->fee_type;
        $charge['tenant_id']    = $billDetail->tenant_id;
        $charge['tenant_name']  = $billDetail->tenant_name;
        $charge['remark']       = isset($request->remark) ? $request->remark : "";
        $chargeRes = $chargeService->save($charge, $user);

        // 更新费用状态
        if ($request->amount == $billDetail['receive_amount']) {
          $billData['status'] = 2;
        } else if ($request->amount < $billDetail['receive_amount']) {
          $billData['status'] = 3; //  部分退款
        }
        $billService = new TenantBillService;
        $billService->billDetailModel()->where('id', $billDetail['id'])->update($billData);

        $refundService = new RefundService;
        // Log::error($chargeRes);
        // 写入退款记录
        $DA = $request->toArray();
        $DA['charge_id'] = $chargeRes->id;
        $DA['proj_id'] = $billDetail->proj_id;
        // Log::error(json_encode($DA));
        $refundService->save($DA, $user);
      }, 3);
      return true;
    } catch (Exception $th) {
      Log::error("退款失败." . $th);
      return false;
    }
  }
}
