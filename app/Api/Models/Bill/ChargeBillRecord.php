<?php

namespace App\Api\Models\Bill;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;

class ChargeBillRecord extends Model
{
  protected $table = 'bse_charge_bill_record';
  protected $fillable = [];
  protected $hidden = ['deleted_at', 'company_id'];
  protected $appends = ['type_label', 'fee_type_label'];

  public function getFeeTypeLabelAttribute()
  {
    $fee = getFeeNameById($this->attributes['fee_type']);
    return $fee['fee_name'];
  }
  public function getTypeLabelAttribute()
  {
    $type = $this->attributes['type'];
    switch ($type) {
      case '1':
        return "充值";
        break;
      case '2':
        return "抵扣";
        break;
    }
  }
}
