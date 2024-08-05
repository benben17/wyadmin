<?php

namespace App\Models;

use DateTimeInterface;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Api\Models\Sys\UserGroup as UserGroupModel;

class User extends BaseModel
{
    use SoftDeletes;



    /**
     * 关联到模型的数据表
     *
     * @var string
     */

    protected $table = 'users';

    protected $hidden = ['password', 'remember_token',  'updated_at'];
    protected $appends = ['manager_label', 'c_user',];


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function getCUserAttribute()
    {
        if (isset($this->attributes['c_uid'])) {
            $user = getUserByUid($this->attributes['c_uid']);
            return $user['realname'] ?? "";
        }
        return "";
    }

    public function getManagerLabelAttribute()
    {
        if (isset($this->attributes['is_manager'])) {
            return isset($this->attributes['c_uid']) ? "是" : "否";
        } else {
            return "";
        }
    }


    public function company()
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }
    public function role()
    {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }
    public function group()
    {
        return $this->hasOne(UserGroupModel::class, 'id', 'group_id');
    }

    public function depart()
    {
        return $this->hasOne(Depart::class, 'id', 'depart_id');
    }
}
