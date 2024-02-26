<?php

namespace App\Api\Models\Equipment;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

/**
 *  设备
 */
class Equipment extends Model
{

  /**
   * 关联到模型的数据表
   * @var string
   */

  protected $table = 'bse_equipment';
  protected $fillable = [];
  protected $hidden = [];

  public function maintain()
  {
    return $this->hasMany(EquipmentMaintain::class, 'equipment_id', 'id');
  }

  public function maintainPlan()
  {
    return $this->hasMany(EquipmentPlan::class, 'equipment_id', 'id');
  }

  protected $appends = ['proj_name'];

  public function getProjNameAttribute()
  {
    $projId = $this->attributes['proj_id'];
    $proj = \App\Api\Models\Project::select('proj_name')->find($projId);
    return $proj['proj_name'];
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
