<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;
/**
 *
 */
class TenantShare extends Model
{

  use SoftDeletes;
  protected $table = 'bse_tenant_share';
  protected $fillable = [];
  protected $hidden = ['updated_at','created_at'];


  public function contract(){
    return $this->hasOne('\App\Api\Models\Contract\Contract','id','contract_id');
  }

  public function getShareTypeLabelAttribute(){
    $sharetype = $this->attributes['share_type'];
    if ($sharetype == 1) {
      return '面积分摊';
    }else if ($sharetype == 2) {
      return '比例分摊';
    }else if ($sharetype == 3) {
      return '固定金额';
    }
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}