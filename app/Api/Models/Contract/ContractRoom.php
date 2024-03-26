<?php

namespace App\Api\Models\Contract;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;

class ContractRoom extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  protected $table = 'bse_contract_room';

  protected $fillable = ['contract_id', 'build_id', 'floor_id', 'room_id', 'room_no', 'room_area'];
  protected $hidden = ['deleted_at', 'updated_at', 'created_at'];

  protected  $appends = ['contract_no'];
  public function contract()
  {
    return $this->belongsTo(Contract::class, 'contract_id', 'id');
  }

  public function getContractNoAttribute()
  {
    if (isset($this->attributes['contract_id'])) {
      $contractId = $this->attributes['contract_id'];
      return Contract::select('contract_no')->find($contractId)['contract_no'];
    }
  }

  public function addAll(array $data)
  {
    // $data['created_at'] =  now();
    $res = DB::table($this->getTable())->insert($data);
    return $res;
  }
}
