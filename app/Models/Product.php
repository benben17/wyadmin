<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
 protected $table = 'sys_product';
 protected $hidden = ['deleted_at'];
}
