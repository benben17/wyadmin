<?php

namespace App\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
/**
 * 房间model
 *
 */
class BuildingRoom extends Model
{

	use SoftDeletes;

	protected $table = 'bse_building_room';
	protected $fillable = ['company_id','proj_id','build_id','floor_id','room_no','room_state','room_measure_area','room_trim_state','room_price','room_type','room_tags','channel_state','rentable_date','room_area','remark','is_vaild','c_uid','u_uid'];
	protected $hidden = ['deleted_at',"company_id",'c_uid','u_uid','created_at','updated_at'];


	public function project(){
		return $this->hasOne(Project::class,'id','proj_id');
	}
	public function building(){
		return $this->hasOne(Building::class,'id','build_id');
	}
	public function floor(){
		return $this->hasOne(BuildingFloor::class,'id','floor_id');
	}

	public function addAll(Array $data)
  {
    $res = DB::table($this->getTable())->insert($data);
    return $res;
  }

	protected static function boot()
	{
		parent::boot();
		static::addGlobalScope(new CompanyScope);
	}

}