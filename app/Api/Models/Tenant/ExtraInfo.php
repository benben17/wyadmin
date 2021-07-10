<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

class ExtraInfo extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  protected $table = 'bse_tenant_extra';
  protected $fillable = ['tenant_id', 'demand_area', 'expected_price', 'trim_state', 'recommend_room_id', 'recommend_room', 'purpose_room', 'purpose_price', 'purpose_term_lease', 'purpose_free_time', 'current_proj', 'current_addr', 'current_area', 'current_price', 'c_uid', 'u_uid'];
  protected $hidden = ['deleted_at', 'c_uid', 'u_uid', 'updated_at', 'created_at'];

  public function tenant()
  {
    return $this->hasOne(Tenant::class, 'id', 'cus_id');
  }
}
