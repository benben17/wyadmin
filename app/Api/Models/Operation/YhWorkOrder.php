<?php

namespace App\Api\Models\Operation;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

/**
 *  隐患工单
 */
class YhWorkOrder extends Model
{

  /**
   * 关联到模型的数据表
   * @var string
   */

  protected $table = 'bse_yh_workorder';
  protected $fillable = [];
  protected $hidden = ['company_id'];


  protected $appends = ['status_label'];


  public function getStatusLabelAttribute()
  {
    $status = $this->attributes['status'] ?? 0;

    return $status ? $this->statusMap()[$status] : "";
  }

  public function statusMap()
  {
    return [

      '1' => '待派单',
      '2' => '已派单',
      '3' => '处理完成',
      '4' => '关闭',
      '90' => '隐患库',
      '99' => '已取消'
    ];
  }


  public function orderLogs()
  {
    return $this->hasMany(WorkOrderLog::class, 'yh_order_id', 'id')->orderBy('id', 'desc');
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
