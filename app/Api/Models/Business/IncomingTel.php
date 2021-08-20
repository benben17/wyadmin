<?php

namespace App\Api\Models\Business;

use App\Api\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use App\Enums\AppEnum;

/**
 * 致电客户
 *
 * @Author leezhua
 * @DateTime 2021-08-20
 */
class IncomingTel extends Model
{
  use SoftDeletes;
  protected $table = 'bse_incoming_tel';

  protected $fillable = [];
  protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid', 'created_at', 'updated_at'];
  protected $appends = ['sex_label'];

  public function getSexLabelAttribute()
  {
    if (isset($this->attributes['sex'])) {
      if ($this->attributes['sex'] == 1) {
        return "男";
      } else {
        return "女";
      }
    }
  }
  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
