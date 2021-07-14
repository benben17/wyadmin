<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;

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
  protected $fillable = ['proj_type', 'company_id', 'proj_name', 'proj_addr', 'proj_logo', 'u_uid', 'c_uid', 'proj_province_id', 'proj_city_id', 'proj_district_id', 'proj_province', 'proj_city', 'proj_district', 'proj_occupy', 'proj_buildarea', 'proj_usablearea', 'proj_far', 'proj_pic', 'is_vaild', 'water_price', 'electric_price'];
  // protected $fillable=['*'];
  //
  //
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
