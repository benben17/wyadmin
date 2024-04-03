<?php

namespace App\Api\Models;

use App\Enums\AppEnum;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 房间model
 *
 */
class BuildingRoom extends Model
{

	use SoftDeletes;

	protected $table = 'bse_building_room';
	protected $fillable = ['company_id', 'proj_id', 'build_id', 'floor_id', 'room_no', 'room_state', 'room_measure_area', 'room_trim_state', 'room_price', 'room_type', 'room_tags', 'channel_state', 'rentable_date', 'room_area', 'remark', 'is_vaild', 'c_uid', 'u_uid'];
	protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid', 'created_at', 'updated_at'];
	protected $appends = ['price_label', 'pic_full'];

	public function project()
	{
		return $this->hasOne(Project::class, 'id', 'proj_id');
	}
	public function building()
	{
		return $this->hasOne(Building::class, 'id', 'build_id');
	}
	public function floor()
	{
		return $this->hasOne(BuildingFloor::class, 'id', 'floor_id');
	}
	public function getPriceLabelAttribute()
	{
		if (isset($this->attributes['price_type'])) {
			switch ($this->attributes['price_type']) {
				case 1:
					return AppEnum::dayPrice;
					break;
				case 2:
					return AppEnum::monthPrice;
					break;
			}
		};
	}
	public function getPicFullAttribute()
	{
		if (isset($this->attributes['pics'])) {
			$pics = str2Array($this->attributes['pics']);
			return array_map(function ($pic) {
				return getOssUrl($pic);
			}, $pics);
		} else {
			return [];
		}
	}
	public function addAll(array $data)
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
