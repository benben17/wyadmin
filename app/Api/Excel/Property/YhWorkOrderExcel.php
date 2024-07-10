<?php

namespace App\Api\Excel\Property;

use Maatwebsite\Excel\Excel;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class YhWorkOrderExcel implements FromArray, WithHeadings, WithMapping, WithDrawings
{
  use Exportable;

  protected $data;

  private $writerType = Excel::XLSX;

  private $headers = [
    'Content-Type' => 'text/csv',
  ];

  public function __construct(array $data)
  {
    $this->data = $data;
  }

  public function array(): array
  {
    return $this->data;
  }

  public function headings(): array
  {
    return [
      '序号',
      '单号',
      '状态',
      '隐患区域',
      '隐患来源',
      '隐患等级',
      '治理要求',
      '上报时间',
      '上报图片',
      '位置',
      '整改时间',
      '整改图片',
      '隐患问题',
      '隐患类型',
      '上报人',
      '整改结果',
    ];
  }

  public function map($row): array
  {
    return [
      '=ROW()-1',
      $row['order_no'],
      $row['status_label'],
      $row['tenant_name'],
      $row['check_type'],
      $row['hazard_level'],
      $row['process_type'],
      $row['open_time'],
      '', // 上报图片占位符
      $row['position'],
      $row['dispatch_time'],
      '', // 整改图片占位符
      $row['hazard_issues'],
      $row['hazard_type'],
      $row['open_person'],
      $row['process_result'],
    ];
  }

  public function drawings()
  {
    $drawings = [];

    foreach ($this->data as $index => $row) {
      if (!empty($row['pic'])) {
        $picPath = $this->downloadImage($row['pic_full']);
        $drawing = new Drawing();
        $drawing->setName('上报图片');
        $drawing->setDescription('上报图片');
        $drawing->setPath($picPath);
        $drawing->setHeight(50);
        $drawing->setCoordinates('I' . ($index + 2));
        $drawings[] = $drawing;
      }

      if (!empty($row['process_pic'])) {
        $processPicPath = $this->downloadImage($row['process_pic_full']);
        $drawing = new Drawing();
        $drawing->setName('整改图片');
        $drawing->setDescription('整改图片');
        $drawing->setPath($processPicPath);
        $drawing->setHeight(50);
        $drawing->setWidth(50); // 设置图片宽度
        $drawing->setCoordinates('L' . ($index + 2));
        $drawings[] = $drawing;
      }
    }

    return $drawings;
  }

  public function registerEvents(): array
  {
    return [
      AfterSheet::class => function (AfterSheet $event) {
        $sheet = $event->sheet->getDelegate();

        // 设置列宽
        $columns = range('A', 'Z'); // 假设有16列
        foreach ($columns as $column) {
          $sheet->getColumnDimension($column)->setWidth(30); // 设置宽度为20
        }

        // 设置行高
        $rowCount = count($this->data) + 1; // 加上标题行
        for ($row = 2; $row <= $rowCount; $row++) {
          $sheet->getRowDimension($row)->setRowHeight(80); // 设置高度为50
        }
      },
    ];
  }

  private function downloadImage($url)
  {
    try {
      // url 逗号分隔，取第一个
      $url = $url[0];
      // Log::info('download image url: ' . $url);
      $imageContent = file_get_contents($url);
      $parsedUrl = parse_url($url);
      $fileName = pathinfo($parsedUrl['path'], PATHINFO_BASENAME);
      $imagePath = sys_get_temp_dir() . '/' . $fileName;
      file_put_contents($imagePath, $imageContent);
      return $imagePath;
    } catch (\Exception $e) {
      Log::error('download image error: ' . $e->getMessage());
      return '';
    }
  }
}
