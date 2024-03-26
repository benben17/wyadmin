<?php

namespace App\Api\Models\Bill;

use App\Api\Models\Contract\Contract;
use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 *  账单详细
 */
class TenantBillDetailLog extends Model
{
  use SoftDeletes;
  protected $table = 'bse_tenant_bill_detail_log';
  protected $fillable = [];
  protected $hidden = ['deleted_at', 'updated_at', 'company_id', 'u_uid'];


  public function billDetail()
  {
    return $this->hasOne(TenantBillDetail::class, 'bill_detail_id', 'id');
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
