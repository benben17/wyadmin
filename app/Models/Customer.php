<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends BaseModel
{
   /**
    * 关联到模型的数据表
    *
    * @var string
    */
   protected $table = 'admin_customer';

   protected $hidden = ['created_at', 'updated_at'];

   // public function user()
   //     {
   //         return $this->belongsTo(User::class, 'customer_id','id');
   //     }
}
