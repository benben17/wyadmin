<?php

use Illuminate\Support\Arr;

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
