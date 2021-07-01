<?php

namespace App\Api\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

use App\Api\Models\Customer\Customer;

class CustomerRemind extends Model
{
 /**
  * 关联到模型的数据表
  *
  * @var string
  */
  protected $table = 'bse_cus_remind';
  protected $hidden = ['deleted_at',"company_id",'c_uid','u_uid','updated_at','created_at'];

  protected $fillable = ['company_id','cus_id','cus_name','cus_remind_date','cus_remind_content','c_uid','u_uid','remark'];


  public function customer()
  {
    return $this->hasOne(Customer::class,'id','cus_id');
  }


  protected static function boot()
  {
  	parent::boot();
  	static::addGlobalScope(new CompanyScope);
  }
}