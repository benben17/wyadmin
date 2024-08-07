<?php

namespace App\Models;

use App\Api\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Models\Sys\UserGroup as UserGroupModel;

class Depart extends BaseModel
{
    use SoftDeletes;
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'bse_depart';

    protected $hidden = ["updated_at", "u_uid", "company_id", 'deleted_at'];
    protected $appends = ['vaild_label', 'c_user',];

    public function getCUserAttribute()
    {
        if (isset($this->attributes['c_uid'])) {
            $user = getUserByUid($this->attributes['c_uid']);
            return $user['realname'];
        } else {
            return "";
        }
    }

    public function getVaildLabelAttribute()
    {
        if (isset($this->attributes['is_vaild'])) {
            return $this->attributes['is_vaild'] ? "启用" : "禁用";
        } else {
            return "";
        }
    }
    public function users()
    {
        return $this->hasMany(User::class, 'depart_id', 'id');
    }
    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new CompanyScope);
    }
}
