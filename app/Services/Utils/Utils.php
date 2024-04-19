<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * 图片地址转换
 * @Author leezhua
 * @Date 2024-04-01
 * @param mixed $pic 
 * @return string[] 
 */
function picFullPath($pics): array
{
  $picFull = [];
  foreach (str2Array($pics) as $pic) {
    $picFull[] = getOssUrl($pic);
  }
  return $picFull;
}


function isEmptyObj($obj): bool
{
  return empty(get_object_vars($obj));
}


function num_format(&$data, $decimals = 2)
{
  if (is_array($data)) {
    foreach ($data as $k => &$v) {
      if (is_array($v) || is_object($v)) {
        num_format($v, $decimals);
      } else if (Str::endsWith($k, '_amt') || Str::endsWith($k, 'amount') || Str::endsWith($k, '_price')) {
        $v = number_format($v, $decimals);
      }
    }
  } else if (is_object($data)) {
    $data = (array) $data;
    num_format($data, $decimals);
  } else {
    if (is_float($data) || is_numeric($data) || is_double($data)) {
      $data =  number_format($data, $decimals);
    }
  }
  return $data;
}
