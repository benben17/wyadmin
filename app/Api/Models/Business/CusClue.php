<?php

namespace App\Api\Models\Business;

use App\Api\Models\Tenant\Follow;
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
class CusClue extends Model
{
  use SoftDeletes;
  protected $table = 'bse_customer_clue';

  protected $fillable = [
    'clue_type',
    'clue_time',
    'status',
    'name',
    'phone',
    'remark',
    'company_id',
    'proj_id',
    'c_uid', 'deleted_at', "company_id", 'u_uid', 'created_at', 'updated_at'
  ];
  protected $hidden = ['deleted_at', "company_id", 'c_uid', 'u_uid', 'created_at', 'updated_at'];
  protected $appends = ['clue_type_label', 'c_user', 'status_label'];


  public function getClueTypeLabelAttribute()
  {
    if (isset($this->attributes['clue_type'])) {
      return getDictName($this->attributes['clue_type']);
    }
  }
  public function getStatusLabelAttribute()
  {
    $statusLabels = [
      1 => "待转化",
      2 => "已转化",
    ];

    $status = $this->attributes['status'] ?? '';
    return $statusLabels[$status] ?? "无效";
  }
  public function getCUserAttribute()
  {
    if (isset($this->attributes['c_uid'])) {
      $user = getUserByUid($this->attributes['c_uid']);
      return $user['realname'];
    }
  }

  public function customer()
  {
    return $this->hasOne(Tenant::class, 'id', 'tenant_id');
  }

  public function cusFollow()
  {
    return $this->hasMany(Follow::class, 'tenant_id', 'tenant_id');
  }

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
