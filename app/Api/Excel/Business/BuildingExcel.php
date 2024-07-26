<?php

namespace App\Api\Excel\Business;

use App\Api\Models\Building;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BuildingExcel implements FromArray, WithHeadings, WithMapping
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
      "项目名称",
      "楼宇类型",
      "楼号",
      "楼层总数",
      "建筑面积(m²)",
      "管理面积(m²)",
      "空闲面积(m²)",
      "总房源数",
      "可租房源数",
      "建筑用途"
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
      $row['build_type'],
      $row['build_no'],
      $row['floor_count'],
      $row['build_area'],
      $row['total_area'],
      // $row['total_rooms'],
      $row['free_area'],
      $row['build_room_count'],
      $row['free_room_count'],
      $row['build_usage'],
    ];
  }


  public function model(array $row, $user)
  {
    return new Building([
      'company_id' => $user->company_id,
      'proj_id'    => $user['proj_id'],
      'proj_name'  => $row[1],
      'build_type' => $row[2],
      'build_no'   => $row[3],
      // 'floor_count' => $row[4],
      'build_area'       => $row[5],
      'total_area'       => $row[6],
      'free_area'        => $row[7],
      // 'build_room_count' => $row[8],
      // 'free_room_count'  => $row[9],
      'build_usage'      => $row[10],
      'c_uid'            => $user['id'],
      'created_at'       => now(),
    ]);
  }
}
