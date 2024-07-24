<?php

namespace App\Api\Models\Tenant;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 客户工商信息
 */
class BusinessInfo extends BaseModel
{

  use SoftDeletes;
  protected $table = 'bse_business_info';
  protected $hidden = ['deleted_at', 'c_uid', 'u_uid', 'updated_at'];
  protected $fillable = ['id', 'staffNumRange', 'fromTime', 'isMicroEnt', 'usedBondName', 'bondName', 'regNumber', 'percentileScore', 'regCapital', 'name', 'regInstitute', 'regLocation', 'industry', 'approvedTime', 'socialStaffNum', 'tags', 'taxNumber', 'businessScope', 'property3', 'alias', 'orgNumber', 'regStatus', 'estiblishTime', 'bondType', 'legalPersonName', 'toTime', 'actualCapital', 'companyOrgType', 'base', 'creditCode', 'historyNames', 'bondNum', 'regCapitalCurrency', 'actualCapitalCurrency', 'revokeDate', 'revokeReason', 'cancelDate', 'cancelReason'];
}
