<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * 租户账单规则
 */
class BillRule extends Model
{
  use SoftDeletes;
  protected $table = 'bse_tenant_bill_rule';
  protected $fillable = [];
  protected $hidden = ['deleted_at'];

  public function addAll(array $data)
  {
    $res = DB::table($this->getTable())->insert($data);
    return $res;
  }
}
