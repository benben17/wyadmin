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

  protected $appends = ['status_label'];
  public function getStatusLabelAttribute()
  {
    return $this->attributes['status'] ? "已开" : "未开";
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
