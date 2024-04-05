<?php

namespace App\Enums;

use App\Enums\StatusEnum;

class ClueStatus extends StatusEnum
{
  const Pending   = 1;
  const Converted = 2;
  const Invalid   = 3;

  protected static $labels = [
    self::Pending   => '待转化',
    self::Converted => '已转化',
    self::Invalid   => '无效',
  ];
}
