<?php

namespace App\Enums;

use MyCLabs\Enum\Enum;


final class AppEnum extends Enum
{
  const Channel       = 1;  //  渠道
  const Customer      = 2;
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

  const rentFeeType       =   101;    // 租金类型id
  const managerFeeType    =   102;    // 管理费
  const waterFeeType      =   103;    // 水类型id
  const electricFeeType   =   104;     // 电类型

  const TenantType = 2;  // 客户类型  1 客户  2 租户
  const feeType  = 1;
  const depositFeeType = 2; // 押金类型


  // charge
  const chargeIncome = 1;  // 收入     
  const chargePay  = 2;    // 支出


  const chargeVerify  = 1;  // 
  const chargeUnVerify  = 0;


  // 单位
  const shareRate = "%";
  const shareAmt  = "元";
  const shareArea = "m²";


  // 房源单价
  const dayPrice = "元/㎡/天";
  const monthPrice = "元/㎡/月";
}
