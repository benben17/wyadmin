<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 */
class TenantLeaseback extends Model
{

  use SoftDeletes;
  protected $table = 'bse_tenant_leaseback';
  protected $fillable = [];
  protected $hidden = ['updated_at', 'created_at'];


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
    return $this->hasOne('\App\Api\Models\Contract\Contract', 'id', 'contract_id');
  }

  public function tenant()
  {
    return $this->hasOne(Tenant::class, 'id', 'tenant_id');
  }
}
