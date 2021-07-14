<?php

namespace App\Api\Models\Bill;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 *  账单详细
 */
class TenantBillDetail extends Model
{
  use SoftDeletes;
  protected $table = 'bse_tenant_bill_detail';
  protected $fillable = [];
  protected $hidden = ['company_id', 'deleted_at', 'updated_at'];

  protected $appends = ['fee_name', 'c_user', 'proj_name', 'status_label', 'unreceive_amount'];

  public function getFeeNameAttribute()
  {
    $fee = getFeeNameById($this->attributes['fee_type']);
    return $fee['fee_name'];
  }
  public function getCUserAttribute()
  {
    $user = getUserByUid($this->attributes['c_uid']);
    return $user['realname'];
  }
  public function getUnreceiveAmountAttribute()
  {

    return numFormat($this->attributes['amount'] - $this->attributes['receive_amount']);
  }
  public function getProjNameAttribute()
  {
    $proj = getProjById($this->attributes['proj_id']);
    return $proj['proj_name'];
  }
  public function getStatusLabelAttribute()
  {
    return $this->attributes['status'] ?  '已结清' : "未结清";
  }


  public function receiveBill()
  {
    return $this->hasMany(ReceiveBill::class, 'bill_detail_id', 'id');
  }


  public function addAll($data)
  {
    $res = DB::table($this->getTable())->insert($data);
    return $res;
  }


  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
