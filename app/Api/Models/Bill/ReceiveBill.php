<?php

namespace App\Api\Models\Bill;

use Illuminate\Database\Eloquent\Model;

use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 */
class ReceiveBill extends Model
{

  use SoftDeletes;
  protected $table = 'bse_receive_bill';
  protected $fillable = [];
  protected $hidden = ['deleted_at', 'company_id'];


  /** 账单详细 */
  public function billDetail()
  {
    return $this->belongsTo(TenantBillDetail::class, 'bill_detail_id', 'id');
  }


  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
