<?php

namespace App\Api\Services\Common;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * 二维码
 */
class QrcodeService
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
      Log::error(public_path('qrcode.png'));
      $fileName = uuid() . '.png';
      QrCode::format('png')
        ->encoding('UTF-8')
        ->size(100)
        ->color(255, 0, 255)
        ->margin(100)
        ->generate($content, $fileName);

      // Log::error($hahh);
      // $saveFolder = $companyId . '/operation/' . date('Ymd');
      // $res = Storage::putFile($saveFolder, public_path('/') . $fileName);
      // Log::error($res);
      // return $res;
      // unlink(public_path('/') . $fileName);
    } catch (Exception $e) {
      Log::error("二维码生成" . $e);
      return false;
    }
    return "";
  }
}
