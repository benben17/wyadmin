<?php

namespace App\Api\Models;

use App\Models\BaseModel;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class Building extends BaseModel
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */


  use SoftDeletes;


  protected $table = 'bse_building';
  protected $fillable = ['company_id', 'proj_id', 'proj_name', 'build_type', 'build_no', 'build_floor_no', 'build_certificate', 'build_block', 'floor_height', 'build_area', 'build_date', 'remark', 'is_valid', 'c_uid', 'u_uid', 'build_usage', 'manager_area'];

  protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid', 'updated_at', 'created_at'];

  public function project()
  {
    return $this->hasOne(Project::class, 'id', 'proj_id');
  }

  public function floor()
  {
    return $this->hasMany(BuildingFloor::class, 'build_id', 'id');
  }

  public function children()
  {
    return $this->hasMany(BuildingFloor::class, 'build_id', 'id');
  }

  public function buildRoom()
  {
    return $this->hasMany(BuildingRoom::class, 'build_id', 'id');
  }

  public function Building()
  {
    return $this->hasMany(BuildingFloor::class, 'build_id', 'id');
  }




  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
