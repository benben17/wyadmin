<?php

namespace App\Api\Models\Tenant;

use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;

class Remind extends BaseModel
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */
  protected $table = 'bse_tenant_remind';
  protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid', 'updated_at', 'created_at'];

  protected $fillable = [];


  public function customer()
  {
    return $this->hasOne(Tenant::class, 'id', 'tenant_id');
  }


  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
