<?php

namespace App\Api\Models\Operation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

class Invoice extends Model
{
 /**
  * 关联到模型的数据表
  *
  * @var 租户发票信息
  */

  protected $table = 'bse_tenant_invoice';
  // protected $fillable = [''];
  protected $hidden = ['deleted_at','c_uid','u_uid','updated_at'];

}
