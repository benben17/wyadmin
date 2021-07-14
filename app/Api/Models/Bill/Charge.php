<?php

namespace App\Api\Models\Bill;

use App\Api\Models\Company\BankAccount;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class Charge extends Model
{

  use SoftDeletes;
  protected $table = 'bse_charge';
  protected $fillable = [];
  protected $hidden = ['company_id', 'deleted_at', 'updated_at'];

  protected $appends = ['tenant_name', 'c_name', 'type_label', 'bank_name'];
  public function getTenantNameAttribute()
  {
    $tenantId = $this->attributes['tenant_id'];
    $tenant = Tenant::select('name')->find($tenantId);
    return $tenant['name'];
  }

  public function getTypeLabelAttribute()
  {
    if ($this->attributes['type'] == 1) {
      return "收入";
    } else if ($this->attributes['type'] == 1) {
      return "支出";
    }
  }
  public function getcNameAttribute()
  {
    $c_uid = $this->attributes['c_uid'];
    $user = \App\Models\User::select('realname')->find($c_uid);
    return $user['realname'];
  }
  public function getBankNameAttribute()
  {
    $bankId = $this->attributes['bank_id'];
    $bank =  BankAccount::select('account_name')->find($bankId);
    return $bank['account_name'];
  }
  public function chargeBillRecord()
  {
    return $this->hasMany(ChargeBillRecord::class, 'charge_id', 'id');
  }
  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
