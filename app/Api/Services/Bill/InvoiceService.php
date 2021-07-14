<?php

namespace App\Api\Services\Bill;

use App\Api\Models\Bill\InvoiceRecord;
use App\Api\Models\Tenant\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 *   发票服务
 */
class InvoiceService
{
  public function invoiceModel()
  {
    return new Invoice;
  }

  public function invoiceRecordModel()
  {
    return new InvoiceRecord;
  }

  public function invoiceRecordSave($DA, $user)
  {
    try {
      if (isset($DA['id']) && $DA['id'] > 0) {
        $invoiceRecord = $this->invoiceRecordModel()->find($DA['id']);
        $invoiceRecord->u_uid = $user['id'];
      } else {
        $invoiceRecord = $this->invoiceRecordModel();
        $invoiceRecord->company_id = $user['company_id'];
        $invoiceRecord->c_uid = $user['id'];
      }
      $invoiceRecord->proj_id        = $DA['proj_id'];
      $invoiceRecord->amount         = $DA['amount'];
      $invoiceRecord->invoice_no     = isset($DA['invoice_no']) ? $DA['invoice_no'] : "";
      $invoiceRecord->bill_detail_id = $DA['bill_detail_id'];
      $invoiceRecord->tax_rate       = $DA['tax_rate'];
      $invoiceRecord->invoice_type   = isset($DA['invoice_type']) ? $DA['invoice_type'] : "";
      $invoiceRecord->status         = $DA['status'];
      $invoiceRecord->save();
      return $invoiceRecord;
    } catch (Exception $e) {
      Log::error('保存发票失败' . $e);
      throw new Exception("发票保存失败" . $e);
      return false;
    }
  }
}
