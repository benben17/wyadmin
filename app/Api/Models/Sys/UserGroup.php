<?php

namespace App\Api\Models\Sys;

use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class UserGroup extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */


  use SoftDeletes;

  protected $table = 'sys_user_group';

  public function user()
  {
    return $this->hasMany('App\Models\User', 'group_id', 'id');
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
