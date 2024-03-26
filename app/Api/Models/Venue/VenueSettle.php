<?php

namespace App\Api\Models\Venue;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
// use App\Api\Scopes\CompanyScope;

/**
 * 场馆预定
 */
class VenueSettle extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    // use SoftDeletes;
    protected $table = 'bse_venue_settle';

    protected $hidden = ['cus_id','book_id','venue_id'];


    public function venue()
    {
        return $this->hasOne(Venue::class,'id','venue_id');
    }

    public function venueBook()
    {
        return $this->hasOne(VenueBook::class,'id','book_id');
    }

    public function addAll(Array $data)
    {
        $res = DB::table($this->getTable())->insert($data);
        return $res;
    }
}
