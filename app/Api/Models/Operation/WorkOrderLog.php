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
    $statusMap = [
      1 => '待接单',
      2 => '处理中',
      3 => '处理完成',
      4 => '关闭',
      99 => '已取消',
    ];

    return $statusMap[$status] ?? '未知状态';
  }
}
