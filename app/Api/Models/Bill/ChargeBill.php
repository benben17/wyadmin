<?php

namespace App\Api\Models\Bill;

use App\Api\Models\Company\BankAccount;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use App\Enums\AppEnum;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeBill extends Model
{
  use SoftDeletes;

  protected $table = 'bse_charge_bill';
  protected $fillable = [];
  protected $hidden = ['company_id', 'deleted_at', 'updated_at'];
  protected $appends = ['tenant_name', 'c_user', 'type_label', 'bank_name', 'status_label', 'category_label'];

  public function getTenantNameAttribute()
  {
    return $this->belongsTo(Tenant::class, 'tenant_id')->value('name');
  }

  public function getStatusLabelAttribute()
  {
    if (isset($this->attributes['status'])) {
      return $this->getStatusLabels()[$this->attributes['status']] ?? '';
    }
  }

  public function getTypeLabelAttribute()
  {
    if (isset($this->attributes['type'])) {
      return $this->getTypeLabels()[$this->attributes['type']] ?? '';
    }
  }

  public function getCategoryLabelAttribute()
  {
    if (isset($this->attributes['category'])) {
      return $this->getCategoryLabels()[$this->attributes['category']] ?? '';
    }
  }

  public function getcUserAttribute()
  {

    return $this->belongsTo(\App\Models\User::class, 'c_uid')->value('realname');
  }

  public function getBankNameAttribute()
  {
    return $this->belongsTo(BankAccount::class, 'bank_id')->value('account_name');
  }

  // 核销记录
  public function chargeBillRecord()
  {
    return $this->hasMany(ChargeBillRecord::class, 'charge_id', 'id');
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }


  protected function getStatusLabels()
  {
    return [
      0 => "未核销",
      1 => "已核销",
      3 => "已废弃",
    ];
  }

  protected function getTypeLabels()
  {
    return [
      1 => "收入",
      2 => "支出",
    ];
  }

  protected function getCategoryLabels()
  {
    return [
      1 => "费用",
      2 => "押金",
    ];
  }
}
