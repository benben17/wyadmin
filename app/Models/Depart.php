<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Models\Sys\UserGroup as UserGroupModel;
use App\Api\Scopes\CompanyScope;

class Depart extends Model
{
    use SoftDeletes;
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'bse_depart';

    protected $hidden = ["updated_at", "u_uid", "company_id", 'deleted_at'];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new CompanyScope);
    }
}
