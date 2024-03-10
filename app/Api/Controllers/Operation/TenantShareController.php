<?php

namespace App\Api\Controllers\Operation;

use JWTAuth;
use Exception;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Models\Bill\TenantShareFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Contract\ContractService;
use App\Api\Services\Tenant\BaseInfoService;
use App\Api\Services\Tenant\TenantShareService;
use App\Api\Services\Tenant\TenantService;
use App\Enums\AppEnum;
use Encore\Admin\Grid\Filter\Where;

/**
 *  租户分摊
 */
class TenantShareController extends BaseController
{
    private $parentType;
    private $tenantService;
    private $tenantBillService;
    private $tenantShareService;
    public function __construct()
    {
        parent::__construct();
        $this->parentType = AppEnum::Tenant;
        $this->tenantShareService = new TenantShareService;
        $this->tenantBillService = new TenantBillService;
        $this->tenantService = new TenantService;
    }

    /**
     * @OA\Post(
     *     path="/api/operation/tenant/share/list",
     *     tags={"租户分摊"},
     *     summary="租户列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize","orderBy","order"},
     *       @OA\Property(property="name",type="String",description="客户名称")
     *     ),
     *       example={}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function list(Request $request)
    {
        $pagesize = $request->input('pagesize');
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config('per_size');
        }
        if ($pagesize == '-1') {
            $pagesize = config('export_rows');
        }
        $map = array();
        $map['parent_id'] = 0;
        // 排序字段
        if ($request->input('orderBy')) {
            $orderBy = $request->input('orderBy');
        } else {
            $orderBy = 'created_at';
        }
        // 排序方式desc 倒叙 asc 正序
        if ($request->input('order')) {
            $order = $request->input('order');
        } else {
            $order = 'desc';
        }

        DB::enableQueryLog();

        $result = $this->tenantService->tenantModel()->get();
        // ->where(function ($q) use($request){});


        // return response()->json(DB::getQueryLog());

        $data = $this->handleBackData($result);
        return $this->success($data);
    }





    /**
     * @OA\Post(
     *     path="/api/operation/tenant/share/show",
     *     tags={"租户分摊"},
     *     summary="根据租户获取客户信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="客户id"
     *       )
     *     ),
     *       example={"id":1}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function show(Request $request)
    {
        $validatedData = $request->validate([
            'tenant_id' => 'required|numeric|min:1',
        ]);

        $contractService = new ContractService;
        DB::enableQueryLog();
        $data = $this->tenantShareService->model()
            ->where(function ($q) use ($request) {
                $request->tenant_id && $q->where('tenant_id', $request->tenant_id);
                // $request->contract_id && $q->where('contract_id', $request->contract_id);
            })->first()->toArray();


        // return response()->json(DB::getQueryLog());
        if ($data) {
            $data['fee_list_json'] = json_decode($data['fee_list']);
        }

        return $this->success($data);
    }


    /**
     * @OA\Post(
     *     path="/api/operation/tenant/share/fee/list",
     *     tags={"租户分摊"},
     *     summary="租户应收 应收未收列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"contract_id","fee_start_date"},
     *       @OA\Property(
     *          property="contract_id",
     *          type="int",
     *          description="合同id"
     *       ),
     *      @OA\Property(property="fee_start_date",type="date",description="分摊账单开始时间"
     *       )
     *     ),
     *       example={"contract_id":1,"fee_start_date":"2024-03-01"}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function feeList(Request $request)
    {
        $msg = ['contract_id' => '合同id不允许为空'];
        $validatedData = $request->validate([
            'contract_id' => 'required|numeric|min:1'
        ]);

        $billDetailService = new TenantBillService;
        DB::enableQueryLog();
        $data = $billDetailService->BillDetailModel()
            ->where(function ($q) use ($request) {
                $q->where('contract_id', $request->contract_id);
                $q->where('bill_id', 0);
                $request->fee_type && $q->where('fee_type', $request->fee_type); // 只分摊 
                $q->whereIn('fee_type', [101, 102]);
                $q->where('status', 0);
            })
            ->whereHas('tenant', function ($q) use ($request) {
                $q->where('parent_id', '>', 0);
            })->orderBy('charge_date', 'asc')->get()->toArray();
        // return response()->json(DB::getQueryLog());

        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/operation/tenant/share/add",
     *     tags={"租户分摊"},
     *     summary="分摊租户规则新增",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"share_type","tenant_id","contract_id","invoice"},
     *       @OA\Property(property="share_type",type="int",description="客户类型 1:比例 2 固定金额"),
     *       @OA\Property(property="tenant_id",type="int",description="分摊租户id"),
     *       @OA\Property(property="contract_id",type="int",description="合同id"),
     *       @OA\Property(property="fee_type",type="int",description="分摊费用类型"),
     *       @OA\Property(property="share_rate",type="float",description="分摊比例，最大两位小数"),
     *       @OA\Property(property="share_amount",type="float",description="分摊金额"),
     *       @OA\Property(property="remark",type="String",description="分摊备注"),
     *     ),
     *       example={
     *       "parent_tenant_id":5,
     *          "contract_id":6,
     *          "share_type":2,
     *          "share_rules":""}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function tenantShareStore(Request $request)
    {
        $msg = ['contract_id' => '合同id不允许为空'];
        $validatedData = $request->validate([
            'contract_id' => 'required|numeric|min:1',
            'share_list' => 'required|array',
            'parent_tenant_id' => 'required'
        ], $msg);

        $DA = $request->toArray();
        DB::enableQueryLog();
        try {
            $user = $this->user;
            DB::transaction(function () use ($DA, $user) {
                foreach ($DA['share_list'] as $share) {
                    $primaryTenant = $DA['parent_tenant_id'];
                    $share['contract_id'] = $DA['contract_id'];
                    $this->tenantShareService->saveShareFee($share, $this->user);

                    if ($primaryTenant === $share['tenant_id']) {
                        // 处理 最新的应收
                        foreach ($share['fee_list'] as $k1 => $v1) {
                            $updateData = ['amount' => $v1['share_amount']];
                            $this->tenantBillService->updateShareBillDetail($v1['id'], $updateData);
                        }
                    } else {
                        // 处理分摊租户
                        foreach ($share['fee_list'] as $k => &$v) {
                            $v['tenant_id'] = $share['tenant_id'];
                            $v['tenant_name'] = $share['tenant_name'];
                            $v['amount'] = $v['share_amount'];
                            $v['contract_id'] = $DA['contract_id'];
                        }
                        Log::error(json_encode($share['fee_list']));
                        $newFeeList = $this->tenantBillService->formatBillDetail($share['fee_list'], $user);
                        $this->tenantBillService->billDetailModel()->addAll($newFeeList);
                        $updateTenant = ['parent_id' => $primaryTenant];
                        $this->tenantService->tenantModel()->update($updateTenant);
                    }
                }
            }, 2);
            return $this->success("分摊处理成功");
        } catch (Exception $e) {
            Log::error($e);
            return $this->error("分摊处理失败" . $e);
        }


        // return response()->json(DB::getQueryLog());


    }
}
