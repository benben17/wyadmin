<?php

namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Api\Models\Tenant\Tenant as TenantModel;
use App\Api\Models\Tenant\TenantContract;
use App\Api\Models\Tenant\TenantContractRoom;
use App\Api\Models\Contract\ContractRoom;

class TenantContractService
{


  public function model()
  {
    $model = new TenantContract;
    return $model;
  }

  public function contractRoomModel()
  {
    $model = new TenantContractRoom;
    return $model;
  }


  /**
   * 租户合同保存
   * @Author   leezhua
   * @DateTime 2021-05-31
   * @param    [type]     $DA [description]
   * @return   [type]         [description]
   */

  public function save($DA, $user, $type = "add")
  {
    try {
      // DB::transaction(function () use ($DA, $user, $type) {
      if ($type == "add") {
        $contract = $this->model();
        $contract->c_uid = $user->id;
        $contract->company_id = $user->company_id;
      } else {
        //编辑
        $contract = $this->model()->find($DA['id']);
        $contract->u_uid = $user->id;
      }
      // 合同编号不设置的时候系统自动生成
      if (!isset($DA['contract_no']) || empty($DA['contract_no'])) {
        $contract_prefix = getVariable($user->company_id, 'contract_prefix');
        $contract->contract_no = $contract_prefix . getContractNo();
      } else {
        $contract->contract_no = $DA['contract_no'];
      }
      $contract->free_type = isset($DA['free_type']) ? $DA['free_type'] : 1;
      $contract->proj_id = $DA['proj_id'];
      $contract->contract_state = $DA['contract_state'];
      $contract->contract_type =  $DA['contract_type'];
      $contract->violate_rate = isset($DA['violate_rate']) ? $DA['violate_rate'] : 0;
      $contract->sign_date = $DA['sign_date'];
      $contract->start_date = $DA['start_date'];
      $contract->end_date = $DA['end_date'];
      $contract->tenant_id = $DA['tenant_id'];
      $contract->lease_term = $DA['lease_term'];
      $contract->sign_area = $DA['sign_area'];
      $contract->rental_deposit_amount = isset($DA['rental_deposit_amount']) ? $DA['rental_deposit_amount'] : 0.00;
      $contract->rental_deposit_month = isset($DA['rental_deposit_month']) ? $DA['rental_deposit_month'] : 0;
      if (isset($DA['increase_date'])) {
        $contract->increase_date = $DA['increase_date'];
      }
      $contract->increase_rate = isset($DA['increase_rate']) ? $DA['increase_rate'] : 0;
      $contract->increase_year = isset($DA['increase_year']) ? $DA['increase_year'] : 0;
      $contract->bill_day = $DA['bill_day'];
      $contract->bill_type = isset($DA['bill_type']) ? $DA['bill_type'] : 1;
      $contract->ahead_pay_month = $DA['ahead_pay_month'];
      $contract->rental_price = $DA['rental_price'];
      $contract->rental_price_type = $DA['rental_price_type'];
      $contract->management_price = isset($DA['management_price']) ? $DA['management_price'] : 0.00;
      $contract->management_month_amount = isset($DA['management_month_amount']) ? $DA['management_month_amount'] : 0;
      $contract->pay_method = $DA['pay_method'];
      $contract->rental_month_amount = $DA['rental_month_amount'];
      $contract->manager_deposit_month = isset($DA['manager_deposit_month']) ? $DA['manager_deposit_month'] : 0;
      $contract->manager_deposit_amount = isset($DA['manager_deposit_amount']) ? $DA['manager_deposit_amount'] : 0.00;
      $contract->increase_show = isset($DA['increase_show']) ? $DA['increase_show'] : 0;
      $contract->manager_show = isset($DA['manager_show']) ? $DA['manager_show'] : 0;
      $contract->rental_usage = isset($DA['rental_usage']) ? $DA['rental_usage'] : "";
      $contract->room_type    = isset($DA['room_type']) ? $DA['room_type'] : 1;
      $contract->save();

      // });
      return $contract;
    } catch (Exception $e) {
      throw new Exception($e, 1);
      Log::error($e->getMessage());
      return false;
    }
  }
}
