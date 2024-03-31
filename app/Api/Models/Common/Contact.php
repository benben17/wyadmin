<?php

namespace App\Api\Models\Common;

use App\Api\Scopes\CompanyScope;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

/**
 * 联系人类型1 渠道 2 客户 3 租户 4供应商 5 公共关系
 */
class Contact extends Model
{
  /**
   *
   */
  // use SoftDeletes;
  protected $table = 'bse_contacts';
  protected $hidden = ['deleted_at', "company_id", 'parent_type', 'c_uid', 'u_uid', 'updated_at', 'created_at'];
  protected $fillable = ['company_id', 'parent_type', 'parent_id', 'contact_name', 'contact_phone', 'contact_role', 'is_default', 'remark', 'c_uid', 'u_uid'];
  // public function customer(){
  // return $this->belongto;
  // }
  public function addAll(array $data)
  {
    // $res = DB::table($this->getTable())->insert($data);
    return $this->insert($data);
  }

  /**
   * 全局调用公司id查询
   *
   * @return void
   */
  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
