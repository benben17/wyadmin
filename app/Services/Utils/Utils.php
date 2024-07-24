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
  if (empty($pics)) {
    return [];
  }
  return array_map('getOssUrl', str2Array($pics));
}


/**
 * @Desc: 检查对象是否为空
 * @Author leezhua
 * @Date 2024-07-18
 * @param [type] $obj
 * @return boolean
 */
function isEmptyObj($obj): bool
{
  return empty(get_object_vars($obj));
}

/**
 * 数字格式化，保留2位小数
 * @Author leezhua
 * @Date 2024-05-05
 * @param mixed $data 
 * @param int $decimals 
 * @return mixed 
 */
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


/**
 * curl http请求
 * @Author leezhua
 * @Date 2024-04-04
 * @param mixed $url 
 * @param array $headers 
 * @return string|bool 
 */
function http_request($url, $headers = [])
{
  $curl = curl_init();
  if (!empty($headers)) {
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_HEADER, 0); //返回response头部信息
  }
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
  if (!empty($data)) {
    curl_setopt($curl, CURLOPT_HTTPGET, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
  }
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
  $result =  curl_exec($curl);
  curl_close($curl);
  return $result;
}
