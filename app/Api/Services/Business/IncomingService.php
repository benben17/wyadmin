<?php

namespace App\Api\Services\Business;

use App\Api\Models\Business\IncomingTel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use Exception;

/**
 * 来电管理
 */
class IncomingService
{
  public function model()
  {
    return new IncomingTel;
  }

  public function save($DA, $user)
  {
    if (isset($DA['id']) &&  $DA['id'] > 0) {
      $income = $this->model()->find($DA['id']);
      $income->u_uid = $user->id;
    } else {
      $income = $this->model();
      $income->company_id = $user->company_id;
      $income->c_uid = $user->id;
    }
    $income->proj_id = isset($DA['proj_id']) ? $DA['proj_id'] : "";
    $income->name = isset($DA['name']) ? $DA['name'] : "";
    $income->income_type = $DA['income_type'];
    $income->phone = isset($DA['phone']) ? $DA['phone'] : "";
    $income->demand_area = isset($DA['demand_area']) ? $DA['demand_area'] : "";
    $income->remark = isset($DA['remark']) ? $DA['remark'] : "";
    $res = $income->save();
    return $res;
  }
}
