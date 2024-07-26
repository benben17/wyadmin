<?php

namespace App\Api\Services\Common;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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
  public function createQr($content, $companyId): string
  {
    try {
      // Log::error($content);

      $fileName = public_path(Str::uuid() . '.png');
      $ossFolder = $companyId . '/operation/' . date('Ymd');
      // QrCode::format('png')->generate('Hello, world!', public_path('qrcode.png'));
      QrCode::format('png')
        ->encoding('UTF-8')
        ->size(600)
        // ->color(255, 0, 255)
        ->margin(2)
        ->generate($content, $fileName);
      // Log::error($hahh);

      $filePath = Storage::putFile($ossFolder, $fileName);
      unlink($fileName);
      return $filePath;
    } catch (Exception $e) {
      Log::error("二维码生成" . $e);
      return false;
    }
  }
}
