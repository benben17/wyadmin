<?php

namespace App\Api\Models\Contract;

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

  protected $fillable = ['contract_id', 'type', 'amount', 'charge_date', 'start_date', 'end_date', 'bill_date', 'price', 'remark'];
  protected $hidden = ['deleted_at', 'updated_at'];

  public function addAll(array $data)
  {
    $res = DB::table($this->getTable())->insert($data);
    return $res;
  }

  public function contract()
  {
    return $this->hasOne('\App\Api\Models\Contract\Contract', 'id', 'contract_id');
  }

  // protected static function boot()
  // {
  //   parent::boot();
  //   static::addGlobalScope(new CompanyScope);
  // }
}
