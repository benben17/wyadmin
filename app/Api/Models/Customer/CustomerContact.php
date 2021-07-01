<?php

namespace App\Api\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

class CustomerContact extends Model
{
 /**
  * 
  */
  use SoftDeletes;
  protected $table = 'bse_cus_contact';
  protected $hidden = ['deleted_at',"company_id",'c_uid','u_uid','updated_at','created_at'];
  protected $fillable = ['company_id','cus_id','cus_contact_name','cus_contact_role','cus_contact_phone','is_default','remark','c_uid','u_uid'];
  // public function customer(){
  	// return $this->belongto;
  // }
  protected static function boot()
  {
  	parent::boot();
  	static::addGlobalScope(new CompanyScope);
  }
}