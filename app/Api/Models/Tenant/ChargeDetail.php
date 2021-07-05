<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Api\Scopes\CompanyScope;

class ChargeDetail extends Model
{
  protected $table = 'bse_charge_detail';
  protected $fillable = [];
  protected $hidden = ['deleted_at', 'company_id'];
}
