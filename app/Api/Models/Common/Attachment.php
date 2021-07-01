<?php
namespace App\Api\Models\Common;

use Illuminate\Database\Eloquent\Model;


/**
 *
 */

class Attachment extends Model
{

  protected $table = "bse_common_attachment";
  protected $hidden = ["deleted_at","company_id",'parent_type','updated_at'];
  // protected $fillable = [];


}