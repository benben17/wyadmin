<?php

namespace App\Api\Models\Company;

use App\Models\BaseModel;


/**
 * 收款帐号管理
 */
class CompanyVariable extends BaseModel
{

  // use SoftDeletes;
  protected $table = 'bse_company_variable';
  protected $primaryKey = 'company_id';
  protected $hidden = ['deleted_at', 'updated_at'];
  // protected $fillable = ['no','company_id'];
}
