<?php

namespace App\Api\Services\Import;

use Maatwebsite\Excel\Concerns\ToModel;
use App\Api\Models\Common\Maintain;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * 维护导入
 */
class MaintainImport implements ToModel, WithHeadingRow
{
  public function model(array $row)
  {
    return new Maintain([
      'maintain_date'     => $row[0],
      'maintain_user'     => $row[1],
      'maintain_type'     => $row[2],
      'maintain_record'   => $row[3],
      'maintain_feedback' => $row[4],
      'maintain_depart'   => $row[5],
    ]);
  }
}
