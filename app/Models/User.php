<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Models\Sys\UserGroup as UserGroupModel;

class User extends Model
{
    use SoftDeletes;
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'users';

    protected $hidden = ['password', 'remember_token'];


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
