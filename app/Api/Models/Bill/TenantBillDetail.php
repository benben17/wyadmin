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
  protected $hidden = ['deleted_at', 'updated_at'];

  protected $appends = ['fee_type_label', 'c_user', 'proj_name', 'status_label', 'unreceive_amount'];

  public function getFeeTypeLabelAttribute()
  {
    if (isset($this->attributes['fee_type'])) {
      $fee = getFeeNameById($this->attributes['fee_type']);
      return $fee['fee_name'];
    }
  }
  public function getCUserAttribute()
  {
    if (isset($this->attributes['c_uid'])) {
      $user = getUserByUid($this->attributes['c_uid']);
      return $user['realname'];
    }
  }
  public function getUnreceiveAmountAttribute()
  {
    return numFormat($this->attributes['amount'] - $this->attributes['receive_amount']);
  }
  public function getProjNameAttribute()
  {
    if (isset($this->attributes['proj_id'])) {
      $proj = getProjById($this->attributes['proj_id']);
      return $proj['proj_name'];
    }
  }
  public function getStatusLabelAttribute()
  {
    if (isset($this->attributes['status'])) {
      return $this->attributes['status'] ?  '已结清' : "未结清";
    }
  }

  public function chargeBillRecord()
  {
    return $this->hasMany(ChargeBillRecord::class, 'bill_detail_id', 'id');
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
