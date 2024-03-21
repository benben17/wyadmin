<?php

namespace App\Api\Services\Common;

use QrCode;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * 二维码
 */
class QrCodeService
{

  /**
   * 二维码生成
   *
   * @param [type] $content  内容
   * @param [type] $companyId 公司ID
   * @return void
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
        ->merge('/public/icon.png', .15)
        ->generate($content, $fileName);
    } catch (Exception $e) {
      Log::error($e);
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
