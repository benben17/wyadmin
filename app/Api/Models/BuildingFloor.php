<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class BuildingFloor extends Model
{
	/**
    * 关联到模型的数据表  楼层 管理
    *
    * @var string
    */

  // use SoftDeletes;

  protected static function boot()
  {
	parent::boot();
	static::addGlobalScope(new CompanyScope);
  }

  protected $table = 'bse_building_floor';
  protected $fillable = ['company_id','proj_id','build_id','floor_no','remark','is_vaild','c_uid','u_uid'];
  protected $hidden = ['deleted_at',"company_id",'c_uid','u_uid','proj_id','created_at','updated_at'];

  public function addAll(Array $data)
  {
    $res = DB::table($this->getTable())->insert($data);
    return $res;
  }

  public function building(){
  	return $this->hasOne(Building::class,'id','build_id');
  }
  public function project(){
    return $this->hasOne(Building::class,'id','proj_id');
  }
  public function floorRoom(){
    return $this->hasMany(BuildingRoom::class,'floor_id','id');
  }
  // protected static function boot()
  // {
  //   parent::boot();
  //   static::addGlobalScope(new CompanyScope);
  // }
}