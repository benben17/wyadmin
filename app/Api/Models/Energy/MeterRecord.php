<?php

namespace App\Api\Models\Energy;

use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeterRecord extends Model
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
    return  $this->attributes['audit_status'] ? "已审核" : "未审核";
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
