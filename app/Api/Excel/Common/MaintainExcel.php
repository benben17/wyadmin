<?php

namespace App\Api\Excel\Common;

use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MaintainExcel implements FromArray, WithHeadings, WithMapping
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
    // foreach ($this->data as $item) {
    //   $result[] = [
    //     'proj_name' => $item['proj_name'],
    //     'build_type' => $item['build_type'],
    //     'build_no' => $item['build_no'],
    //     'floor_count' => $item['floor_count'],
    //     'build_area' => $item['build_area'],
    //     'total_area' => $item['total_area'],
    //     // 'total_rooms' => $item['total_rooms'],
    //     'free_area' => $item['free_area'],
    //     'build_room_count' => $item['build_room_count'],
    //     'free_room_count' => $item['free_room_count'],
    //     'build_usage' => $item['build_usage'],
    //   ];
    // }

    return $this->data;
  }

  public function headings(): array
  {
    return [
      '#',
      "渠道名称",
      "联系人",
      "联系电话",
      "维护类型",
      "维护时间",
      "维护记录",
      "录入人",
      "维护部门",
      "维护反馈",
      "维护次数"
    ];
  }
  public function map($row): array
  {
    // 输出调试信息
    // dd($row);

    // 返回所需字段，确保与headings方法中的字段一致
    return [
      '=ROW()-1',
      $row['name'],
      $row['maintain_user'],
      $row['maintain_phone'],
      $row['maintain_type'],
      $row['maintain_date'],
      $row['maintain_record'],
      $row['c_username'],
      $row['maintain_depart'],
      $row['maintain_feedback'],
      $row['times']
    ];
  }
}
