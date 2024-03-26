<?php

namespace App\Api\Models\Company;

use App\Api\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;

/**
 * 收款帐号管理
 */
class BankAccount extends Model
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
