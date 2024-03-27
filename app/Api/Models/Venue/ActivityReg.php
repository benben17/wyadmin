<?php

namespace App\Api\Models\Venue;


use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

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
    // protected $fillable = [];

    // public function project()
    // {
    //     return $this->hasOne('App\Api\Models\Project', 'id', 'proj_id');
    // }
    // public function venueBook()
    // {
    //     return $this->hasMany(VenueBook::class, 'venue_id', 'id');
    // }
    // public function venueSettle()
    // {
    //     return $this->hasMany(VenueSettle::class, 'venue_id', 'id');
    // }


    // getStatusLabel 为自定义 status label
    private function getStatusLabelAttribute()
    {
        $status = $this->attributes['status'] ?? 0;
        switch ($status) {
            case '1':
                return '未支付';
                break;
            case '2':
                return '已支付';
                break;
            case '3':
                return '已退款';
                break;
            case '99':
                return '已取消';
                break;
            default:
                return '未知';
                break;
        }
    }

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new CompanyScope);
    }
}
