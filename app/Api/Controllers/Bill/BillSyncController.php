<?php

namespace App\Api\Controllers\Bill;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Models\Tenant\TenantShare;
use App\Api\Services\Contract\ContractService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Tenant\ShareRuleService;
use Exception;

/**
 * 租户账单
 */

class BillSyncController extends BaseController
{

  function __construct()
  {
    // $this->uid  = auth()->payload()->get('sub');
    // if (!$this->uid) {
    //   return $this->error('用户信息错误');
    // }
    // $this->company_id = getCompanyId($this->uid);
    // $this->user = auth('api')->user();
    // $this->parent_type = AppEnum::Tenant;
  }




  public function syncContractBill()
  {
    try {
      $contractService = new ContractService;
      $billService = new TenantBillService;
      $shareRule = new ShareRuleService;

      $chargeDate = date('Y-m-d');
      Log::error($chargeDate);
      $contractBills = $contractService->contractBillModel()
        ->where('charge_date', $chargeDate)
        ->where('is_sync', 0)->get();
      // Log::error(json_encode($contractBills));
      if ($contractBills) {
        foreach ($contractBills as $k => $bill) {
          DB::transaction(function () use ($bill, $billService, $contractService, $shareRule) {

            $user['id'] = isset($bill['c_uid']) ? $bill['c_uid'] : 0;
            $user['company_id'] = $bill['company_id'];

            $shareRule =

              $billService->saveBillDetail($bill, $user);
            $contractService->contractBillModel()->find($bill['id'])->update('is_sync', 1);
          }, 3);
        }
      } else {
        Log::error(nowYmd() . "今天未发现账单");
      }
    } catch (Exception $th) {
      Log::error("账单同步失败" . $th);
    }
  }
}
