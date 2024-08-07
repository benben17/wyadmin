<?php

namespace App\Api\Models\Company;

use App\Models\BaseModel;

/**
 *  
 * Desc 字典模型
 * @package App\Api\Models\Company
 */
class CompanyDict extends BaseModel
{

	// use SoftDeletes;
	protected $table = 'bse_dict';

	protected $hidden = ['created_at', 'updated_at', 'u_uid'];

	protected $fillable = ['company_id', 'dict_key', 'dict_value', 'c_uid', 'u_uid', 'is_vaild'];
	protected $appends = ['is_vaild_label', 'c_user'];

	public function getIsVaildLabelAttribute()
	{
		if (isset($this->attributes['is_vaild'])) {
			return $this->attributes['is_vaild'] ? "启用" : "禁用";
		}
	}
	public function getCUserAttribute()
	{
		$uid = $this->attributes['c_uid'] ?? 0;
		return $uid > 0 ? getUserByUid($uid)['realname'] : "admin";
	}
}
