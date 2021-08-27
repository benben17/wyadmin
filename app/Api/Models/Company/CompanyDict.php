<?php

namespace App\Api\Models\Company;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDict extends Model
{
	/**
	 * 关联到模型的数据表
	 *
	 * @var string
	 */
	// use SoftDeletes;
	protected $table = 'bse_dict';

	protected $hidden = ['created_at', 'updated_at', 'c_uid', 'u_uid'];

	protected $fillable = ['company_id', 'dict_key', 'dict_value', 'c_uid', 'u_uid', 'is_vaild'];
	protected $appends = ['is_vaild_label'];

	public function getIsVaildLabelAttribute()
	{
		if (isset($this->attributes['fee_type'])) {
			return $this->attributes['fee_type'] ? "启用" : "禁用";
		}
	}
	public function extra()
	{
		return $this->hasMany('App\Api\Models\Channel\Channel', 'channel_type', 'dict_value');
	}
}
