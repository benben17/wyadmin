<?php

namespace App\Api\Models\Channel;

use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;
use App\Api\Models\Tenant\Tenant;



/**
 *. 渠道佣金日志
 */
class ChannelBrokerage extends BaseModel
{

  // use SoftDeletes;

  protected $table = 'bse_channel_brokerage_log';

  protected $fillable = ["channel_id", "tenant_id", "brokerage", "remark", "u_uid", "company_id", "created_at", "updated_at", "deleted_at", "id"];
  protected $hidden = ['deleted_at', "company_id", 'u_uid', 'updated_at'];
  protected $appends = ['tenant_name', 'channel_name'];


  public function getTenantNameAttribute()
  {
    if (isset($this->attributes['tenant_id'])) {
      return getTenantNameById($this->attributes['tenant_id']);
    }
  }
  public function getChannelNameAttribute()
  {
    if (isset($this->attributes['channel_id'])) {
      return $this->channel()->first()->channel_name;
    }
  }
  public function tenant()
  {
    return $this->hasOne(Tenant::class, 'id', 'tenant_id');
  }

  public function channel()
  {
    return $this->belongsTo(Channel::class,  'channel_id', 'id');
  }
  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
