<?php

namespace App\Api\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;


class UserRole extends Model
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
