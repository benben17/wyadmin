<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UserExport implements FromArray, WithHeadings
{

  protected $data;

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
    // 返回 Excel 表格的标题行（表头）
    return [
      'ID',
      'Name',
      'Email',
      // 添加其他列标题
    ];
  }
}
