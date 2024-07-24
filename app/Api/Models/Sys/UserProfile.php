<?php

namespace App\Api\Models\Sys;

use App\Models\BaseModel;


/**
 *   用户变量模型
 *
 */
class UserProfile extends BaseModel
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  protected $primaryKey = 'user_id';
  protected $table = 'bse_user_profile';
  protected $fillable = ['default_proj_id', 'page_rows', 'user_id', 'created_at', 'updated_at'];
  protected $hidden = ['created_at', 'updated_at'];



  // protected static function boot(){
  //   parent::boot();
  //   static::addGlobalScope(new CompanyScope);
  // }
}
