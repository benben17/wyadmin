<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;


/**
 *  天眼查，查询日志
 */
class SkyeyeLog extends Model
{

  // use SoftDeletes;

  protected $table = 'bse_skyeye_log';
  // protected $primaryKey = 'company_id';
  protected $hidden = ['deleted_at'];
  // protected $fillable = ['no','company_id'];

}
