<?php

namespace App\Api\Models\Company;

use App\Api\Models\Bill\TenantBillDetail;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

class FeeType extends Model
{
  /**
   * 费用类型
   *
   * @var string
   */

  protected $table = 'bse_fee_type';

  protected $fillable = [];
  protected $hidden = ['deleted_at', 'created_at'];

  protected $appends = ['type_label'];
  public function getTypeLabelAttribute()
  {
    if (isset($this->attributes['type'])) {
      $type = $this->attributes['type'];
      if ($type == 1) {
        return '费用';
      } else if ($type == 2) {
        return '押金';
      } else if ($type == 3) {
        return '日常费用';
      }
    }
  }
  public function feeStat()
  {
    return $this->hasOne(TenantBillDetail::class, 'fee_type', 'id');
  }
}
