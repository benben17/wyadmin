<?php

namespace App\Api\Models\Bill;

use App\Api\Models\Contract\BillRule;
use App\Api\Models\Contract\Contract;
use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use App\Enums\AppEnum;
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
  protected $appends = ['fee_type_label', 'pay_method', 'month_amt', 'tenant_name', 'share_type_label', 'share_num_label'];

  public function getFeeTypeLabelAttribute()
  {
    if (isset($this->attributes['fee_type'])) {
      $fee = getFeeNameById($this->attributes['fee_type']);
      return $fee['fee_name'];
    }
  }
  public function getTenantNameAttribute()
  {
    if (isset($this->attributes['tenant_id'])) {
      return getTenantNameById($this->attributes['tenant_id']);
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

  public function getShareNumLabelAttribute()
  {
    if (isset($this->attributes['share_type'])) {
      switch ($this->attributes['share_type']) {
        case '1':
          return AppEnum::shareRate;
          break;
        case '2':
          return AppEnum::shareAmt;
          break;
        case '3':
          return AppEnum::shareArea;
          break;
      }
    }
  }

  public function getShareTypeLabelAttribute()
  {
    $sharetype = $this->attributes['share_type'];
    if ($sharetype == 1) {
      return '比例分摊';
    } else if ($sharetype == 2) {
      return '固定金额';
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
