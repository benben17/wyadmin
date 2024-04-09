<?php

namespace App\Api\Models\Contract;

use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 租户账单规则
 */
class BillRule extends Model
{
  use SoftDeletes;
  protected $table = 'bse_contract_bill_rule';
  protected $fillable = [
    'c_uid', 'u_uid', 'tenant_id', 'contract_id', 'type', 'fee_type', 'unit_price', 'price_type',
    'start_date', 'bill_type', 'end_date', 'charge_date', 'area_num', 'lease_term', 'pay_method',
    'bill_day', 'amount', 'month_amt', 'ahead_pay_month', 'increase_show', 'increase_rate',
    'increase_date', 'increase_start_period', 'unit_price_label', 'remark', 'is_valid', 'created_at'
  ];
  protected $hidden = ['deleted_at'];
  protected $appends = ['fee_type_label'];

  public function getFeeTypeLabelAttribute()
  {
    $feeName = getFeeNameById($this->attributes['fee_type']);
    return $feeName['fee_name'];
  }

  public function addAll($data)
  {
    $res = DB::table($this->getTable())->insert($data);
    return $res;
  }
}
