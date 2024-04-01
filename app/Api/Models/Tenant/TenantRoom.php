<?php

namespace App\Api\Models\Tenant;

use App\Api\Scopes\CompanyScope;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class TenantRoom extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  protected $table = 'bse_tenant_room';

  protected $fillable = ['tenant_id', 'build_id', 'floor_id', 'room_id', 'room_no', 'room_area'];
  protected $hidden = ['deleted_at', 'updated_at', 'created_at'];


  public function addAll(array $data)
  {
    return DB::table($this->getTable())->insert($data);
  }
}
