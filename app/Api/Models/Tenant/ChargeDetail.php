<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;

class ChargeDetail extends Model
{
  protected $table = 'bse_charge_detail';
  protected $fillable = [];
  protected $hidden = ['deleted_at', 'company_id'];
  protected $appends = ['type_label'];

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
