<?php

namespace App\Api\Models\Sys;

use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 系统菜单 Model 包含小程序和后台菜单
 *
 * @Desc:
 * @Author leezhua
 * @Date 2024-07-15
 */
class SysMenu extends Model
{
  // use SoftDeletes;

  protected $table = 'sys_menu';

  protected $hidden = ['created_at', 'updated_at'];

  public function children()
  {
    return $this->hasMany(SysMenu::class, 'parent_id', 'id');
  }

  public function parent()
  {
    return $this->belongsTo(SysMenu::class, 'parent_id', 'id');
  }

  // protected static function boot()
  // {
  //   parent::boot();
  //   static::addGlobalScope(new CompanyScope);
  // }
}
