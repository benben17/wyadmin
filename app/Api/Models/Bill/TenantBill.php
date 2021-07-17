<?php

namespace App\Api\Models\Bill;

use Illuminate\Database\Eloquent\Model;

use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 */
class TenantBill extends Model
{

  use SoftDeletes;
  protected $table = 'bse_tenant_bill';
  protected $fillable = [];
  protected $hidden = ['deleted_at', 'company_id'];
  protected $appends = ['is_print_label', 'status_label'];

  /** 账单详细 */
  public function billDetail()
  {
    return $this->hasMany(TenantBillDetail::class, 'bill_id', 'id');
  }
  public function getIsPrintLabelAttribute()
  {
    if (isset($this->attributes['is_print'])) {
      return $this->attributes['is_print'] ?  '已打印' : "未打印";
    }
  }
  public function getStatusLabelAttribute()
  {
    if (isset($this->attributes['status'])) {
      return $this->attributes['status'] ?  '已结清' : "未结清";
    }
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
