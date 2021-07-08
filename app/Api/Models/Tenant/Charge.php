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

  protected $appends = ['tenant_name', 'audit_label', 'type_label', 'c_name'];
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
    $feeName = getFeeNameById($this->attributes['charge_type']);
    return $feeName['fee_name'];
  }
  public function getcNameAttribute()
  {
    $c_uid = $this->attributes['c_uid'];
    $user = \App\Models\User::select('realname')->find($c_uid);
    return $user['realname'];
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
