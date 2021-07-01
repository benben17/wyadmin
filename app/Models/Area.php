<?php
namespace App\Models;

use \App\Api\Scopes\UserScope;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
   /**
    * 关联到模型的数据表
    *
    * @var string
    */
   protected $table = 'sys_area';

   protected $hidden = ['created_at','updated_at'];
}
