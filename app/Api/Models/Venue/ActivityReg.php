<?php

namespace App\Api\Models\Venue;


use App\Api\Scopes\CompanyScope;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * 活动报名
 */
class ActivityReg extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    // use SoftDeletes;
    protected $table = 'bse_activity_reg';

    protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid', 'status_label'];
    protected $fillable = ["status", "proj_id", "venue_id", "venue_name", "activity_title", "user_id", "user_name", "user_phone", "reg_time"];
    protected $appends = ['status_label'];

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'id', 'activity_id');
    }

    public function getStatusLabelAttribute()
    {
        $status = $this->attributes['status'] ?? 0;
        $statusMap = [
            '1'  => '未支付',
            '2'  => '已支付',
            '3'  => '已退款',
            '99' => '已取消',
        ];
        return $statusMap[$status] ?? '未知';
    }

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new CompanyScope);
    }
}
