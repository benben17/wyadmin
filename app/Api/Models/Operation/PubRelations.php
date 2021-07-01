<?php

namespace App\Api\Models\Operation;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use App\Services\CompanyServices;
use App\Enums\AppEnum;

/**
 *
 */
class PubRelations extends Model
{
  /**
   * 关联到模型的数据表
   *
   * @var string
   */

  var $parentType = AppEnum::Relationship;
  protected $table = 'bse_relations';
  protected $fillable = [];
  protected $hidden = ["company_id", 'updated_at'];

  protected $appends = ['proj_name'];

  public function getProjNameAttribute()
  {
    $projId = $this->attributes['proj_id'];
    $companyService = new CompanyServices;
    return $companyService->getProjName($projId);
  }

  public function maintain()
  {
    return $this->hasMany('App\Api\Models\Common\Maintain', 'parent_id', 'id')
      ->where('parent_type', $this->parentType);
  }
  public function attachment()
  {
    return $this->hasMany('App\Api\Models\Common\Attachment', 'parent_id', 'id')
      ->where('parent_type', $this->parentType);
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
