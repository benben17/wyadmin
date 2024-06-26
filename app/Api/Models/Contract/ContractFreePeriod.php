<?php

namespace App\Api\Models\Contract;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;

/**
 * 免租类型 免租时间
 */
class ContractFreePeriod extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */
  use SoftDeletes;
  protected $table = 'bse_contract_free_period';

  protected $fillable = ['cus_id', 'contract_id', 'free_type', 'start_date', 'end_date', 'free_num', 'is_vaild', 'remark'];
  protected $hidden = ['deleted_at', 'company_id', 'u_uid'];


  public function addAll(array $data)
  {
    $res = DB::table($this->getTable())->insert($data);
    return $res;
  }
}
