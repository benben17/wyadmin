<?php

namespace App\Api\Models\Venue;


use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

/**
 * 场馆
 */
class Venue extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    // use SoftDeletes;
    protected $table = 'bse_venue';

    protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid'];
    // protected $fillable = [];

    public function project()
    {
        return $this->hasOne('App\Api\Models\Project', 'id', 'proj_id');
    }
    public function venueBook()
    {
        return $this->hasMany(VenueBook::class, 'venue_id', 'id');
    }
    public function venueSettle()
    {
        return $this->hasMany(VenueSettle::class, 'venue_id', 'id');
    }
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new CompanyScope);
    }
}
