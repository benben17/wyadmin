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
use App\Api\Services\Company\VariableService;
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



  /**
   * operation/tenant/bill/sync
   *
   * @Author leezhua
   * @DateTime 2021-07-24
   *
   * @return void
   */
  public function syncContractBill()
  {
    try {
      $contractService = new ContractService;
      $billService = new TenantBillService;
      $shareRule = new ShareRuleService;

      $chargeDate = date('Y-m-d');
      // $chargeDate = "2021-09-01";

      $bills = $contractService->contractBillModel()
        ->where('charge_date', $chargeDate)
        ->where('is_sync', 0)->exists();
      if ($bills) {
        // DB::transaction(function () use ($chargeDate, $billService, $contractService, $shareRule) {
        $billList = $contractService->contractBillModel()
          ->where('charge_date', $chargeDate)
          ->where('is_sync', 0)->get();
        foreach ($billList as $k => $bill) {
          $contractBillId = $bill['id'];

          $user['id']         = isset($bill['c_uid']) ? $bill['c_uid'] : 0;
          $user['company_id'] = $bill['company_id'];

          // 判断租户是否有分摊
          $shareCount  = $shareRule->model()->where('contract_id', $bill['contract_id'])->where('fee_type', $bill['fee_type'])->count();
          if ($shareCount > 0) {
            $shareList = $shareRule->model()->where('contract_id', $bill['contract_id'])
              ->where('fee_type', $bill['fee_type'])->get();
            $shareTotalAmt = 0.00;
            $shareAmount = 0.00;
            foreach ($shareList as $key => $share) {

              $shareBill = $bill;
              unset($shareBill['id']);
              if ($share['share_type'] == 1) {  // 比例
                // 分摊租户账单保存
                $shareAmount = numFormat($shareBill['amount'] * $share['share_num'] / 100);
              } else if ($share['share_type'] == 2) {  // 固定金额
                $shareAmount = numFormat($share['share_num']);
              } else if ($share['share_type'] == 3) { // 面积
                $shareAmount = $this->getMonthPrice($shareBill, $share['share_num']);
              }

              $shareBill['tenant_id'] = $share['tenant_id'];
              $shareBill['tenant_name'] = getTenantNameById($share['tenant_id']);
              $shareBill['amount'] = $shareAmount;
              // 保存分摊租户账单
              $billService->saveBillDetail($shareBill, $user);
              $shareTotalAmt += $shareAmount;
            }
            $bill =  $contractService->contractBillModel()->find($contractBillId);
            $bill['amount'] = numFormat($bill['amount'] - $shareTotalAmt);
          }
          unset($bill['id']);
          $billService->saveBillDetail($bill, $user);

          $billUpdateData['is_sync'] = 0;
          Log::error("账单更新ID:" . $contractBillId);
          $contractService->contractBillModel()->where('id', $contractBillId)->update($billUpdateData);
        }
        // }, 3);
      } else {
        Log::error(nowYmd() . "今天未发现账单");
      }
    } catch (Exception $th) {
      Log::error("账单同步失败" . $th);
    }
  }

  /**
   * 获取账单金额
   *
   * @Author leezhua
   * @DateTime 2021-07-24
   * @param [type] $rule
   * @param [type] $shareNum
   *
   * @return void
   */
  private function getMonthPrice(array $rule, float $shareNum): float
  {
    $monthPrice = 0.00;
    if ($rule['price_type'] == 1) {
      $yearDays = getVariable($rule['company_id'], 'year_days');
      $monthPrice = numFormat($rule['unit_price'] * $shareNum * $yearDays / 12);
    } else if ($rule['price_type'] == 2) {
      $monthPrice = numFormat($rule['unit_price'] * $shareNum);
    }
    return $monthPrice;
  }
}
