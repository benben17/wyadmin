<?php

namespace App\Api\Models\Contract;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\Log;

class Contract extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  protected $table = 'bse_contract';

  protected $fillable = ['company_id', 'proj_id', 'contract_no', 'contract_type', 'violate_rate', 'contract_state', 'sign_date', 'start_date', 'end_date', 'belong_uid', 'belong_person', 'tenant_id', 'tenant_name', 'customer_industry', 'customer_sign_person', 'customer_legal_person', 'sign_area', 'c_uid', 'u_uid', 'lease_term', 'audit_uid', 'audit_state', 'attr', 'bank_account_id', 'rental_deposit_amount', 'rental_deposit_month', 'increase_year', 'increase_rate', 'increase_date', 'bill_day', 'ahead_pay_month', 'rental_price_type', 'rental_price', 'rental_month_amount', 'management_price', 'pay_method', 'rental_bank_name', 'rental_account_name', 'rental_account_number', 'manager_bank_name', 'manager_account_name', 'manager_account_number', 'rental_bank_id', 'manager_bank_id', 'manager_deposit_month', 'manager_deposit_amount', 'manager_show', 'increase_show', 'rental_usage', 'management_month_amount'];

  protected $hidden = ['deleted_at', 'company_id', 'c_uid', 'u_uid', 'updated_at'];
  protected $appends = ['state_label', 'proj_name'];
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

  public function billRule()
  {
    return $this->hasMany(BillRule::class, 'contract_id', 'id')->where('type', 1);
  }
  public function depositRule()
  {
    return $this->hasMany(BillRule::class, 'contract_id', 'id')->where('type', 2);
  }

  public function contractLog()
  {
    return $this->hasMany(ContractLog::class, 'contract_id', 'id');
  }

  public function getProjNameAttribute()
  {
    if (isset($this->attributes['proj_id'])) {
      $proj = getProjById($this->attributes['proj_id']);
      return $proj['proj_name'];
    }
  }
  public function getStateLabelAttribute()
  {
    if (isset($this->attributes['contract_state'])) {
      $value =  $this->attributes['contract_state'];
      switch ($value) {
        case '0':
          return "待提交";
          break;
        case '1':
          return "待审核";
          break;
        case '2':
          return '正常执行';
          break;
        case '98':
          return '退租合同';
          break;
        case '99':
          return '作废合同';
          break;
      }
    }
  }

  public function getRentalPriceAttribute()
  {
    if ($this->getRental()) {
      return $this->getRental()->unit_price . $this->getRental()->unit_price_label;
    }
    return 0.00;
  }

  public function getRentalMonthAmountAttribute()
  {

    if ($this->getRental()) {
      return $this->getRental()->month_amt;
    }
    return 0.00;
  }

  private function getRental()
  {
    return BillRule::where('contract_id', $this->attributes['id'])->where('fee_type', 101)->first();
  }
  public function getManagementPriceAttribute()
  {

    if ($this->getMenage()) {
      return $this->getMenage()->unit_price . $this->getMenage()->unit_price_label;
    }
    return 0.00;
  }
  private function getMenage()
  {
    return BillRule::where('contract_id', $this->attributes['id'])->where('fee_type', 102)->first();
  }
  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
