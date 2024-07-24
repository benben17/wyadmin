<?php

namespace App\Api\Models\Equipment;

use App\Enums\AppEnum;
use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;;

/**
 *  设备维护模型
 */
class EquipmentMaintain extends BaseModel
{

  /**
   * 关联到模型的数据表
   * @var string
   */
  var $parentType = AppEnum::EquipmentMaintain;  // 维护时使用
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

  public function equipment()
  {
    return $this->belongsTo(Equipment::class, 'equipment_id', 'id');
  }


  public function attachment()
  {
    return $this->hasMany('App\Api\Models\Common\Attachment', 'parent_id', 'id')
      ->where('parent_type', $this->parentType);
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
