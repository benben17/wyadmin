<?php

namespace App\Api\Models\Contract;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;

class ContractTemplate extends Model
{
  protected $table = 'bse_template';

  protected $fillable = ['company_id', 'name', 'file_name', 'file_path', 'remark', 'c_uid', 'u_uid'];
  protected $hidden = ['deleted_at', 'updated_at', 'company_id'];

  protected static function boot()
  {
    parent::boot();
    static::addGlobalScope(new CompanyScope);
  }
}
