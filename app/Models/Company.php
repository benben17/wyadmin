<?php

namespace App\Models;

use \App\Api\Scopes\UserScope;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class Company extends BaseModel
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */
  protected $table = 'sys_company';
  protected $hidden = ['created_at', 'updated_at'];

  public function module()
  {
    return $this->belongsToMany(Module::class, CompanyModule::class, 'company_id', 'module_id');
  }
  public function user()
  {
    return $this->hasMany(User::class, 'company_id', 'id');
  }
  public function province()
  {
    return $this->hasOne(Area::class, 'code', 'province_id');
  }
  public function city()
  {
    return $this->hasOne(Area::class, 'code', 'city_id');
  }
  public function district()
  {
    return $this->hasOne(Area::class, 'code', 'district_id');
  }
  public function product()
  {
    return $this->hasOne(Product::class, 'id', 'product_id');
  }
  public function order()
  {
    return $this->hasMany(Order::class, 'company_id', 'id')->with('product');
  }
}
