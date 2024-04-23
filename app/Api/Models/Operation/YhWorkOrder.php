<?php

namespace App\Api\Models\Operation;

use App\Enums\AppEnum;
use App\Api\Scopes\CompanyScope;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *  隐患工单
 */
class YhWorkOrder extends Model
{

  /**
   * 关联到模型的数据表
   * @var string
   */

  use SoftDeletes;
  protected $table = 'bse_yh_workorder';
  protected $fillable = [];
  protected $hidden = ['company_id'];


  protected $appends = ['status_label', "pic_full", 'process_pic_full', 'process_status_label'];


  public function getStatusLabelAttribute()
  {
    $status = $this->attributes['status'] ?? 0;
    return $status ? $this->statusMap()[$status] : "";
  }

  public function getPicFullAttribute()
  {
    $pic = $this->attributes['pic'] ?? "";
    return picFullPath($pic);
  }

  public function getProcessPicFullAttribute()
  {
    $pic = $this->attributes['process_pic'] ?? "";
    return picFullPath($pic);
  }

  public function getProcessStatusLabelAttribute()
  {
    $status = $this->attributes['process_status'] ?? 0;
    return  getDictName($status);
  }

  /**
   * 工单状态
   * @Author leezhua
   * @Date 2024-04-02
   * @return string[] 
   */
  public function statusMap()
  {
    return [
      '1' => '待派单',
      '2' => '已派单',
      '3' => '处理完成',
      '4' => '已关闭',
      '90' => '隐患库',
      // '99' => '已取消'
    ];
  }


  public function tenant()
  {
    return $this->hasOne(Tenant::class, 'tenant_id', 'id');
  }

  public function remarks()
  {
    return $this->hasMany('App\Api\Models\Common\BseRemark', 'parent_id', 'id')
      ->where('parent_type', AppEnum::YhWorkOrder);
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
