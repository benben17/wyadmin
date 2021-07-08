<?php

namespace App\Api\Services\Common;

use QrCode;
use Illuminate\Support\Facades\Storage;

/**
 * 二维码
 */
class QrcodeService
{

  /** 生成二维码 */
  /**
   * 二维码地址
   * @Author   leezhua
   * @DateTime 2020-07-12
   * @return   [type]     [description]
   */
  public  function createQr($content, $companyId)
  {
    try {
      $fileName = uuid() . '.png';
      QrCode::encoding('UTF-8')
        // ->margin(2)
        ->color(255, 0, 0, 25)
        ->backgroundColor(255, 255, 255)
        ->format('png')
        ->size(400)
        ->errorCorrection('H')
        ->merge('/public/icon.png', .2)
        ->generate($content, $fileName);
    } catch (Exception $e) {
      throw new Exception("生成二维码错误");
      return false;
    }
    try {
      $saveFolder = $companyId . '/operation/' . date('Ymd');
      $res = Storage::putFile($saveFolder, public_path('/') . $fileName);
      unlink(public_path('/') . $fileName);
    } catch (Exception $e) {
      return false;
    }
    return $res;
  }
}
