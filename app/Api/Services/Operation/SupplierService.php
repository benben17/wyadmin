<?php

namespace App\Api\Services\Operation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Models\Operation\Supplier as SupplierModel;
use App\Api\Services\Common\MessageService;
use App\Api\Services\Common\ContactService;
use App\Enums\AppEnum;
use Exception;

/**
 *  供应商管理
 */
class SupplierService
{
  public function supplierModel()
  {
    $model = new SupplierModel;
    return $model;
  }

  /** 保存供应商 */
  public function saveSupplier($DA, $user, $type = 1)
  {
    try {
      DB::transaction(function () use ($DA, $user, $type) {
        if (isset($DA['id']) && $DA['id'] > 0) {
          $supplier = $this->supplierModel()->find($DA['id']);
          if (!$supplier) {
            $supplier = $this->supplierModel();
            $supplier->c_username = $user['realname'];
          }
        } else {
          $supplier = $this->supplierModel();
          $supplier->c_uid = $user['id'];
          $supplier->c_username = $user['realname'];
        }
        $supplier->company_id        = $user['company_id'];
        // $supplier->proj_id           = $DA['proj_id'];
        $supplier->name              = isset($DA['name']) ? $DA['name'] : "";
        $supplier->supplier_type     = isset($DA['supplier_type']) ? $DA['supplier_type'] : "";
        $supplier->maintain_depart   = isset($DA['maintain_depart']) ? $DA['maintain_depart'] : "";
        $supplier->service_content   = isset($DA['service_content']) ? $DA['service_content'] : "";
        $supplier->contract_info     = isset($DA['contract_info']) ? $DA['contract_info'] : "";
        $supplier->main_business     = isset($DA['main_business']) ? $DA['main_business'] : "";
        $supplier->business_license  = isset($DA['business_license']) ? $DA['business_license'] : "";
        $supplier->operation_license = isset($DA['operation_license']) ? $DA['operation_license'] : "";
        $supplier->do_project        = isset($DA['do_project']) ? $DA['do_project'] : "";
        $supplier->register_capital  = isset($DA['register_capital']) ? $DA['register_capital'] : "";
        $supplier->register_address  = isset($DA['register_address']) ? $DA['register_address'] : "";
        $supplier->bank_name         = isset($DA['bank_name']) ? $DA['bank_name'] : "";
        $supplier->account_number    = isset($DA['account_number']) ? $DA['account_number'] : "";
        $supplier->credit_code       = isset($DA['credit_code']) ? $DA['credit_code'] : "";
        $supplier->remark            = isset($DA['remark']) ? $DA['remark'] : "";
        $res = $supplier->save();
        if ($res &&  $DA['contacts']) {
          $contact = new ContactService;
          // 更新供应商的时候删除所有的联系人
          if ($type == 2) {
            $contact->delete($supplier->id);
          }
          $user['parent_type'] = AppEnum::Supplier;
          $contacts = formatContact($DA['contacts'], $supplier->id, $user);

          if ($contacts) {

            $contact->saveAll($contacts);
          }
        }
      });
      return true;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }
}
