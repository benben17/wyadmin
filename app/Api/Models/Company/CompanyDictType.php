<?php

namespace App\Api\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDictType extends Model
{
	/**
	* 关联到模型的数据表
	*
	* @var string
	*/
	// use SoftDeletes;
	protected $table = 'bse_dict_type';

	protected $hidden = ['id','created_at','updated_at','deleted_at','c_uid','u_uid'];

	protected $fillable = ['dict_type','dict_value'];

	// public function scopeWhereCompanyId($query, $companyId)
	// {
	//     return $query->where('company_id', '=', $companyId);
	// }

}

