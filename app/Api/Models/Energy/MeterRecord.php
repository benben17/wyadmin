<?php

namespace App\Api\Models\Energy;

use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;


class MeterRecord extends BaseModel
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  protected $table = 'bse_meter_record';
  protected $fillable = [];
  protected $hidden = ['deleted_at', "company_id", 'updated_at'];
  protected $appends = ['audit_status_label'];

  // public function meter()
  // {
  //   return $this->hasOne(Meter::class, 'id', 'meter_id');
  // }

  public function meter()
  {
    return $this->belongsTo(Meter::class, 'meter_id', 'id');
  }

  public function getAuditStatusLabelAttribute()
  {
    $auditStatus = $this->attributes['audit_status'] ?? 0;
    return $auditStatus ? "已审核" : "未审核";
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
