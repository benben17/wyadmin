<?php

namespace App\Api\Models\Company;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

class Energy extends Model
{
  /**
  * 能耗单价设置
  *
  * @var string
  */

  protected $table = 'bse_energy_price';

  protected $fillable = ['company_id','proj_id','water_price','electric_price','c_uid'];
  protected $hidden = ['company_id','c_uid','updated_at','created_at'];


  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }

}