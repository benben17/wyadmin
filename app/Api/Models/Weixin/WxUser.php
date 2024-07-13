<?php

namespace App\Api\Models\Weixin;

use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WxUser extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */


  use SoftDeletes;

  protected $table = 'bse_wx_user';
  protected $fillable = ['*'];
  protected $hidden = ['deleted_at', "company_id"];


  // protected static function boot()
  // {
  //   parent::boot();
  //   static::addGlobalScope(new CompanyScope);
  // }
}
