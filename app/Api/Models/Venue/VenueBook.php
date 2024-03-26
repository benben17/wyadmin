<?php

namespace App\Api\Models\Venue;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

/**
 * 场馆预定
 */
class VenueBook extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    // use SoftDeletes;
    protected $table = 'bse_venue_book';

    protected $hidden = ["company_id"];


    public function venue()
    {
        return $this->hasOne(Venue::class,'id','venue_id');
    }

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new CompanyScope);
    }
}
