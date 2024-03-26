<?php

namespace App\Exports;

use App\Api\Models\Building;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BuildingImport implements ToCollection
{
  public function collection(Collection $rows)
  {
    foreach ($rows as $row) {
      // 在这里处理每一行的数据，$row 是一个包含单元格数据的集合（Collection）
      // 例如，$row[0] 是第一列的数据，$row[1] 是第二列的数据，以此类推
      // 你可以根据你的需求来处理导入的数据
      // 例如将数据存储到数据库中或者进行其他逻辑处理
    }
  }
}
