<?php

namespace App\Api\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

use App\Api\Models\User;
class CustomerFollow extends Model
{
 /**
  * 关联到模型的数据表
  *
  * @var string
  */
  protected $table = 'bse_cus_follow';
  protected $hidden = ['deleted_at',"company_id",'c_uid','u_uid','updated_at'];
  protected $fillable = ['company_id','cus_id','cus_follow_type','cus_state','cus_follow_record','cus_follow_time','cus_feedback','cus_contact_id','cus_loss_reason','cus_contact_user','follow_usrename','c_uid','u_uid','remark'];

  public function customer(){
  	return $this->hasOne(Customer::class,'id','cus_id');
  }
  public function user(){
  	return $this->hasOne(User::class ,'id','c_uid');
  }

  //跟进类型：1来访，2 电话，3微信  ，4QQ、5其他

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}