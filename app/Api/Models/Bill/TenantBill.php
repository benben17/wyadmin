<?php

namespace App\Api\Models\Bill;

use App\Api\Scopes\CompanyScope;
use App\Api\Models\Tenant\Tenant;

use Illuminate\Database\Eloquent\Model;
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

  public function tenant()
  {
    return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
  }

  public function getIsPrintLabelAttribute()
  {
    if (isset($this->attributes['is_print'])) {
      return $this->attributes['is_print'] == 1 ?  '已打印' : "未打印";
    }
  }
  public function getStatusLabelAttribute()
  {
    if (isset($this->attributes['status'])) {
      return $this->attributes['status'] == 1 ?  '已审核' : "未审核";
    }
  }


  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
