<?php

namespace App\Api\Models\Bill;

use App\Api\Models\Company\FeeType;
use App\Api\Models\Contract\Contract;
use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 *  账单详细
 */
class TenantBillDetail extends Model
{
  use SoftDeletes;
  protected $table = 'bse_tenant_bill_detail';
  protected $fillable = [];
  protected $hidden = ['deleted_at', 'updated_at', 'company_id', 'u_uid'];

  protected $appends = ['fee_type_label', 'c_user', 'proj_name', 'status_label', 'unreceive_amount', 'receivable_amount', 'bill_status'];

  public function getFeeTypeLabelAttribute()
  {
    if (isset($this->attributes['fee_type'])) {
      $fee = getFeeNameById($this->attributes['fee_type']);
      return $fee['fee_name'];
    }
  }
  public function getCUserAttribute()
  {
    if (isset($this->attributes['c_uid'])) {
      $user = getUserByUid($this->attributes['c_uid']);
      return $user['realname'];
    }
  }
  // 未收金额
  public function getUnreceiveAmountAttribute()
  {
    if (isset($this->attributes['amount']) && isset($this->attributes['receive_amount']) && isset($this->attributes['discount_amount'])) {
      return numFormat($this->attributes['amount'] - $this->attributes['receive_amount'] - $this->attributes['discount_amount']);
    } else {
      return 0.00;
    }
  }

  // 实际应收
  public function getReceivableAmountAttribute()
  {
    if (isset($this->attributes['amount']) && isset($this->attributes['discount_amount'])) {
      // Log::error("aaa" . numFormat($this->attributes['amount'] - $this->attributes['discount_amount']));
      return numFormat($this->attributes['amount'] - $this->attributes['discount_amount']);
    } else {
      return 0.00;
    }
  }

  public function getProjNameAttribute()
  {
    if (isset($this->attributes['proj_id'])) {
      $proj = getProjById($this->attributes['proj_id']);
      return $proj['proj_name'];
    }
  }
  public function getStatusLabelAttribute()
  {
    if (isset($this->attributes['status'])) {
      $status = $this->attributes['status'];
      if ($status == 1) {
        return '已结清';
      } else if ($status == 0) {
        return "未结清";
      } else if ($status == 2) {
        return '已退款';
      }
    }
  }
  public function getBillStatusAttribute()
  {
    if (isset($this->attributes['bill_id'])) {
      return $this->attributes['status'] ?  '已生成' : "未生成";
    }
  }

  public function contract()
  {
    return $this->belongsTo(Contract::class, 'contract_id', 'id');
  }

  public function feeType()
  {
    return $this->belongsTo(FeeType::class, 'fee_type', 'id');
  }

  public function invoiceRecord()
  {
    return $this->hasOne(InvoiceRecord::class, 'invoice_id', 'id');
  }

  public function chargeBillRecord()
  {
    return $this->hasMany(ChargeBillRecord::class, 'bill_detail_id', 'id');
  }


  public function billDetailLog()
  {
    return $this->hasMany(TenantBillDetailLog::class, 'bill_detail_id', 'id');
  }

  public function refundRecord()
  {
    return $this->hasMany(RefundRecord::class, 'bill_detail_id', 'id');
  }

  public function addAll($data)
  {
    $res = DB::table($this->getTable())->insert($data);
    return $res;
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
