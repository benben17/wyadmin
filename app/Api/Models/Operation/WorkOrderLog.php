<?php

namespace App\Api\Models\Operation;

use App\Api\Scopes\CompanyScope;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 *  保修工单
 */
class WorkOrderLog extends Model
{

  /**
   * 关联到模型的数据表
   * @var string
   */

  protected $table = 'bse_workorder_log';
  protected $fillable = [];
  protected $hidden = [];

  // protected $appends = ["pic_full", 'maintain_pic_full'];


  public function getStatusAttribute($status)
  {
    if ($status == 1)
      return '待接单';
    else if ($status == 2)
      return '处理中';
    else if ($status == 3)
      return '处理完成';
    else if ($status == 4)
      return '关闭';
    else if ($status == 99)
      return '已取消';
  }
}
