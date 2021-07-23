<?php

namespace App\Api\Models\Bill;

use App\Api\Models\Contract\BillRule;
use App\Api\Models\Contract\Contract;
use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;


/**
 * 分摊规则
 */
class TenantShareRule extends Model
{

  use SoftDeletes;
  protected $table = 'bse_tenant_share_rule';
  protected $fillable = [];
  protected $hidden = ['deleted_at', 'company_id', 'updated_at', 'created_at', 'u_uid'];
  protected $appends = ['fee_type_label', 'pay_method', 'month_amt'];

  public function getFeeTypeLabelAttribute()
  {
    if (isset($this->attributes['fee_type'])) {
      $fee = getFeeNameById($this->attributes['fee_type']);
      return $fee['fee_name'];
    }
  }

  public function getPayMethodAttribute()
  {
    if (isset($this->attributes['bill_rule_id'])) {
      $fee = BillRule::find($this->attributes['bill_rule_id']);
      return $fee['pay_method'];
    }
  }
  public function getMonthAmtAttribute()
  {
    if (isset($this->attributes['bill_rule_id'])) {
      $fee = BillRule::find($this->attributes['bill_rule_id']);
      return $fee['month_amt'];
    }
  }
  /** 账单详细 */
  public function contract()
  {
    return $this->belongsTo(Contract::class, 'id', 'contract_id');
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
