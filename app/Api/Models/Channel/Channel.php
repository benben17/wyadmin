<?php

namespace App\Api\Models\Channel;

use App\Api\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use App\Enums\AppEnum;

/**
 *
 */
class Channel extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */
  protected $autoCheckFields = false;
  /** 1 渠道 [description] */
  var $parentType = AppEnum::Channel;
  use SoftDeletes;

  protected $table = 'bse_channel';
  // protected $primaryKey = 'id';

  protected $fillable = ['company_id', 'channel_name', 'channel_addr', 'policy_id', 'channel_type', 'channel_policy', 'brokerage', 'remark', 'c_uid', 'u_uid', 'is_valid', 'proj_ids'];
  protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid', 'updated_at'];
  protected $appends = ['create_user', 'valid_label'];

  public function getCreateUserAttribute()
  {
    if (isset($this->attributes['c_uid'])) {
      $user =  getUserByUid($this->attributes['c_uid']);
      return $user['realname'];
    }
  }

  public function getValidLabelAttribute()
  {
    if (isset($this->attributes['is_valid'])) {
      return $this->attributes['is_valid'] === 1 ? "启用" : "禁用";
    }
  }

  public function channelContact()
  {
    return $this->hasMany('App\Api\Models\Common\Contact', 'parent_id', 'id')
      ->where('parent_type', $this->parentType);
  }

  public function createUser()
  {
    return $this->hasOne('App\Models\User', 'id', 'c_uid');
  }

  public function channelMaintain()
  {
    return $this->hasMany('App\Api\Models\Common\Maintain', 'parent_id', 'id')
      ->where('parent_type', $this->parentType);
  }
  public function customer()
  {
    return $this->hasMany(Tenant::class, 'channel_id', 'id');
  }

  public function brokerageLog()
  {
    return $this->hasMany(ChannelBrokerage::class, 'channel_id', 'id');
  }

  /** 获取政策 */
  public function channelPolicy()
  {
    return $this->hasOne(ChannelPolicy::class, 'id', 'policy_id');
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
