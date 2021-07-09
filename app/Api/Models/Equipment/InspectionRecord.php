<?php

namespace App\Api\Models\Equipment;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

/**
 *  巡检记录
 */
class InspectionRecord extends Model
{

  /**
   * 关联到模型的数据表
   * @var string
   */

  protected $table = 'bse_inspection_record';
  protected $fillable = [];
  protected $hidden = ["company_id"];


  protected $appends = ['proj_name', 'is_unusual_label', 'major_label'];

  public function getProjNameAttribute()
  {
    $projId = $this->attributes['proj_id'];
    $proj = \App\Api\Models\Project::select('proj_name')->find($projId);
    return $proj['proj_name'];
  }

  public function getIsUnusualLabelAttribute()
  {
    if ($this->attributes['is_unusual'] == 1) {
      return "正常";
    } else {
      return "异常";
    }
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
