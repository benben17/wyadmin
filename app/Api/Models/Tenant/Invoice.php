<?php

namespace App\Api\Models\Tenant;

use App\Models\BaseModel;

class Invoice extends BaseModel
{
  /**
   * 关联到模型的数据表
   *
   * @var 租户发票信息
   */

  protected $table = 'bse_tenant_invoice';
  // protected $fillable = [''];
  protected $hidden = ['deleted_at', 'c_uid', 'u_uid', 'updated_at'];
}
