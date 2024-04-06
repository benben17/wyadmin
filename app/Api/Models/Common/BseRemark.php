<?php

namespace App\Api\Models\Common;

use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BseRemark extends Model
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

  public function addAll(array $data)
  {
    return $this->insert($data);
  }

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
