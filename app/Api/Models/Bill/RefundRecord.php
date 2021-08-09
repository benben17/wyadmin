<?php

namespace App\Api\Models\Bill;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefundRecord extends Model
{

  use SoftDeletes;
  protected $table = 'bse_tenant_refund_record';
  protected $fillable = [];
  protected $hidden = ['company_id', 'deleted_at', 'updated_at'];
  protected $appends = [];

  // public function getBankNameAttribute()
  // {
  //   if (isset($this->attributes['bank_id'])) {
  //     $bank =  BankAccount::select('account_name')->find($this->attributes['bank_id']);
  //     return $bank['account_name'];
  //   }
  // }
  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
