<?php

namespace App\Api\Models\Company;

use App\Models\BaseModel;
use App\Api\Models\Project;
use App\Api\Scopes\CompanyScope;

/**
 * 收款帐号管理
 */
class BankAccount extends BaseModel
{

  // use SoftDeletes;
  protected $table = 'bse_bank_account';
  protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid', 'updated_at'];
  protected $fillable = ['company_id', 'bank_name', 'account_name', 'account_number', 'c_uid', 'u_uid', 'remark', 'is_valid', 'proj_id'];


  public function project()
  {
    return $this->hasOne(Project::class, 'id', 'proj_id');
  }

  // public function feeTypes()
  // {
  //   return $this->hasMany(FeeType::class, 'id', 'proj_id');
  // }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
