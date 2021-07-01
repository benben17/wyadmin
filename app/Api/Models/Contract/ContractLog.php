<?php

namespace App\Api\Models\Contract;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Api\Scopes\CompanyScope;

class ContractLog extends Model
{
 /**
  *  合同操作
  */
 /** type 1 作废日志 2 审核日志 */
  protected $table = 'bse_contract_log';
  protected $fillable = ['contract_id','contract_state','remark','type','audit_state','c_username','c_uid'];
  protected $hidden = ['deleted_at','type','updated_at'];
}