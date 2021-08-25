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
  public function exportExcel(string $fileName, array $title, array $data)
  {
    try {
      return Excel::create($fileName, function ($excel) use ($data, $title) {
        /** @var LaravelExcelWriter $excel */
        $excel->sheet('sheet', function ($sheet) use ($data, $title) {
          /** @var LaravelExcelWorksheet $sheet */
          $column = $this->cellLetter[count($data[0]) - 1];

          try {
            $sheet->fromArray($data, null, 'A1', true, false);
          } catch (PHPExcel_Exception $e) {
            throw new LaravelExcelException($e->getMessage());
          }

          /** 此为设置整体样式 */
          $sheet->setStyle([
            'font' => [
              'name' => 'Calibri',
              'size' => 12,
              'bold' => false,
            ]
          ])
            ->prependRow($title)
            ->row(1, function ($row) {
              /** @var CellWriter $row */
              $row->setFont(array(   //设置标题的样式
                'family' => 'Calibri',
                'size' => '16',
                'bold' => true
              ));
            })
            ->mergeCells('A1:' . $column . '1')
            ->cell('A2:' . $column . '2', function ($cells) {
              /** @var CellWriter $cells */
              $cells->setBackground('#AAAAFF');
            })->setHeight(1, 30)
            ->setAutoFilter('A2:' . $column . '2');  //设置自动过滤

          /** 此为针对每行的高宽进行设置 */
          for ($i = 2; $i <= count($data[0]) + 1; $i++) {
            $sheet->setHeight($i, 20);
            $sheet->setWidth($this->cellLetter[$i - 1], 30);
            $sheet->row($i - 1, function ($row) {
              /** @var CellWriter $row */
              $row->setAlignment('center');
              $row->setValignment('center');
            });
          }
        });
      })->export('xlsx');
    } catch (LaravelExcelException $e) {
      throw new LaravelExcelException($e->getMessage());
    }
  }
}
