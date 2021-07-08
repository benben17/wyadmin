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
use App\Api\Models\Tenant\TenantBillDetail;
use App\Api\Services\CustomerInfoService;
use App\Api\Services\CustomerService;
use App\Api\Services\Contract\ContractService;
use App\Api\Services\Tenant\TenantBillService;
use App\Api\Services\Tenant\TenantService;
use App\Enums\AppEnum;

/**
 * 租户账单
 */

class TenantBillController extends BaseController
{

  function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
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
  }

  public function list(Request $request)
  {
    $billDetail = new TenantBillService;
    $data = $billDetail->billModel()->get();
    return $this->success($data);
  }

  public function billDetailList(Request $request)
  {
    $billDetail = new TenantBillService;
    $data = $billDetail->billDetailModel()->get();
    return $this->success($data);
  }
}
