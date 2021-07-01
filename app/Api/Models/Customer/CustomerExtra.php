<?php

namespace App\Api\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

class CustomerExtra extends Model
{
 /**
  * 关联到模型的数据表
  *
  * @var string
  */

  protected $table = 'bse_customer_extra';
  protected $fillable = ['cus_id','demand_area','expected_price','trim_state','recommend_room_id','recommend_room','purpose_room','purpose_price','purpose_term_lease','purpose_free_time','current_proj','current_addr','current_area','current_price','c_uid','u_uid'];
  protected $hidden = ['deleted_at','c_uid','u_uid','updated_at','created_at'];

  public function customer()
  {
    return $this->hasOne(Customer::class,'id','cus_id');
  }

}
