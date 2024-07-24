<?php

namespace App\Api\Models\Contract;

use App\Models\BaseModel;


class ContractLog extends BaseModel
{
  /**
   *  合同操作
   */
  /** type 1 作废日志 2 审核日志 */
  protected $table = 'bse_contract_log';
  protected $fillable = ['contract_id', 'contract_state', 'remark', 'type', 'audit_state', 'c_username', 'c_uid'];
  protected $hidden = ['deleted_at', 'type', 'updated_at'];
  protected $appends = ['state_label'];

  public function getStateLabelAttribute()
  {
    if (isset($this->attributes['contract_state'])) {
      $value =  $this->attributes['contract_state'];
      switch ($value) {
        case '0':
          return "待提交";
          break;
        case '1':
          return "待审核";
          break;
        case '2':
          return '正常执行';
          break;
        case '98':
          return '退租合同';
          break;
        case '99':
          return '作废合同';
          break;
      }
    }
  }
}
