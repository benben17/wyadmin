<?php

namespace App\Api\Models;

use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 */
class Project extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  use SoftDeletes;
  protected $table = 'bse_project';

  protected $hidden = ['deleted_at', 'company_id', 'c_uid', 'u_uid', 'updated_at'];
  protected $fillable = ['proj_type', 'company_id', 'proj_name', 'proj_addr', 'proj_logo', 'u_uid', 'c_uid', 'proj_province_id', 'proj_city_id', 'proj_district_id', 'proj_province', 'proj_city', 'proj_district', 'proj_occupy', 'proj_buildarea', 'proj_usablearea', 'proj_far', 'proj_pic', 'is_valid', 'water_price', 'electric_price', 'operate_entity', 'bill_instruction'];

  protected $appends = ['valid_label', 'proj_pic_full'];


  public function getValidLabelAttribute()
  {
    if (isset($this->attributes['is_valid'])) {
      return $this->attributes['is_valid'] === 1 ? "启用" : "禁用";
    }
  }
  public function getProjPicFullAttribute()
  {
    if (isset($this->attributes['proj_pic'])) {
      $pics = str2Array($this->attributes['proj_pic']);
      return array_map(function ($pic) {
        return getOssUrl($pic);
      }, $pics);
    } else {
      return [];
    }
  }
  public function projRoom()
  {
    return $this->hasMany(BuildingRoom::class, 'proj_id', 'id');
  }

  public function building()
  {
    return $this->hasMany(Building::class, 'proj_id', 'id');
  }

  public function builds()
  {
    return $this->hasMany(Building::class, 'proj_id', 'id');
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
