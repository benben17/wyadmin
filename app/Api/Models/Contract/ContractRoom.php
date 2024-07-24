<?php

namespace App\Api\Models\Contract;

use App\Models\BaseModel;
use Illuminate\Support\Facades\DB;


class ContractRoom extends BaseModel
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
}
