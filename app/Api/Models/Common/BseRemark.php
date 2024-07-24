<?php

namespace App\Api\Models\Common;

use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class BseRemark extends BaseModel
{
  /**
   * 备注公共模型
   *
   * @Author leezhua
   * @DateTime 2024-03-28
   */
  use SoftDeletes;
  protected $table = 'bse_common_remark';
  protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid', 'updated_at', 'company_id', 'id'];
  protected $fillable = ['company_id', 'parent_type', 'parent_id', 'remark_content', 'c_user', 'c_uid', 'u_uid'];
  protected $appends = ['c_user_name'];


  public function getCUserNameAttribute()
  {
    return $this->createUser->realname;
  }

  public function createUser()
  {
    return $this->hasOne('App\Models\User', 'id', 'c_uid');
  }
  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
