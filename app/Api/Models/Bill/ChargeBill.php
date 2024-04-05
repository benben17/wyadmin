<?php

namespace App\Api\Models\Bill;

use App\Enums\AppEnum;
use App\Enums\ChargeEnum;
use App\Api\Scopes\CompanyScope;
use App\Api\Models\Tenant\Tenant;
use App\Api\Models\Company\BankAccount;
use Illuminate\Database\Eloquent\Model;
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
      $status = $this->attributes['status'] ?? "";
      // return ChargeEnum::getLabels()[$status] ?? '';
      return $this->getStatusLabels()[$status] ?? '';
    }
  }

  public function getTypeLabelAttribute()
  {
    if (isset($this->attributes['type'])) {
      return ChargeEnum::getTypeLabels()[$this->attributes['type']] ?? '';
    }
  }

  /**
   * 获取类别标签属性
   * 
   * @return string
   */
  public function getCategoryLabelAttribute()
  {
    if (isset($this->attributes['category'])) {
      return ChargeEnum::getCategoryLabels()[$this->attributes['category']] ?? '';
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

  // protected function getTypeLabels()
  // {
  //   return [
  //     1 => "收入",
  //     2 => "支出",
  //   ];
  // }

  // protected function getCategoryLabels()
  // {
  //   return [
  //     1 => "费用收入",
  //     2 => "押金转收入",
  //     3 => '退款',
  //   ];
  // }
}
