<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
   /**
    * 关联到模型的数据表
    *
    * @var string
    */
   protected $table = 'sys_module';

   protected $hidden = [];

}
