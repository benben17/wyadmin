<?php

namespace App\Api\Models\Energy;

use App\Enums\RemarkType;
use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;
use App\Services\CompanyServices;
use App\Api\Models\Common\BseRemark;
use App\Api\Models\Contract\ContractRoom;

class Meter extends BaseModel
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */
  var $parentType = RemarkType::Meter;
  protected $table = 'bse_meter';
  protected $fillable = [];
  protected $hidden = ['deleted_at', "company_id", 'updated_at'];


  protected $appends = ['proj_name', 'is_virtual', 'meter_type', 'master_slave_label'];

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
    if (isset($this->attributes['type'])) {

      return  $this->attributes['type'] == 1 ? '水表' : '电表';
    }
  }
  public function getMasterSlaveLabelAttribute()
  {
    if (isset($this->attributes['master_slave'])) {
      return $this->attributes['master_slave'] == 1 ? '总表' : '子表';
    }
  }

  public function remark()
  {
    return $this->hasMany(BseRemark::class, 'parent_id', 'id')
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


  public function initRecord()
  {
    return $this->hasOne(MeterRecord::class, 'meter_id', 'id')->where('status', 1);
  }

  public function meterRecord()
  {
    return $this->hasMany(MeterRecord::class, 'meter_id', 'id');
  }

  public function contractRoom()
  {
    return $this->belongsTo(ContractRoom::class, 'room_id', 'room_id');
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
