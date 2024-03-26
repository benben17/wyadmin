<?php

namespace App\Api\Excel\Business;

use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;


class CusClueExcel implements FromArray, WithHeadings, WithMapping
{
  use Exportable;
  protected $data;
  protected $titles;

  private $writerType = Excel::XLSX;


  /**
   * Optional headers
   */
  private $headers = [
    'Content-Type' => 'text/csv',
  ];



  public function __construct(array $data)
  {
    $this->data = $data;
    // $this->titles = $titles;
  }

  public function array(): array
  {


    return $this->data;
  }

  public function headings(): array
  {
    return [
      "线索来源",
      "线索时间",
      "状态",
      "姓名",
      "联系电话",
      "备注",
      "录入人",

    ];
  }
  public function map($row): array
  {
    // 输出调试信息
    // dd($row);

    // 返回所需字段，确保与headings方法中的字段一致
    return [
      $row['clue_type_label'],
      $row['clue_time'],
      $row['status_label'],
      $row['name'],
      $row['phone'],
      $row['remark'],
      $row['c_user'],
    ];
  }
}
