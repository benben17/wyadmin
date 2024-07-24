<?php

namespace App\Api\Models\Equipment;

use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;


/**
 *  巡检记录
 */
class InspectionRecord extends BaseModel
{

  /**
   * 关联到模型的数据表
   * @var string
   */

  protected $table = 'bse_inspection_record';
  protected $fillable = [];
  protected $hidden = ["company_id"];


  protected $appends = ['proj_name', 'is_unusual_label', "pic_full"];


  public function getProjNameAttribute()
  {
    $projId = $this->attributes['proj_id'];
    $proj = \App\Api\Models\Project::select('proj_name')->find($projId);
    return $proj['proj_name'];
  }

  public function getIsUnusualLabelAttribute()
  {
    $unusual = $this->attributes['is_unusual'] ?? 0;
    return  $unusual == 1  ? "正常" : "异常";
  }

  public function getPicFullAttribute()
  {
    $pic = $this->attributes['pic'];
    return picFullPath($pic);
  }

  public function inspection()
  {
    return $this->hasOne(Inspection::class, "id", "inspection_id");
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
