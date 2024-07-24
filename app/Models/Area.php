<?php

namespace App\Models;

use \App\Api\Scopes\UserScope;

class Area extends BaseModel
{
   /**
    * 关联到模型的数据表
    *
    * @var string
    */
   protected $table = 'sys_area';

   protected $hidden = ['created_at', 'updated_at'];
}
