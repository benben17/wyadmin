<?php

namespace App\Api\Models\Common;

use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 附件管理模型
 *
 * @Author leezhua
 * @DateTime 2024-03-28
 */
class Attachment extends Model
{

  use SoftDeletes;
  protected $table = "bse_common_attachment";
  protected $hidden = ["deleted_at", "company_id", 'parent_type', 'updated_at'];
  // protected $fillable = [];
  protected $appends = ['file_path_full', 'atta_type_label'];

  public function getFilePathFullAttribute()
  {
    return getOssUrl($this->file_path);
  }

  public function getAttaTypeLabelAttribute()
  {
    $atta_type = $this->atta_type ?? "";
    return getDictName($atta_type);
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
