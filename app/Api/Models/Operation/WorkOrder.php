<?php

namespace App\Api\Models\Operation;

use App\Api\Scopes\CompanyScope;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 *  报修工单
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


  protected $appends = ['status_label', 'pic_full', 'maintain_pic_full'];

  public function getStatusLabelAttribute()
  {
    $status = $this->attributes['status'];
    return $this->statusMap()[$status];
  }

  public function getPicFullAttribute()
  {
    $pic = $this->attributes['pic'] ?? "";
    return picFullPath($pic);
  }

  public function getMaintainPicFullAttribute()
  {
    $pic = $this->attributes['maintain_pic'] ?? "";
    return picFullPath($pic);
  }
  public function statusMap()
  {
    return [
      '1' => '待派单',
      '2' => '处理中',
      '3' => '处理完成',
      '4' => '关闭',
      '99' => '已取消'
    ];
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
