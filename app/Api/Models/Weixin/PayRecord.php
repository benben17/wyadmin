<?php

namespace App\Api\Models\Venue;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

/**
 * 支付记录
 */
class PayRecord extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    use SoftDeletes;
    protected $table = 'bse_pay_record';

    protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid'];
    // protected $fillable = [];

    public function activity()
    {
        return $this->hasOne('App\Api\Models\Venue\ActivityReg', 'id', 'activity_reg_id');
    }
    // public function venueBook()
    // {
    //     return $this->hasMany(VenueBook::class, 'venue_id', 'id');
    // }
    // public function venueSettle()
    // {
    //     return $this->hasMany(VenueSettle::class, 'venue_id', 'id');
    // }
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new CompanyScope);
    }
}
