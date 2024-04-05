<?php

namespace App\Enums;

class InvoiceEnum extends BaseEnum
{
  const UnOpen = 1;  // 未开
  const Opened = 2;  // 已开
  const Cancel = 3;  // 作废

  protected static $labels = [
    self::UnOpen => '未开',
    self::Opened => '已开',
    self::Cancel => '作废',
  ];



  public static function getLabels()
  {
    return self::$labels;
  }
}
