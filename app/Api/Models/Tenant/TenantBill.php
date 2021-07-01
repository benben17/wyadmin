<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

use App\Api\Scopes\CompanyScope;

/**
 *
 */
class TenantBill extends Model
{

  protected $table = 'bse_tenant_bill';
  protected $fillable = [];
  protected $hidden = ['updated_at','company_id','created_at'];



  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}