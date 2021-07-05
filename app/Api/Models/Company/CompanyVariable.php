<?php

namespace App\Api\Models\Company;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;

/**
 * 收款帐号管理
 */
class CompanyVariable extends Model
{

  // use SoftDeletes;
  protected $table = 'bse_company_variable';
  protected $primaryKey = 'company_id';
  protected $hidden = ['deleted_at', 'updated_at'];
  // protected $fillable = ['no','company_id'];
}
