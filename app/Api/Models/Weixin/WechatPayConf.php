<?php

namespace App\Api\Models\Weixin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

/**
 * 微信支付 配置表
 *
 * @Author leezhua
 * @DateTime 2024-01-14
 */
class WeChatPayConf extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */


  use SoftDeletes;

  protected $table = 'base_wechatpay_conf';
  protected $fillable = ['*'];
  protected $hidden = ['deleted_at', "company_id"];


  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
