<?php

namespace App\Api\Models\Tenant;

use App\Api\Models\Channel\Channel;
use App\Api\Models\Channel\ChannelBrokerage;
use App\Api\Models\Common\Maintain;
use App\Api\Models\Contract\Contract;
use App\Api\Models\Contract\ContractRoom;
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
  protected $hidden = ['updated_at', 'company_id', 'deleted_at'];

  protected $appends = ['status_label'];

  public function getStatusLabelAttribute()
  {
    if (isset($this->attributes['status'])) {
      if ($this->attributes['status'] == 1) {
        return  '在租';
      } else {
        return '退租';
      }
    }
  }
  public function getProjNameAttribute()
  {
    if (isset($this->attributes['proj_id'])) {
      return getProjById($this->attributes['proj_id'])['proj_name'];
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
  public function contacts()
  {
    return $this->hasMany('App\Api\Models\Common\Contact', 'parent_id', 'id')
      ->where('parent_type', $this->parentType);
  }


  // 发票抬头
  public function invoice()
  {
    $invoice =  $this->hasOne(Invoice::class, 'tenant_id', 'id');

    return $invoice;
  }

  public function follow()
  {
    return $this->hasMany(Follow::class, 'tenant_id', 'id');
  }
  public function remind()
  {
    return $this->hasMany(Remind::class, 'tenant_id', 'id');
  }

  public function extraInfo()
  {
    return $this->hasOne(ExtraInfo::class, 'tenant_id', 'id');
  }

  public function business_info()
  {
    return $this->hasOne(BaseInfo::class, 'id', 'business_id');
  }
  // 合同信息
  public function contract()
  {
    return $this->hasMany(Contract::class, 'tenant_id', 'id');
  }

  public function channel()
  {
    return $this->hasOne(Channel::class,  'id', 'channel_id');
  }

  public function contractStat()
  {
    return $this->hasOne(Contract::class, 'tenant_id', 'id');
  }

  public function tenantRooms()
  {
    return $this->hasMany(TenantRoom::class, 'tenant_id', 'id');
  }

  // 佣金

  public function brokerageLog()
  {
    return $this->hasMany(ChannelBrokerage::class, 'tenant_id', 'id');
  }


  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
