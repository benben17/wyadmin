<?php

namespace App\Api\Models\Equipment;

use App\Api\Scopes\CompanyScope;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

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
  protected $appends = ['maintain_period_label', 'proj_name', 'third_party_label', 'is_valid_label'];

  public function maintain()
  {
    return $this->hasMany(EquipmentMaintain::class, 'equipment_id', 'id');
  }

  public function maintainPlan()
  {
    return $this->hasMany(EquipmentPlan::class, 'equipment_id', 'id');
  }

  public function getThirdPartyLabelAttribute()
  {
    $thirdParty = $this->attributes['third_party'] ?? 1;
    return $thirdParty == 1 ?  "本单位维护" : "第三方维护";
  }

  public function getIsValidLabelAttribute()
  {
    $isValid = $this->attributes['is_valid'] ?? 1;
    return $isValid == 1 ?  "有效" : "无效";
  }
  public function getProjNameAttribute()
  {
    $projId = $this->attributes['proj_id'] ?? 0;
    $proj = \App\Api\Models\Project::select('proj_name')->find($projId);
    return $proj['proj_name'] ?? "";
  }

  public function getMaintainPeriodLabelAttribute()
  {

    if (isset($this->attributes['maintain_period'])) {
      $value =  $this->attributes['maintain_period'];
      switch ($value) {
        case '1':
          return "每周";
          break;
        case '2':
          return "每月";
          break;
        case '3':
          return '每季度';
          break;
        case '4':
          return '每半年';
          break;
        case '5':
          return '每年';
          break;
      }
    }
  }
  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
