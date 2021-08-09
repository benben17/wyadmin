<?php

namespace App\Api\Services\Bill;

use App\Api\Models\Bill\RefundRecord;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


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
}
