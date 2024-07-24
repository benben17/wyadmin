<?php

namespace App\Api\Models\Common;

use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;
use App\Api\Models\Channel\Channel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 公共维护 1 渠道 2 客户 3 供应商 4 政府关系 5 租户 
 */
class Maintain extends BaseModel
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  use SoftDeletes;
  protected $table = 'bse_maintain';

  protected $fillable = ['parent_id', 'parent_type', 'maintain_record', 'maintain_feedback', 'maintain_user', 'maintain_date', 'c_username', 'c_uid', 'u_uid', 'maintain_type'];
  protected $hidden = ['deleted_at', 'company_id', 'u_uid', 'updated_at'];


  /**
   * @Desc: 关联渠道
   * @Author leezhua
   * @Date 2024-04-02
   * @return HasOne 
   */
  public function channel()
  {
    return $this->hasOne(Channel::class, 'id', 'parent_id');
  }
  /**
   * 
   * @Author leezhua
   * @Date 2024-04-02
   * @return HasOne 
   */
  public function createUser()
  {
    return $this->hasOne('App\Models\User', 'id', 'c_uid');
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
