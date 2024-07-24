<?php

namespace App\Api\Models\Company;

use Illuminate\Database\Eloquent\Model;


class TemplateParm extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  protected $table = 'bse_template_parm';

  protected $fillable = [];

  protected $hidden = ['deleted_at', 'created_at', 'updated_at'];
}
