<?php

namespace App\Enums;


/**
 * 应用变量信息
 *
 * @Author leezhua
 * @DateTime 2024-03-25
 */
abstract class AppEnum extends BaseEnum
{
  const Channel      = 1;  //  渠道
  const Customer     = 2;  // 客户
  const Supplier     = 3;  //  供应商
  const Relationship = 4;  // 公共关系
  const Tenant       = 5;  // 租户
  const CusClue      = 6;  // 线索
  const YhWorkOrder  = 7;  // 隐患工单

  // 招商合同状态
  const contractSave      = 0;  // 保存
  const contractAudit     = 1;  // 待审核状态
  const contractExecute   = 2;  // 正在执行
  const contractComplete  = 3;  // 已完成
  const contractLeaseBack = 98; // 退租
  const contractCancel    = 99; // 取消
  /**
   * 工单状态
   */
  const workorderOpen      = 1;   // 开单
  const workorderTake      = 2;   // 接单
  const workorderProcess   = 3;   // 处理 
  const workorderClose     = 4;   // 关闭 // 隐患 审核
  const workorderRate      = 5;   //评价
  const workorderWarehouse = 90;  // 隐患 仓库
  const workorderCancel    = 99;  // 取消

  const rentFeeType        = 101;  // 租金类型
  const managerFeeType     = 102;  // 管理费
  const waterFeeType       = 103;  // 水类型id
  const electricFeeType    = 104;  // 电类型
  const maintenanceFeeType = 105;  // 工程维修费

  const TenantCustomer = 1;  // 客户类型  1 客户  2 租户
  const TenantType     = 2;  // 客户类型  1 客户  2 租户 3 退租租户
  const TenantLeaseback     = 3;  // 退租租户

  const feeType        = 1;  // 费用 
  const depositFeeType = 2;  // 押金类型
  const dailyFeeType   = 3;  // 日常费用

  // charge

  // 免租类型
  const freeMonth = 1;  // 按月免租
  const freeDay   = 2;  // 按天免租

  // 单位
  const shareRate = "%";
  const shareAmt  = "元";
  const shareArea = "m²";

  const squareMeterUnit = "m²";
  const percentUnit     = "%";

  // 房源单价
  const dayPrice   = "元/㎡·天";
  const monthPrice = "元/㎡·月";

  const projType = "办公园区";

  // 跟进方式
  const followVisit = 87; // 来访

  // 应收状态
  const feeStatusUnReceive  = 0;  // 未结清
  const feeStatusReceived   = 1;  // 已经清
  const feeStatusPartRefund = 2;  // 部分退款
  const feeStatusRefund     = 3;  // 已退款

  const statusUnAudit = 0;  // 未审核
  const statusAudit   = 1;  // 已审核

  const valid = 1;  // 有效
  const invalid = 0;  // 无效

  const billDelay = 1; // 1 延期 2 正常
  const billNormal = 2; // 1 延期 2 正常
}
