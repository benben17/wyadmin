<?php

namespace App\Api\Models\Contract;

use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends BaseModel
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */
  use SoftDeletes;
  protected $table = 'bse_contract';

  protected $fillable = ['company_id', 'proj_id', 'contract_no', 'contract_type', 'violate_rate', 'contract_state', 'sign_date', 'start_date', 'end_date', 'belong_uid', 'belong_person', 'tenant_id', 'tenant_name', 'customer_industry', 'customer_sign_person', 'customer_legal_person', 'sign_area', 'c_uid', 'u_uid', 'lease_term', 'audit_uid', 'audit_state', 'attr', 'bank_account_id', 'rental_deposit_amount', 'rental_deposit_month', 'increase_year', 'increase_rate', 'increase_date', 'bill_day', 'ahead_pay_month', 'rental_price_type', 'rental_price', 'rental_month_amount', 'management_price', 'pay_method', 'rental_bank_id', 'manager_bank_id', 'manager_deposit_month', 'manager_deposit_amount', 'manager_show', 'increase_show', 'rental_usage', 'management_month_amount'];

  protected $hidden = ['deleted_at', 'company_id', 'c_uid', 'u_uid', 'updated_at'];
  protected $appends = ['state_label', 'proj_name'];


  public function getTenantNameAttribute()
  {
    if (isset($this->attributes['tenant_id'])) {
      $tenant = $this->tenant()->first();
      return $tenant->name ?? "";
    }
  }

  public function contractRoom()
  {
    return $this->hasMany(ContractRoom::class,  'contract_id', 'id');
  }

  public function freeList()
  {
    return $this->hasMany(ContractFreePeriod::class, 'contract_id', 'id');
  }

  public function tenant()
  {
    return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
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
  // 合同日志
  public function contractLog()
  {
    return $this->hasMany(ContractLog::class, 'contract_id', 'id');
  }

  // 分摊规则
  // public function shareRule()
  // {
  //   return $this->hasMany(TenantShareRule::class, 'contract_id', 'id');
  // }


  // 项目名称
  public function getProjNameAttribute()
  {
    if (isset($this->attributes['proj_id'])) {
      $proj = getProjById($this->attributes['proj_id']);
      return $proj['proj_name'] ?? "";
    }
  }

  // 合同状态
  public function getStateLabelAttribute()
  {

    $stateLabels = [
      '0' => '待提交',
      '1' => '待审核',
      '2' => '正常执行',
      '3' => '执行完成',
      '98' => '退租合同',
      '99' => '作废合同',
    ];
    $contractState = $this->attributes['contract_state'] ?? 0;
    return $stateLabels[$contractState] ?? 0;
  }

  public function getRentalPriceAttribute()
  {
    if ($this->getRental()) {
      return $this->getRental()->unit_price;
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
