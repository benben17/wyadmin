<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;

/**
 *
 */
class TenantBillDetail extends Model
{

  // var $parentType = 3;  // 维护
  protected $table = 'bse_tenant_bill_detail';
  protected $fillable = [];
  protected $hidden = ['updated_at','created_at'];

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }

}