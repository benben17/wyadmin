<?php

namespace App\Api\Models\Energy;

use App\Models\BaseModel;


class MeterLog extends BaseModel
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  protected $table = 'bse_meter_log';
  protected $fillable = [];
  protected $hidden = ['deleted_at', "company_id", 'updated_at'];
}
