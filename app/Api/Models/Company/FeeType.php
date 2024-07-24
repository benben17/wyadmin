<?php

namespace App\Api\Models\Company;

use App\Models\BaseModel;
use App\Api\Models\Bill\TenantBillDetail;

class FeeType extends BaseModel
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
    $typeLabels = [
      1 => '费用',
      2 => '押金',
      3 => '日常费用',
    ];
    $type = $this->attributes['type'] ?? null;
    return $typeLabels[$type] ?? '';
  }
  public function feeStat()
  {
    return $this->hasOne(TenantBillDetail::class, 'fee_type', 'id');
  }
}
