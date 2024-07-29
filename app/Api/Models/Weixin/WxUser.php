<?php

namespace App\Api\Models\Weixin;

use App\Models\BaseModel;
use Illuminate\Auth\Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class WxUser extends BaseModel implements JWTSubject, AuthenticatableContract
{
  use SoftDeletes, Authenticatable;

  protected $table = 'bse_wx_user';
  protected $fillable = ['*'];
  protected $hidden = ['deleted_at', "company_id"];


  public function getJWTIdentifier()
  {
    return $this->getKey();
  }

  public function getJWTCustomClaims()
  {
    return [];
  }

  // protected static function boot()
  // {
  //   parent::boot();
  //   static::addGlobalScope(new CompanyScope);
  // }
}
