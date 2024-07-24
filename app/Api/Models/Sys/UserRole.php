<?php

namespace App\Api\Models\Sys;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserRole extends BaseModel
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */


  use SoftDeletes;

  protected $table = 'sys_role';
  protected $fillable = [];
  protected $hidden = [];



  // protected static function boot(){
  //   parent::boot();
  //   static::addGlobalScope(new CompanyScope);
  // }
}
