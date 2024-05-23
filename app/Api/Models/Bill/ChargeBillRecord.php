<?php

namespace App\Api\Models\Bill;

use App\Api\Scopes\CompanyScope;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 核销记录
 *
 * @Author leezhua
 * @DateTime 2024-03-06
 */
class ChargeBillRecord extends Model
{
  protected $table = 'bse_charge_bill_record';
  protected $fillable = [];
  protected $hidden = ['deleted_at', 'company_id', 'updated_at'];
  protected $appends = ['type_label', 'fee_type_label'];

  use SoftDeletes;

  public function billDetail()
  {
    return $this->belongsTo(TenantBillDetail::class, 'bill_detail_id', 'id');
  }
  public function charge()
  {
    return $this->belongsTo(ChargeBill::class, 'charge_id', 'id');
  }

  public function getFeeTypeLabelAttribute()
  {
    $fee = getFeeNameById($this->attributes['fee_type']);
    return $fee['fee_name'];
  }
  public function getTypeLabelAttribute()
  {
    $type = $this->attributes['type'] ?? 1;
    switch ($type) {
      case '1':
        return "收入";
        break;
      case '2':
        return "支出";
        break;
    }
  }
}
