<?php

namespace App\Api\Models\Contract;

use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;


class ContractTemplate extends BaseModel
{
  protected $table = 'bse_template';

  protected $fillable = ['company_id', 'name', 'file_name', 'file_path', 'remark', 'c_uid', 'u_uid'];
  protected $hidden = ['deleted_at', 'updated_at', 'company_id'];

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
