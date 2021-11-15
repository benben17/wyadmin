<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\Log;
use App\Api\Models\User;

class Follow extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */
  protected $table = 'bse_tenant_follow';
  protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid', 'updated_at'];
  protected $fillable = [];
  protected $appends = ['follow_type_label', 'tenant_name'];

  public function tenant()
  {
    return $this->belongsTo(Tenant::class,  'tenant_id', 'id');
  }
  public function user()
  {
    return $this->hasOne(User::class, 'id', 'c_uid');
  }
  public function getTenantNameAttribute()
  {
    if (isset($this->attributes['tenant_id'])) {
      $tenant = Tenant::select('name')->find($this->attributes['tenant_id']);
      return $tenant['name'];
    }
  }





  public function getFollowTypeLabelAttribute()
  {

    if (isset($this->attributes['follow_type'])) {
      Log::error($this->attributes['follow_type']);
      return getDictName($this->attributes['follow_type']);
    }
  }

  //跟进类型：1来访，2 电话，3微信  ，4QQ、5其他

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
