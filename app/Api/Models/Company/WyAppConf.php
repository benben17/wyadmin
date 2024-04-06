<?php

namespace App\Api\Models\Company;

use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 小程序以及公众号配置 支付配置
 *
 * @Author leezhua
 * @DateTime 2024-01-14
 */
class WyAppConf extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */


  use SoftDeletes;

  protected $table = 'base_app_conf';
  protected $fillable = ['*'];
  protected $hidden = ['deleted_at', "company_id"];


  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
