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
  }
}
