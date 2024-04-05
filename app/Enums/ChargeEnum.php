<?php

use FontLib\Table\Type\name;

namespace App\Enums;

class ChargeEnum extends BaseEnum
{
  const Income = 1;  // 收入     
  const Refund = 2;  // 支出

  const CategoryFee     = 1;  //费用
  const CategoryDeposit = 2;  // 押金转收入
  const CategoryRefund  = 3;  // 收入退款


  const chargeVerify   = 1;  //  已核销
  const chargeUnVerify = 0;  // 未核销

  protected static $typeLabels = [
    self::Income => '收入',
    self::Refund => '支出',
  ];
  protected static $categoryLabels = [
    self::CategoryFee     => '费用',
    self::CategoryDeposit => '押金转收入',
    self::CategoryRefund  => '收入退款',
  ];

  public static function getTypeLabels(): array
  {
    return self::$typeLabels;
  }

  public static function getCategoryLabels(): array
  {
    return self::$categoryLabels;
  }
}
