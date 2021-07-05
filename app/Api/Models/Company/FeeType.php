<?php

namespace App\Api\Models\Company;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

class FeeType extends Model
{
  /**
   * 费用类型
   *
   * @var string
   */

  protected $table = 'bse_fee_type';

  protected $fillable = [];
  protected $hidden = ['deleted_at', 'created_at'];
}
