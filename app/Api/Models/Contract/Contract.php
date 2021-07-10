<?php

namespace App\Api\Models\Contract;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

class Contract extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  protected $table = 'bse_contract';

  protected $fillable = ['company_id', 'proj_id', 'contract_no', 'contract_type', 'violate_rate', 'contract_state', 'sign_date', 'start_date', 'end_date', 'belong_uid', 'belong_person', 'tenant_id', 'tenant_name', 'customer_industry', 'customer_sign_person', 'customer_legal_person', 'sign_area', 'c_uid', 'u_uid', 'lease_term', 'audit_uid', 'audit_state', 'attr', 'bank_account_id', 'rental_deposit_amount', 'rental_deposit_month', 'increase_year', 'increase_rate', 'increase_date', 'bill_day', 'ahead_pay_month', 'rental_price_type', 'rental_price', 'rental_month_amount', 'management_price', 'pay_method', 'rental_bank_name', 'rental_account_name', 'rental_account_number', 'manager_bank_name', 'manager_account_name', 'manager_account_number', 'rental_bank_id', 'manager_bank_id', 'manager_deposit_month', 'manager_deposit_amount', 'manager_show', 'increase_show', 'rental_usage', 'management_month_amount'];

  protected $hidden = ['deleted_at', 'c_uid', 'u_uid', 'updated_at'];

  public function contractRoom()
  {
    return $this->hasMany(ContractRoom::class, 'contract_id', 'id');
  }

  public function freeList()
  {
    return $this->hasMany(ContractFreePeriod::class, 'contract_id', 'id');
  }

  public function project()
  {
    return $this->hasOne('App\Api\Models\Project', 'id', 'proj_id');
  }

  public function contractBill()
  {
    return $this->hasMany(ContractBill::class, 'contract_id', 'id');
  }

  public function contractLog()
  {
    return $this->hasMany(ContractLog::class, 'contract_id', 'id');
  }


  // public function getContractStateAttribute($value){

  // }
  // public function setContractStateAttribute($value)
  //   {
  //      $this->attributes['contract_state'] = $value;
  //   }


  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
