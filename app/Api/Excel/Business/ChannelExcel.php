<?php

namespace App\Api\Excel\Business;

use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;


class ChannelExcel implements FromArray, WithHeadings, WithMapping
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
      "渠道名称",
      "渠道类型",
      "常用联系人",
      "联系电话",
      "政策",
      "佣金",
      "	总客户数",
      "成交客户数",
      "录入人",
      "添加时间"
    ];
  }
  public function map($row): array
  {
    // 输出调试信息
    // dd($row);

    // 返回所需字段，确保与headings方法中的字段一致
    return [
      '=ROW()-1',
      $row['channel_name'],
      $row['channel_type'],
      $row['channel_contact'][0] ? $row['channel_contact'][0]['contact_name'] : "",
      $row['channel_contact'][0] ? $row['channel_contact'][0]['contact_phone'] : "",
      $row['channel_policy']['name'],
      $row['brokerage_amount'],
      // $row['total_rooms'],
      $row['customer_count'],
      $row['cus_deal_count'],
      $row['create_user'],
      $row['created_at'],
    ];
  }
}
