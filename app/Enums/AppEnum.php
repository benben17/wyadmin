<?php

namespace App\Enums;

use MyCLabs\Enum\Enum;


final class AppEnum extends Enum
{
  const Channel       = 1;  //  渠道
  const Customer      = 2;  //  客户
  const Supplier      = 3;  //  供应商
  const Relationship  = 4;  // 公共关系
  const Tenant        = 5;  // 租户

  // 招商合同状态
  const contractSave      = 1;  // 保存
  const contractAudit     = 2;  // 待审核状态
  const contractExecute   = 3;  // 正在执行
  const contractLeaseBack = 98; // 退租
  const contractCancel    = 99; // 取消
  /**
   * 工单状态
   */
  const workorderOpen     = 1;   // 开单
  const workorderTake     = 2;   // 接单
  const workorderProcess  = 3;   // 处理
  const workorderClose    = 4;   // 关闭
  const workorderRate     = 5;   //评价

  const rentFeeType       = 101;    // 租金规则

  const TenantType = 2;

  const feeType  = 1;
  const depositFeeType = 2; // 押金类型


  // charge
  const chargeIncome = 1;  // 收入
  const chargePay  = 2;    // 支出
}
