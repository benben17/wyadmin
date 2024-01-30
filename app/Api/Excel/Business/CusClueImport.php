<?php

namespace App\Api\Excel\Business;

use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithValidation;
use App\Api\Models\Business\CusClue;
use App\Api\Models\Company\CompanyDict;

class CusClueImport implements ToModel, WithStartRow, WithBatchInserts, SkipsEmptyRows
{

  use Importable;
  private $user;
  private $clueTypeId;

  public function __construct($user)
  {
    $this->user = $user;
  }

  public function startRow(): int
  {
    return 2;
  }
  // public function headingRow(): int
  // {
  //   return 2;
  // }

  public function model(array $row)
  {
    $this->clueTypeId = 0;

    if (!empty($row[0])) {
      $dict = CompanyDict::where([
        'dict_key' => 'clue_type',
        'dict_value' => $row[0],
      ])->first();

      $this->clueTypeId = $dict ? $dict->id : 0;
    }

    return new CusClue([
      'clue_type' => $this->clueTypeId,
      'clue_time' => $row[1],
      'status' => 1,
      'name' => $row[3],
      'phone' => $row[4],
      'remark' => $row[5],
      'company_id' => $this->user->company_id,
      'c_uid' => $this->user->id,
    ]);
  }

  public function batchSize(): int
  {
    return 1000;
  }
  // public function map($row): array
  // {
  //   // 输出调试信息
  //   // dd($row);

  //   // 返回所需字段，确保与headings方法中的字段一致
  //   return [
  //     '=ROW()-1',
  //     $row['clue_type_label'],
  //     $row['clue_time'],
  //     $row['status_label'],
  //     $row['name'],
  //     $row['phone'],
  //     $row['remark'],
  //     $row['c_user'],
  //   ];
  // }

  /**
   * implements WithUpserts
   * @return string|array 
   */
  // public function uniqueBy()
  // {
  //   return 'phone';
  // }
}
