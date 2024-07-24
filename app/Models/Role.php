<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends BaseModel
{
   /**
    * 关联到模型的数据表
    *
    * @var string
    */
   protected $table = 'sys_role';

   protected $hidden = [];
}
