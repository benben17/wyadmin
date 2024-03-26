<?php
namespace App\Api\Models\Operation;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use App\Services\CompanyServices;

/**
 *
 */
class Parking extends Model
{
  /**
  * 关联到模型的数据表
  *
  * @var string
  */

  protected $table = 'bse_parking_lot';
  protected $fillable = [];
  protected $hidden = ["company_id",'updated_at'];

  protected $appends = ['proj_name'];

  public function getProjNameAttribute () {
    $projId = $this->attributes['proj_id'];
    $companyService = new CompanyServices;
    return $companyService->getProjName($projId);
  }

  protected static function boot(){
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}