<?php

namespace App\Api\Models\Tenant;

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
  protected $hidden = ['updated_at','company_id','created_at'];

  protected $appends = ['proj_name','on_rent_label'];
  public function getProjNameAttribute () {
    $projId = $this->attributes['proj_id'];
    $proj = Project::select('proj_name')->find($projId);
    return $proj['proj_name'];
  }
  public function getOnRentLabelAttribute(){
    $onRent = $this->attributes['on_rent'];
    if ($onRent) {
      return '在租';
    }else{
      return '非在租';
    }
  }
  public function tenantShare(){
    return $this->hasMany(Tenant::class,'parent_id','id');
  }

  // 维护信息
  public function maintain(){
    return $this->hasMany('App\Api\Models\Common\Maintain','parent_id','id')
    ->where('parent_type',$this->parentType);
  }

  // 联系人
  public function tenantContact(){
    return $this->hasMany('App\Api\Models\Common\Contact','parent_id','id')->where('parent_type',$this->parentType);
  }

  // 工商信息
  public function businessInfo(){
    return $this->hasOne('App\Api\Models\Customer\CustomerBaseInfo','id','business_id');
  }

  // 发票抬头
  public function invoice(){
    return $this->hasOne('App\Api\Models\Operation\Invoice','tenant_id','id');
  }

  // 合同信息
  public function contract(){
    return $this->hasMany(TenantContract::class,'tenant_id','id');
  }
  protected static function boot(){
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}