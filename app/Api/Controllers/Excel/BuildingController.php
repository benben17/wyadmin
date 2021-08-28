<?php

namespace App\Api\Controllers\Excel;

use App\Api\Controllers\BaseController;
use App\Api\Models\BuildingFloor;
use App\Api\Services\Building\BuildingService;
use App\Exports\BuildingImport;
use App\Exports\UserImport;
use Maatwebsite\Excel\Facades\Excel;

class BuildingController extends BaseController
{
  public function __construct()
  {
    // Token 验证
    // $this->middleware('jwt.api.auth');
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
  }
  public function test()
  {
    $file = public_path('uploads/aa.xlsx');
    $data = Excel::import(new BuildingImport(), $file);

    $buildService = new BuildingService;

    foreach ($data as $k => $row) {
      if ($k == 0) {
        continue;
      }
      $res = $buildService->formatBuilding($row, $this->companyId);
      if ($res) {
        $foor['company_id'] = $this->companyId;
        $foor['build_id'] = $res->id;
        $foor['foor_no'] = $row[2];
        $foor['proj_id'] = $res->proj_id;

        BuildingFloor::insert($data);
      }
    }
  }
}
