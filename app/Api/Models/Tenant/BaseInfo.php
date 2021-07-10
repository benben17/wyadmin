<?php

namespace App\Api\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;
use Illuminate\Support\Facades\DB;

/**
 * 客户工商信息
 */
class BaseInfo extends Model
{

  // use SoftDeletes;
  protected $table = 'bse_business_info';
  protected $hidden = ['deleted_at', 'c_uid', 'u_uid', 'updated_at'];
  protected $fillable = ['id', 'staffNumRange', 'fromTime', 'isMicroEnt', 'usedBondName', 'bondName', 'regNumber', 'percentileScore', 'regCapital', 'name', 'regInstitute', 'regLocation', 'industry', 'approvedTime', 'socialStaffNum', 'tags', 'taxNumber', 'businessScope', 'property3', 'alias', 'orgNumber', 'regStatus', 'estiblishTime', 'bondType', 'legalPersonName', 'toTime', 'actualCapital', 'companyOrgType', 'base', 'creditCode', 'historyNames', 'bondNum', 'regCapitalCurrency', 'actualCapitalCurrency', 'revokeDate', 'revokeReason', 'cancelDate', 'cancelReason'];
}