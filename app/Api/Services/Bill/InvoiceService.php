<?php

namespace App\Api\Services\Bill;

use App\Api\Models\Bill\InvoiceRecord;
use App\Api\Models\Tenant\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

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
        $invoiceRecord->company_id   = $user['company_id'];
        $invoiceRecord->c_uid        = $user['id'];
      }
      $invoiceRecord->proj_id        = $DA['proj_id'];
      $invoiceRecord->tenant_id      = $DA['tenant_id'];
      $invoiceRecord->amount         = $DA['amount'];
      $invoiceRecord->invoice_no     = isset($DA['invoice_no']) ? $DA['invoice_no'] : "";
      $invoiceRecord->bill_detail_id = $DA['bill_detail_id'];
      $invoiceRecord->tax_rate       = $DA['tax_rate'];
      $invoiceRecord->invoice_type   = isset($DA['invoice_type']) ? $DA['invoice_type'] : "";
      if (isset($DA['invoice_date'])) {
        $invoiceRecord->invoice_date    = $DA['invoice_date'];
      }
      $invoiceRecord->open_person  = isset($DA['open_person']) ? $DA['open_person'] : "";
      // $invoiceRecord->status       = $DA['status'];
      $invoiceRecord->title        = $DA['title'];
      $invoiceRecord->bank_name    = $DA['bank_name'];
      $invoiceRecord->account_name = $DA['account_name'];
      $invoiceRecord->tax_number   = $DA['tax_number'];
      $invoiceRecord->addr         = $DA['addr'];
      $invoiceRecord->tel_number   = $DA['tel_number'];
      $invoiceRecord->save();
      return $invoiceRecord;
    } catch (Exception $e) {
      Log::error('保存发票失败' . $e);
      throw new Exception("发票保存失败" . $e);
      return false;
    }
  }

  /**
   * 发票作废
   *
   * @Author leezhua
   * @DateTime 2021-07-18
   * @param [type] $recordId
   *
   * @return void
   */
  public function cancelInvoice($recordId)
  {
    try {
      DB::transaction(function () use ($recordId) {
        $record = $this->invoiceRecordModel()->find($recordId);
        $record->status = 3;
        $billService = new TenantBillService;
        $billService->billDetailModel()->whereIn('id', str2Array($record['bill_detail_id']))
          ->update('invoice_id', 0);
        $record->save();
      });
      return true;
    } catch (Exception $th) {
      Log::error('发票作废失败.' . $th);
      return false;
    }
  }
}
