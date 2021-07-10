<?php

namespace App\Api\Models\Tenant;

use App\Api\Models\Common\Maintain;
use App\Api\Models\Contract\Contract;
use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use App\Api\Models\Project;
use App\Enums\AppEnum;

/**
 *
 */
class Tenant extends Model
{

  var $parentType = AppEnum::Tenant;  // 维护
  protected $table = 'bse_tenant';
  protected $fillable = [];
  protected $hidden = ['updated_at', 'company_id', 'created_at'];

  protected $appends = ['proj_name', 'on_rent_label'];
  public function getProjNameAttribute()
  {
    $projId = $this->attributes['proj_id'];
    $proj = Project::select('proj_name')->find($projId);
    return $proj['proj_name'];
  }
  public function getOnRentLabelAttribute()
  {
    $onRent = $this->attributes['on_rent'];
    if ($onRent) {
      return '在租';
    } else {
      return '非在租';
    }
  }
  public function tenantShare()
  {
    return $this->hasMany(Tenant::class, 'parent_id', 'id');
  }

  // 维护信息
  public function maintain()
  {
    return $this->hasMany(Maintain::class, 'parent_id', 'id')
      ->where('parent_type', $this->parentType);
  }

  // 联系人
  public function contact()
  {
    return $this->hasMany('App\Api\Models\Common\Contact', 'parent_id', 'id')->where('parent_type', $this->parentType);
  }

  // 工商信息
  public function businessInfo()
  {
    return $this->hasOne(BaseInfo::class, 'id', 'business_id');
  }

  // 发票抬头
  public function invoice()
  {
    return $this->hasOne(Invoice::class, 'tenant_id', 'id');
  }

  public function follow()
  {
    return $this->hasMany(Follow::class, 'tenant_id', 'id');
  }
  public function remind()
  {
    return $this->hasMany(Remind::class, 'tenant_id', 'id');
  }
  public function room()
  {
    return $this->hasMany(TenantRoom::class, 'tenant_id', 'id');
  }
  public function extraInfo()
  {
    return $this->hasOne(ExtraInfo::class, 'tenant_id', 'id');
  }

  // 合同信息
  public function contract()
  {
    return $this->hasMany(Contract::class, 'tenant_id', 'id');
  }
  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
