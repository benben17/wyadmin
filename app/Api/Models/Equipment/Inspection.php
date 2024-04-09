<?php

namespace App\Api\Models\Equipment;

use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *  巡检点
 */
class Inspection extends Model
{

  /**
   * 关联到模型的数据表
   * @var string
   */
  // use SoftDeletes;
  protected $table = 'bse_inspection';
  protected $fillable = [];
  protected $hidden = ['company_id', 'deleted_at'];


  protected $appends = ['proj_name', 'check_cycle_label', 'type_label', 'status_label'];

  public function getProjNameAttribute()
  {
    $projId = $this->attributes['proj_id'];
    $proj = \App\Api\Models\Project::select('proj_name')->find($projId);
    return $proj['proj_name'];
  }


  public function getCheckCycleLabelAttribute()
  {
    $check_id = $this->attributes['check_cycle'];
    $dict = \App\Api\Models\Company\CompanyDict::find($check_id);
    return $dict['dict_value'];
  }

  public function getTypeLabelAttribute()
  {
    if (isset($this->attributes['type'])) {
      if ($this->attributes['type'] == 1) {
        return "工程";
      } else {
        return "秩序";
      }
    }
  }

  public function getStatusLabelAttribute()
  {
    $status = $this->attributes['status'] ?? 1;
    return $status == 1 ? '正常' : '异常';
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
