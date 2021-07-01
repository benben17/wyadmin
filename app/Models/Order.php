<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
  protected $table = 'sys_order';
  protected $hidden = ['product_id','deleted_at','c_uid','u_uid'];
  public function company() {
    return $this->belongsTo(Company::class, 'company_id', 'id');
  }
  public function product() {
    return $this->belongsTo(Product::class, 'product_id', 'id');
  }
}
