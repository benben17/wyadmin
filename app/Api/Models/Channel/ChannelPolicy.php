<?php
namespace App\Api\Models\Channel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;


/**
 * 渠道佣金政策
 */
class ChannelPolicy extends Model
{

  use SoftDeletes;

  protected $table = 'bse_channel_policy';
  protected $fillable = ['channel_id','policy_type','month','c_username','remark','c_uid','u_uid','is_vaild'];

  protected $hidden = ['deleted_at','updated_at'];

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }

}