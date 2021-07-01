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

	protected $hidden = ['created_at','company_id','updated_at','c_uid','u_uid','is_vaild'];

	protected $fillable = ['company_id','dict_key','dict_value','c_uid','u_uid','is_vaild'];

	public function extra()
	{
		return $this->hasMany('App\Api\Models\Channel\Channel','channel_type','dict_value');
	}

}
