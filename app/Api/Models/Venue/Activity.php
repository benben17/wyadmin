<?php


namespace App\Api\Models\Venue;

use Carbon\Carbon;
use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 活动
 */
class Activity extends Model
{
  /**
   * 活动
   *
   * @var string
   */
  use SoftDeletes;
  protected $table = 'bse_activities';
  protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid'];
  protected $fillable = ['status', 'proj_id', 'venue_id', 'venue_name', 'activity_title', 'activity_desc', 'activity_type', 'start_date', 'end_date', 'is_valid', 'is_hot', 'is_top'];
  protected $appends = ['status_label'];
  protected $casts = [
    'start_date' => 'datetime:Y-m-d',
    'end_date' => 'datetime:Y-m-d',
  ];

  public function project()
  {
    return $this->hasOne('App\Api\Models\Project', 'id', 'proj_id');
  }

  public function activityType()
  {
    return $this->hasMany(ActivityType::class, 'activity_id', 'id');
  }

  public function getStatusLabelAttribute()
  {
    $startDate = $this->start_date;
    $endDate = $this->end_date;

    // 检查日期是否为null，如果是，则将其设定为一个默认值或者采取其他处理方式
    if ($startDate === null || $endDate === null) {
      return '未知'; // 或者其他默认值
    }

    // 将日期字符串转换为 Carbon 实例，以便进行比较
    $startDate = Carbon::parse($startDate);
    $endDate = Carbon::parse($endDate);

    // 比较日期并生成状态标签
    if ($startDate->isFuture()) {
      $statusLabel = '未开始';
    } elseif ($startDate->isPast() && $endDate->isFuture()) {
      $statusLabel = '进行中';
    } elseif ($endDate->isPast()) {
      $statusLabel = '已结束';
    }

    return $statusLabel ?? '未知'; // 在 $statusLabel 未被赋值时返回默认值
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
