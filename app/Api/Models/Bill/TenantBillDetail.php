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
  protected $hidden = ['company_id', 'deleted_at'];

  protected $appends = ['fee_name', 'c_user', 'proj_name'];

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
  public function getProjNameAttribute()
  {
    $proj = getProjById($this->attributes['proj_id']);
    return $proj['proj_name'];
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
