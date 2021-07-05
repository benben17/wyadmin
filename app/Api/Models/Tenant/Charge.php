<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class Charge extends Model
{

  use SoftDeletes;
  protected $table = 'bse_charge';
  protected $fillable = [];
  protected $hidden = ['company_id', 'deleted_at'];

  protected $appends = ['tenant_name', 'audit_label', 'type_label'];
  public function getTenantNameAttribute()
  {
    $tenantId = $this->attributes['tenant_id'];
    $tenant = Tenant::select('name')->find($tenantId);
    return $tenant['name'];
  }
  public function getAuditLabelAttribute()
  {
    $auditStatus = $this->attributes['audit_status'];
    switch ($auditStatus) {
      case '1':
        return "待审核";
        break;
      case '2':
        return "已审核";
        break;
      case '3':
        return "拒绝";
        break;
    }
  }
  public function getTypeLabelAttribute()
  {
    $type = $this->attributes['charge_type'];
    switch ($type) {
      case '1':
        return "充值";
        break;
      case '2':
        return "抵扣";
        break;
    }
  }
  public function detail()
  {
    return $this->hasMany(ChargeDetail::class, 'charge_id', 'id');
  }
  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
