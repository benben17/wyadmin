<?php
namespace App\Api\Models\Sys;

use Illuminate\Database\Eloquent\Model;


/**
 *   用户变量模型
 *
 */
class UserProfile extends Model
{
   /**
    * 关联到模型的数据表
    *
    * @var string
    */

  protected $primaryKey = 'user_id';
  protected $table = 'bse_user_profile';
  protected $fillable = ['default_proj_id','page_rows'];
  protected $hidden = ['created_at','updated_at'];



  // protected static function boot(){
  //   parent::boot();
  //   static::addGlobalScope(new CompanyScope);
  // }
}
