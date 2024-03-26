<?php

namespace App\Api\Models\Operation;

use App\Api\Models\Common\Maintain;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use App\Services\CompanyServices;
use App\Enums\AppEnum;

/**
 *公共关系
 */
class PubRelations extends Model
{

  var $parentType = AppEnum::Relationship;  // 维护时使用
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
    return $this->hasMany(Maintain::class, 'parent_id', 'id')
      ->where('parent_type', $this->parentType);
  }

  /**
   * 附件查询 
   *
   * @Author leezhua
   * @DateTime 2024-03-25
   *
   * @return void
   */
  public function attachment()
  {
    return $this->hasMany('App\Api\Models\Common\Attachment', 'parent_id', 'id')
      ->where('parent_type', $this->parentType);
  }

  /**
   * 批量保存
   *
   * @Author leezhua
   * @DateTime 2024-03-25
   * @param [type] $data
   *
   * @return bool 
   */
  public static function batchSave($data): bool
  {
    if (empty($data)) {
      return false;
    }
    return self::insert($data);
  }


  //  
  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
