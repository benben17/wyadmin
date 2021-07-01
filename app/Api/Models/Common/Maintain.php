<?php

namespace App\Api\Models\Common;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

/**
 *
 */

class Maintain extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  use SoftDeletes;
  protected $table = 'bse_maintain';

  protected $fillable = ['parent_id', 'parent_type', 'maintain_record', 'maintain_feedback', 'maintain_user', 'maintain_date', 'c_username', 'c_uid', 'u_uid', 'maintain_type'];

  protected $hidden = ['deleted_at', 'company_id', 'u_uid', 'updated_at'];


  public function channel()
  {
    return $this->hasOne('App\Api\Models\Channel\Channel', 'id', 'parent_id');
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
