<?php
namespace App\Api\Controllers\Operation;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Tenant\TenantLeasebackModel;
use App\Api\Models\Common\Contact as ContactModel;
use App\Api\Services\CustomerInfoService;
use App\Api\Services\CustomerService;
use App\Api\Services\Contract\ContractService;
use App\Api\Services\Tenant\TenantService;
use App\Enums\AppEnum;
/**
 * 租户账单
 */

class TenantBillController
{

  function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if(!$this->uid){
        return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->user = auth('api')->user();
    $this->parent_type = AppEnum::Tenant;
  }

  public function createBill(Request $request)
  {
    if (!$request->tenantId) {
        $tenantId = 0;
    }
    $billService = new \App\Api\Services\Tenant\BillService;
    return $billService->createBill($tenantId,$request->month);
  }
}