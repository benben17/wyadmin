<?php

namespace App\Enums;

/**
 * 押金状态
 * 
 * @package App\Enums
 */
class DepositEnum extends BaseEnum
{
  const UnReceive = 0;  // 未收款
  const Received  = 1;  // 已收款
  const Refund    = 2;  // 部分退款
  const Clear     = 3;  // 已结清
  const RecordReceive  = 1;  // 押金收入
  const RecordToCharge = 2;  // 转收款
  const RecordRefund   = 3;  // 押金退款

  protected static $labels = [
    self::UnReceive => '未收款',
    self::Received  => '已收款',
    self::Refund    => '部分退款',
    self::Clear     => '已结清',
  ];

  protected static $recordLabels = [
    self::RecordReceive  => '押金收入',
    self::RecordToCharge => '转收款',
    self::RecordRefund   => '押金退款',
  ];

  public static function getLabels(): array
  {
    return self::$labels;
  }

  public static function getRecordLabels(): array
  {
    return self::$recordLabels;
  }
}
