<?php

namespace App\Api\Scopes;

use JWTAuth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;

class CompanyScope implements Scope
{
    /**
     * 把约束加到 Eloquent 查询构造中。
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $user = auth('api')->user();
        if ($user) {
            $tableName = $model->getTable();
            $map = array(
                $tableName . '.company_id'   =>  $user->company_id
            );
        } else {
            $map = array();
        }

        $builder->where($map);
    }
}
