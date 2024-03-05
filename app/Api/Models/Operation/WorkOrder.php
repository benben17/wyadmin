<?php

namespace App\Api\Models\Operation;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

/**
 *  保修工单
 */
class WorkOrder extends Model
{

  /**
   * 关联到模型的数据表
   * @var string
   */

  protected $table = 'bse_workorder';
  protected $fillable = [];
  protected $hidden = ['company_id'];


  protected $appends = ['status_label', 'yh_status_label'];

  public function getStatusLabelAttribute()
  {
    $status = $this->attributes['status'];
    switch ($status) {
      case '1':
        return '待处理';
        break;
      case '2':
        return '处理中';
        break;
      case '3':
        return '处理完成';
        break;
      case '4':
        return '关闭';
        break;
      case '99':
        return '已取消';
        break;
    }
  }

  public function getYhStatusLabelAttribute()
  {
    $status = $this->attributes['status'];
    switch ($status) {
      case '1':
        return '待派单';
        break;
      case '2':
        return '待处理';
        break;
      case '3':
        return '处理完成';
        break;
      case '4':
        return '关闭';
        break;
      case '99':
        return '已取消';
        break;
    }
  }

  public function orderLogs()
  {
    return $this->hasMany(WorkOrderLog::class, 'workorder_id', 'id')->orderBy('id', 'desc');
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
