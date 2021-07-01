<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use App\Enums\AppEnum;

/**
 *  租户合同
 */
class TenantContractRoom extends Model
{
  protected $table = 'bse_tenant_contract_room';
  protected $fillable = [];
  protected $hidden = ['updated_at', 'company_id', 'created_at'];

  public function addAll(array $data)
  {
    $res = DB::table($this->getTable())->insert($data);
    return $res;
  }
  // protected $appends = ['proj_name'];
  //
  // protected static function boot()
  // {
  //   parent::boot();
  //   static::addGlobalScope(new CompanyScope);
  // }
}
