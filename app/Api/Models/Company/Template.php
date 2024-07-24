<?php

namespace App\Api\Models\Company;

use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**   模版 type 1 合同模版 2账单模版 */
class Template extends Model
{

  use SoftDeletes;

  protected $table = 'bse_template';
  protected $fillable = ['company_id', 'name', 'file_name', 'file_path', 'remark', 'c_uid', 'u_uid'];
  protected $hidden = ['deleted_at', 'updated_at', 'company_id'];


  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
