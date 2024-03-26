<?php

namespace App\Api\Models\Channel;

use App\Api\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;


/**
 *. 渠道佣金日志
 */
class ChannelBrokerage extends Model
{

  // use SoftDeletes;

  protected $table = 'bse_channel_brokerage_log';

  // protected $fillable = [];
  protected $hidden = ['deleted_at', "company_id", 'u_uid', 'updated_at'];
  protected $appends = ['tenant_name'];


  public function getTenantNameAttribute()
  {
    if (isset($this->attributes['tenant_id'])) {
      return getTenantNameById($this->attributes['tenant_id']);
    }
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
