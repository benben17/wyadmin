<?php

namespace App\Api\Models\Bill;

use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;
use App\Api\Models\Contract\Contract;
use Illuminate\Database\Eloquent\SoftDeletes;


/**
 * 分摊规则
 */
class TenantShareFee extends BaseModel
{

  use SoftDeletes;
  protected $table = 'bse_tenant_share_fee';
  protected $fillable = [];
  protected $hidden = ['deleted_at', 'company_id', 'updated_at', 'created_at', 'u_uid'];
  protected $appends = [];


  protected $casts = [
    'fee_list' => 'json',
  ];

  // public function getShareNumLabelAttribute()
  // {
  //   if (isset($this->attributes['share_type'])) {
  //     switch ($this->attributes['share_type']) {
  //       case '1':
  //         return AppEnum::shareRate;
  //         break;
  //       case '2':
  //         return AppEnum::shareAmt;
  //         break;
  //       case '3':
  //         return AppEnum::shareArea;
  //         break;
  //     }
  //   }
  // }

  // public function getShareTypeLabelAttribute()
  // {
  //   $shareType = $this->attributes['share_type'] ?? 0;

  //   switch ($shareType) {
  //     case '1':
  //       return "比例分摊";
  //       break;
  //     case '2':
  //       return "固定金额";
  //       break;
  //     case '3':
  //       return "固定面积";
  //       break;
  //   }
  // }

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
