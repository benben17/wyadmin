<?php

namespace App\Api\Models\Contract;

use App\Api\Models\Bill\TenantBillDetail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;

/**
 * 合同账单
 */
class ContractBill extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  protected $table = 'bse_contract_bill';

  protected $fillable = ['contract_id', 'type', 'amount', 'charge_date', 'start_date', 'end_date', 'bill_date', 'price', 'is_sync', 'remark', 'fee_type', 'c_uid', 'is_sync', 'unit_price_label', 'tenant_id'];
  protected $hidden = ['deleted_at', 'updated_at', 'company_id', 'c_uid', 'u_uid'];
  protected $appends = ['fee_type_label', 'type_label'];

  public function getFeeTypeLabelAttribute()
  {
    if (isset($this->attributes['fee_type'])) {
      $feeName = getFeeNameById($this->attributes['fee_type']);
      return $feeName['fee_name'];
    }
  }
  public function getTypeLabelAttribute()
  {
    if (isset($this->attributes['type'])) {
      if ($this->attributes['type'] == 1 || $this->attributes['type'] == 3) {
        return "费用";
      } else if ($this->attributes['type'] == 2) {
        return "押金";
      } else {
        return "其他费用";
      }
    }
  }


  public function tenantBillDetail()
  {
    return $this->hasOne(TenantBillDetail::class, 'contract_bill_id', 'id');
  }

  public function addAll($data)
  {
    $res = DB::table($this->getTable())->insert($data);
    return $res;
  }

  public function contract()
  {
    return $this->hasOne(Contract::class, 'id', 'contract_id');
  }

  // protected static function boot()
  // {
  //   parent::boot();
  //   static::addGlobalScope(new CompanyScope);
  // }
}
