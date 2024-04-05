<?php

namespace App\Enums;

use MyCLabs\Enum\Enum;

abstract class StatusEnum extends Enum
{
  protected static $labels = [];

  public static function getValueAndLabel($key)
  {
    if (!self::isValidKey($key)) {
      return null;
    }
    $value = self::getValue($key);
    $label = static::$labels[$value];
    return [$value => $label];
  }

  public static function getLabelByKey($key)
  {
    if (self::isValidKey($key)) {
      return static::$labels[self::getValue($key)];
    }
  }

  public static function getAll()
  {
    $allValuesWithLabels = [];

    foreach (static::$labels as $value => $label) {
      $allValuesWithLabels[$value] = $label;
    }

    return $allValuesWithLabels;
  }
}
