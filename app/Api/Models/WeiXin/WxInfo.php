<?php

namespace App\Api\Models\Weixin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

class WxInfo extends Model
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