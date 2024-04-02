<?php


namespace App\Api\Models\Venue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 活动
 */
class ActivityType extends Model
{
  /**
   * 活动
   *
   * @var string
   */
  use SoftDeletes;
  protected $table = 'bse_activity_types';
  protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid', 'status_label'];
  // protected $fillable = [];

  // public function project()
  // {
  //     return $this->hasOne('App\Api\Models\Project', 'id', 'proj_id');
  // }

}
