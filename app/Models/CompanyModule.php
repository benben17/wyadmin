<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyModule extends BaseModel
{
   /**
    * 关联到模型的数据表
    *
    * @var string
    */
   protected $table = 'sys_company_module';

   protected $hidden = ['created_at', 'updated_at'];
}
