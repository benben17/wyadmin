<?php

namespace App\Api\Models\Bill;

use App\Api\Models\Company\BankAccount;
use App\Api\Models\Tenant\Invoice;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceRecord extends Model
{

  use SoftDeletes;
  protected $table = 'bse_invoice_record';
  protected $fillable = [];
  protected $hidden = ['company_id', 'deleted_at', 'updated_at'];
  protected $appends = ['status_label', 'c_user', 'tenant_name'];

  public function getStatusLabelAttribute()
  {
    if (isset($this->attributes['status'])) {
      $status = $this->attributes['status'];
      switch ($status) {
        case '0':
          return '未开';
          break;
        case '1':
          return "已开";
          break;
        case '2':
          return "作废";
          break;
      }
    }
  }
  public function getCUserAttribute()
  {
    if (isset($this->attributes['c_uid'])) {
      $user = getUserByUid($this->attributes['c_uid']);
      return $user['realname'];
    }
  }
  public function getTenantNameAttribute()
  {
    if (isset($this->attributes['tenant_id'])) {
      $tenant = Tenant::select('name')->find($this->attributes['tenant_id']);
      return $tenant['realname'];
    }
  }
  public function BillDetail()
  {
    return $this->hasMany(TenantBillDetail::class, 'invoice_id', 'id');
  }

  public function tenantInvoice()
  {
    return $this->belongsTo(Invoice::class, 'invoice_id', 'id');
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
