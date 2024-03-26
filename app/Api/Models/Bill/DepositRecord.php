<?php

namespace App\Api\Models\Bill;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 押金流水记录
 *
 * @Author leezhua
 * @DateTime 2024-03-06
 */
class DepositRecord extends Model
{

  use SoftDeletes;
  protected $table = 'bse_tenant_deposit_record';
  protected $fillable = [];
  protected $hidden = ['company_id', 'deleted_at', 'updated_at'];
  protected $appends = ['type_label'];

  public function getTypeLabelAttribute()
  {
    if (isset($this->attributes['type'])) {
      return $this->getTypeLabel($this->attributes['type']);
    } else {
      return "";
    }
  }

  public function billDetail()
  {
    return $this->belongsTo(TenantBillDetail::class, "bill_detail_id", "id");
  }


  private function getTypeLabel(int $type)
  {
    $labels = [
      1 => "押金收款",
      2 => "转费用收入",
      3 => "押金退款",
    ];
    return $labels[$type]  ?? '';
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
