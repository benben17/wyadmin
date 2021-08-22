<?php

namespace App\Api\Services\Business;

use App\Api\Models\Business\CusClue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use Exception;

/**
 * 客户线索管理
 */
class CusClueService
{
  public function model()
  {
    return new CusClue;
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
    $income->proj_id = isset($DA['proj_id']) ? $DA['proj_id'] : 0;
    $income->tenant_id = isset($DA['tenant_id']) ? $DA['tenant_id'] : 0;
    $income->name = isset($DA['name']) ? $DA['name'] : "";
    $income->clue_type = $DA['clue_type'];
    $income->clue_time = isset($DA['clue_time']) ? $DA['clue_time'] : nowTime();
    $income->phone = isset($DA['phone']) ? $DA['phone'] : "";
    $income->demand_area = isset($DA['demand_area']) ? $DA['demand_area'] : "";
    $income->status = isset($DA['status']) ? $DA['status'] : 1;
    $income->remark = isset($DA['remark']) ? $DA['remark'] : "";
    $res = $income->save();
    return $res;
  }

  /**
   * 更新线索状态
   *
   * @Author leezhua
   * @DateTime 2021-08-21
   * @param [type] $status
   * @param [type] $clueId
   * @param [type] $tenantId
   *
   * @return void
   */
  public function changeStatus($clueId, $status = 0, $tenantId = 0)
  {
    try {

      if ($tenantId > 0) {
        $data['tenant_id'] = $tenantId;
        $data['change_time'] = nowYmd();
        $data['status'] = 2;
      }
      if ($status > 0) {
        $data['status'] = $status;
      }

      return $this->model()->where('id', $clueId)->update($data);
    } catch (Exception $e) {
      Log::error("线索更新失败" . $e);
      throw new Exception("线索更新失败" . $e);
      return false;
    }
  }
}
