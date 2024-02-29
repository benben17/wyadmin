<?php

namespace App\Api\Models\Equipment;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *  设备 维护计划
 */
class EquipmentPlan extends Model
{

  /**
   * 关联到模型的数据表
   * @var string
   */
  use SoftDeletes;
  protected $table = 'bse_equipment_plan';
  protected $fillable = [];
  protected $hidden = [];

  public function maintain()
  {
    return $this->hasMany(EquipmentMaintain::class, 'plan_id', 'id');
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