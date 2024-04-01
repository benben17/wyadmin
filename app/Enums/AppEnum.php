<?php

namespace App\Enums;

use MyCLabs\Enum\Enum;


/**
 * 应用变量信息
 *
 * @Author leezhua
 * @DateTime 2024-03-25
 */
abstract class AppEnum extends Enum
{
  const Channel       = 1;  //  渠道
  const Customer      = 2;  // 客户
  const Supplier      = 3;  //  供应商
  const Relationship  = 4;  // 公共关系
  const Tenant        = 5;  // 租户
  const CusClue       = 6;  // 线索
  const YhWorkOrder   = 7;  // 隐患工单

  // 招商合同状态
  const contractSave      = 0;  // 保存
  const contractAudit     = 1;  // 待审核状态
  const contractExecute   = 2;  // 正在执行
  const contractLeaseBack = 98; // 退租
  const contractCancel    = 99; // 取消
  /**
   * 工单状态
   */
  const workorderOpen     = 1;   // 开单
  const workorderTake     = 2;   // 接单
  const workorderProcess  = 3;   // 处理 
  const workorderClose    = 4;   // 关闭 // 隐患 审核
  const workorderRate     = 5;   //评价
  const workorderWarehouse = 90;   // 隐患 仓库
  const workorderCancel   = 99;   // 取消

  const rentFeeType       =   101;    // 租金类型
  const managerFeeType    =   102;    // 管理费
  const waterFeeType      =   103;    // 水类型id
  const electricFeeType   =   104;     // 电类型
  const maintenanceFeeType    = 105;  // 工程维修费

  const TenantType = 2;  // 客户类型  1 客户  2 租户
  const feeType  = 1; // 费用
  const depositFeeType = 2; // 押金类型
  const dailyFeeType = 3; // 日常费用

  // charge
  const chargeIncome = 1;  // 收入     
  const chargeRefund  = 2;    // 支出

  // const chargeCategoryFee  = 1;   //费用 类型
  // const chargeCategoryDeposit  = 2;    //押金类型
  const depositRecordReceive = 1;   // 押金收入
  const depositRecordToCharge = 2; // 转收款
  const depositRecordRefund = 3;  // 押金退款


  // 0 => "未收款",
  // 1 => "已收款",
  // 2 => "退款",
  // 3 => '已结清',
  const depositStatusUnReceive = 0;  // 未收款
  const depositStatusReceived = 1;  // 已收款
  const depositStatusRefund = 2;  // 部分退款
  const depositStatusClear = 3;  // 已结清


  const chargeCategoryFee  = 1;   //费用
  const chargeCategoryDeposit  = 2;    // 押金转收入
  const chargeCategoryRefund  = 3;    // 收入退款

  // 免租类型
  const freeMonth = 1; // 按月免租
  const freeDay = 2; // 按天免租

  const chargeVerify  = 1;  //  已核销
  const chargeUnVerify  = 0; // 未核销


  // 单位
  const shareRate = "%";
  const shareAmt  = "元";
  const shareArea = "m²";


  // 房源单价
  const dayPrice = "元/㎡/天";
  const monthPrice = "元/㎡/月";

  const projType = "办公园区";

  // 跟进方式
  const followVisit = 87; // 来访

  // 发票状态
  const invoiceStatusUnOpen = 1;  // 未开
  const invoiceStatusOpened = 2;  // 已开
  const invoiceStatusCancel = 3;  // 作废

  // 应收状态
  const feeStatusUnReceive = 0;  // 未结清
  const feeStatusReceived = 1;  // 已经清
  const feeStatusPartRefund = 2;  // 部分退款
  const feeStatusRefund = 3;  // 已退款

  const statusUnAudit = 0;  // 未审核
  const statusAudit = 1;  // 已审核
}
