<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use App\Enums\AppEnum;

/**
 *  租户合同
 */
class TenantContract extends Model
{
  protected $table = 'bse_tenant_contract';
  protected $fillable = [];
  protected $hidden = ['company_id', 'created_at'];

  // protected $appends = ['proj_name'];
  //

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
