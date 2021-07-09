<?php

namespace App\Api\Models\Energy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use App\Services\CompanyServices;

class Meter extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */
  var $parentType = 3;
  protected $table = 'bse_meter';
  protected $fillable = [];
  protected $hidden = ['deleted_at', "company_id", 'updated_at'];


  protected $appends = ['proj_name', 'is_virtual', 'meter_type', 'master_slave'];

  public function getProjNameAttribute()
  {
    $projId = $this->attributes['proj_id'];
    $companyService = new CompanyServices;
    return $companyService->getProjName($projId);
  }
  public function getIsVirtualAttribute()
  {
    $parentId = $this->attributes['parent_id'];
    if ($parentId == 0) {
      return '实体表';
    } else {
      return '虚拟表';
    }
  }
  public function getMeterTypeAttribute()
  {
    if ($this->attributes['type'] == 1) {
      return '水表';
    } else {
      return '电表';
    }
  }
  public function getMasterSlaveAttribute()
  {
    if ($this->attributes['master_slave'] == 1) {
      return '总表';
    } else {
      return '子表';
    }
  }

  public function remark()
  {
    return $this->hasMany('App\Api\Models\Common\Remark', 'parent_id', 'id')
      ->where('parent_type', $this->parentType);
  }

  public function meterLog()
  {
    return $this->hasMany(MeterLog::class, 'meter_id', 'id');
  }

  public function virtualMeter()
  {
    return $this->hasMany(Meter::class, 'parent_id', 'id');
  }


  public function meterRecord()
  {
    return $this->hasMany(MeterRecord::class, 'meter_id', 'id');
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
