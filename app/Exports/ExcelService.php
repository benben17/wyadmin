<?php

namespace App\Exports;

use Maatwebsite\Excel\Classes\LaravelExcelWorksheet;
use Maatwebsite\Excel\Exceptions\LaravelExcelException;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Writers\CellWriter;
use Maatwebsite\Excel\Writers\LaravelExcelWriter;
use PHPExcel_Exception;

/**
 * 维护导入
 */
class ExcelService
{
  protected $cellLetter = [
    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q',
    'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD',
    'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN',
    'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ'
  ];

  /**
   * Undocumented function
   *
   * @Author leezhua
   * @DateTime 2021-08-25
   * @param string $fileName
   * @param array $title
   * @param array $data
   *
   * @return void
   */
  public function readExcel(string $fileName)
  {
    $data = Excel::load($fileName, function ($reader) {
    }, 'GBK')->get();
    return $data;
  }
}
