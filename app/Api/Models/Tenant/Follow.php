<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

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
  protected $fillable = ['company_id', 'cus_id', 'cus_follow_type', 'cus_state', 'cus_follow_record', 'cus_follow_time', 'cus_feedback', 'cus_contact_id', 'cus_loss_reason', 'cus_contact_user', 'follow_usrename', 'c_uid', 'u_uid', 'remark'];
  protected $appends = ['follow_type_label'];

  public function tenant()
  {
    return $this->hasOne(Tenant::class, 'id', 'tenant_id');
  }
  public function user()
  {
    return $this->hasOne(User::class, 'id', 'c_uid');
  }



  public function getFollowTypeLabelAttribute()
  {
    switch ($this->attributes['follow_type']) {
      case '1':
        return '来访';
        break;
      case '2':
        return '电话';
        break;
      case '3':
        return '微信';
        break;
      case '4':
        return 'QQ';
        break;
      case '5':
        return '其他';
        break;
    }
  }

  //跟进类型：1来访，2 电话，3微信  ，4QQ、5其他

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
