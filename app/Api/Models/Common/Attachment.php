<?php

namespace App\Api\Models\Common;

use Illuminate\Database\Eloquent\Model;


/**
 * 附件管理模型
 *
 * @Author leezhua
 * @DateTime 2024-03-28
 */
class Attachment extends Model
{

  protected $table = "bse_common_attachment";
  protected $hidden = ["deleted_at", "company_id", 'parent_type', 'updated_at'];
  // protected $fillable = [];


}
