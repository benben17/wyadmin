<?php

namespace App\Api\Models\Bill;

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
  protected $hidden = ['deleted_at', 'company_id'];
  // protected $appends = ['is_print_label', 'status_label'];

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
