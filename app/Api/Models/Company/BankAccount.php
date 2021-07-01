<?php
namespace App\Api\Models\Company;

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
  protected $hidden = ['deleted_at',"company_id",'c_uid','u_uid','updated_at'];
  protected $fillable = ['company_id','bank_name','account_name','account_number','c_uid','u_uid','remark','is_vaild'];


  public function project()
  {
      return $this->hasMany('App\Api\Models\Project','id','bank_id');
  }

  protected static function boot()
  {
  	parent::boot();
  	static::addGlobalScope(new CompanyScope);
  }
}