<?php

namespace App\Api\Models\Bill;

use App\Api\Models\Company\BankAccount;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeBill extends Model
{

  use SoftDeletes;
  protected $table = 'bse_charge_bill';
  protected $fillable = [];
  protected $hidden = ['company_id', 'deleted_at', 'updated_at'];

  protected $appends = ['tenant_name', 'c_name', 'type_label', 'bank_name'];
  public function getTenantNameAttribute()
  {
    if (isset($this->attributes['tenant_id'])) {
      $tenant = Tenant::select('name')->find($this->attributes['tenant_id']);
      return $tenant['name'];
    };
  }

  public function getTypeLabelAttribute()
  {
    if (isset($this->attributes['type'])) {
      if ($this->attributes['type'] == 1) {
        return "收入";
      } else if ($this->attributes['type'] == 2) {
        return "支出";
      }
    }
  }
  public function getcNameAttribute()
  {
    if (isset($this->attributes['c_uid'])) {
      $user = \App\Models\User::select('realname')->find($this->attributes['c_uid']);
      return $user['realname'];
    }
  }
  public function getBankNameAttribute()
  {
    if (isset($this->attributes['bank_id'])) {
      $bank =  BankAccount::select('account_name')->find($this->attributes['bank_id']);
      return $bank['account_name'];
    }
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
