<?php

namespace App\Api\Excel\Business;

use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;


class BuildingRoomExcel implements FromArray, WithHeadings, WithMapping
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
      '#',
      "项目名称",
      "楼宇",
      "楼层号",
      "房间号",
      "房间面积(m²)",
      "出租单价(元)",
      "装修状态(m²)",
      "房间状态",
      "带看次数",
      "可租日期",
      "标签"
    ];
  }
  public function map($row): array
  {
    // 输出调试信息
    // dd($row);

    // 返回所需字段，确保与headings方法中的字段一致
    return [
      '=ROW()-1',
      $row['proj_name'],
      $row['build_no'],
      $row['floor_no'],
      $row['room_no'],
      $row['room_area'],
      $row['room_price'],
      $row['room_trim_state'],
      $row['roomState'],
      $row['view_num'],
      $row['rentable_date'],
      $row['room_tags'],
    ];
  }
}
