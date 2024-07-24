<?php

namespace App\Api\Models\Channel;

use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 渠道佣金政策
 */
class ChannelPolicy extends BaseModel
{

  use SoftDeletes;

  protected $table = 'bse_channel_policy';
  protected $fillable = ['channel_id', 'policy_type', 'month', 'c_username', 'remark', 'c_uid', 'u_uid', 'is_vaild'];

  protected $hidden = ['deleted_at', 'updated_at'];

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
