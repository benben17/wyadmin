<?php

namespace App\Api\Models\Common;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;

class BseRemark extends Model
{
  /**
   *
   */
  use SoftDeletes;
  protected $table = 'bse_common_remark';
  protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid', 'updated_at', 'company_id', 'id'];
  protected $fillable = ['company_id', 'parent_type', 'parent_id', 'remark_content', 'c_user', 'c_uid', 'u_uid'];


  public function addAll(array $data)
  {
    $res = DB::table($this->getTable())->insert($data);
    return $res;
  }

  public function createUser()
  {
    return $this->hasOne('App\Models\User', 'id', 'c_uid');
  }
  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
