<?php

namespace App\Api\Models\Equipment;

use App\Api\Scopes\CompanyScope;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 *  设备维护模型
 */
class EquipmentMaintain extends Model
{

  /**
   * 关联到模型的数据表
   * @var string
   */

  protected $table = 'bse_equipment_maintain';
  protected $fillable = [];
  protected $hidden = ['company_id'];
  protected $appends = ['proj_name', 'maintain_period_label', 'pic_full'];


  public function getPicFullAttribute()
  {
    $pic = $this->attributes['pic'];
    if (empty($pic)) {
      return [];
    }
    return picFullPath($pic);
  }


  public function maintainPlan()
  {
    return $this->belongsTo(EquipmentPlan::class,  'plan_id', 'id');
  }


  public function getProjNameAttribute()
  {
    $projId = $this->attributes['proj_id'];
    $proj = \App\Api\Models\Project::select('proj_name')->find($projId);
    return $proj['proj_name'];
  }

  // 获取维护周期label
  public function getMaintainPeriodLabelAttribute()
  {
    if (!isset($this->attributes['equipment_id'])) {
      return "";
    }
    $equipment = Equipment::find($this->attributes['equipment_id']);
    return optional($equipment)->maintain_period_label ?? "";
  }


  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
