<?php

namespace App\Api\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;

class CustomerLog extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'bse_customer_log';
    protected $hidden = ['deleted_at',"company_id",'c_uid','u_uid','updated_at','created_at'];

	protected static function boot()
	{
		parent::boot();
		static::addGlobalScope(new CompanyScope);
	}
}
