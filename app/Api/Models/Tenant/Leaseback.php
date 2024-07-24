<?php

namespace App\Api\Models\Tenant;

use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;
use App\Api\Models\Contract\Contract;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 */
class Leaseback extends BaseModel
{

  use SoftDeletes;
  protected $table = 'bse_tenant_leaseback';
  protected $fillable = [];
  protected $hidden = ['updated_at'];


  protected $appends = ['type_label'];
  public function getTypeLabelAttribute()
  {
    $type = $this->attributes['type'];
    if ($type == 1) {
      return "正常退租";
    } else if ($type == 2) {
      return "提前退租";
    } else {
      return "其他";
    }
  }

  public function contract()
  {
    return $this->hasOne(Contract::class, 'id', 'contract_id');
  }

  public function tenant()
  {
    return $this->hasOne(Tenant::class, 'id', 'tenant_id');
  }
  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
