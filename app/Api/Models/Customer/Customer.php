<?php

namespace App\Api\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use App\Enums\AppEnum;

class Customer extends Model
{
 /**
  * 关联到模型的数据表
  *
  * @var string
  */

  var $parentType = AppEnum::Customer;
  protected $table = 'bse_customer';
  protected $fillable = ['company_id','cus_no','cus_type','cus_name','cus_level','cus_industry','cus_nature','cus_worker_num','cus_state','cus_visit_date','cus_addr','belong_uid','belong_person','cus_demand_area_start','cus_demand_area_end','cus_expected_price','cus_trim_state','channel_id','channel_name','channel_contact','channel_contact_phone','proj_name','proj_id','cus_rate','deal_rate','cus_tags','room_type','remark','business_info_id','c_uid','u_uid'];
  protected $hidden = ['deleted_at',"company_id",'c_uid','u_uid','updated_at'];

  // protected $appends = ['proj_name'];
  // public function getProjNameAttribute () {
  //   $projId = $this->attributes['proj_id'];
  //   $proj = \App\Api\Models\Project::select('proj_name')->find($projId);
  //   return $proj['proj_name'];
  // }

  public function customer_contact(){
    return $this->hasMany('App\Api\Models\Common\Contact','parent_id','id')
    ->where('parent_type',$this->parentType);
  }
  public function maintain(){
    return $this->hasMany('App\Api\Models\Common\Maintain','parent_id','id')
    ->where('parent_type',$this->parentType);
  }

  public function brokerageLog(){
    return $this->hasMany('App\Api\Models\Channel\ChannelBrokerage','cus_id','id');
  }

  public function customerExtra()
  {
    return $this->hasOne(CustomerExtra::class,'cus_id','id');
  }

  public function customerBusiness()
  {
    return $this->hasOne(CustomerBaseInfo::class,'id','business_info_id');
  }

  public function channel()
  {
    return $this->hasOne('App\Api\Models\Channel\Channel','id','channel_id');
  }

  public function contract(){
    return $this->hasMany('App\Api\Models\Contract\Contract','customer_id','id');
  }


  public function customerRoom(){
    return $this->hasMany(CustomerRoom::class,'cus_id','id');
  }

  public function customerFollow(){
    return $this->hasMany(CustomerFollow::class,'cus_id','id');
  }



  protected static function boot()
  {
  	parent::boot();
  	static::addGlobalScope(new CompanyScope);
  }


}
