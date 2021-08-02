<?php

namespace App\Api\Controllers\Operation;

use Exception;
use JWTAuth;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Contract\ContractService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Api\Services\Tenant\LeasebackService;
use App\Api\Services\Tenant\TenantService;
use App\Enums\AppEnum;

/**
 * 租户退租
 */
class LeasebackController extends BaseController
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
        $this->leasebackService = new LeasebackService;
    }

    /**
     * @OA\Post(
     *     path="/api/operation/tenant/leaseback/list",
     *     tags={"租户退租"},
     *     summary="退租租户信息列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize","orderBy","order"},
     *       @OA\Property(property="cus_name",type="int",description="客户名称"),
     *       @OA\Property(property="start_date",type="date",description="退租开始时间"),
     *       @OA\Property(property="end_date",type="date",description="退租结束时间"),
     *       @OA\Property(property="type",type="date",description="退租类型")
     *     ),
     *       example={"cus_name":"","start_date":"","end_date":""}
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
        $validatedData = $request->validate([
            'proj_ids' => 'required',
        ]);
        $pagesize = $request->input('pagesize');
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config('per_size');
        }
        if ($pagesize == '-1') {
            $pagesize = config('export_rows');
        }
        $map = array();
        if ($request->tenant_id) {
            $map['tenant_id'] = $request->tenant_id;
        }
        if ($request->type) {
            $map['type'] = $request->type;
        }
        // 排序字段
        if ($request->input('orderBy')) {
            $orderBy = $request->input('orderBy');
        } else {
            $orderBy = 'leaseback_date';
        }
        // 排序方式desc 倒叙 asc 正序
        if ($request->input('order')) {
            $order = $request->input('order');
        } else {
            $order = 'desc';
        }
        if (!is_array($request->proj_ids)) {
            $request->proj_ids = str2Array($request->proj_ids);
        }
        DB::enableQueryLog();
        $result = $this->leasebackService->leasebackModel()->where($map)
            ->whereHas('tenant', function ($q) use ($request) {
                $request->name && $q->where('name', 'like', '%' . $request->tenant_name . '%');
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
            })->with('tenant:id,tenant_no,name,proj_id,on_rent')
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();
        // return response()->json(DB::getQueryLog());
        $data = $this->handleBackData($result);
        return $this->success($data);
    }
    /**
     * @OA\Post(
     *     path="/api/operation/tenant/leaseback/add",
     *     tags={"租户退租"},
     *     summary="租户退租新增",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"type","contract_id","leaseback_date","leaseback_reason"},
     *       @OA\Property(property="type",type="int",description="退租原因1:正常退租2:提前退租"),
     *       @OA\Property(property="contract_id",type="int",description="合同id"),
     *       @OA\Property(property="leaseback_date",type="String",description="退租日期"),
     *       @OA\Property(property="leaseback_reason",type="String", description="退租原因"),
     *     ),
     *       example={"tenant_name":"租户名称","tenant_id":"1","type":1,"leaseback_date":"2021-01-01"}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'contract_id' => 'required',
            'type' => 'required',
            'leaseback_date' => 'required',
        ]);
        $res = $this->leasebackService->save($request->toArray(), $this->user);
        if ($res) {
            return $this->success('租户退租保存成功');
        } else {
            return $this->fail("租户退租失败");
        }
    }

    /**
     * @OA\Post(
     *     path="/api/operation/tenant/leaseback/edit",
     *     tags={"租户退租"},
     *     summary="租户退租编辑",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id","type","tenant_name","tenant_id","leaseback_date","leaseback_reason"},
     *      @OA\Property(property="id",type="int",description="id"),
     *       @OA\Property(property="type",type="int",description="退租原因1、正常退租2提前退租"),
     *       @OA\Property(property="tenant_name",type="String",description="租户名称"),
     *       @OA\Property(property="tenant_id",type="int",description="租户id"),
     *       @OA\Property(property="leaseback_date",type="String",description="退租日期"),
     *      @OA\Property(property="leaseback_reason",type="String", description="退租原因"),
     *     ),
     *       example={"tenant_name":"租户名称","tenant_id":"1","type":1,"leaseback_date":"2021-01-01"}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function edit(Request $request)
    {
        $validatedData = $request->validate([
            'id'            => 'required',
            'contract_id' => 'required',
            'type' => 'required',
            'leaseback_date' => 'required',
        ]);
        $DA = $request->toArray();
        $res = $this->leasebackService->save($DA, $this->user);
        if ($res) {
            return $this->success('租户退租保存成功');
        } else {
            return $this->fail("租户退租失败");
        }
    }
    /**
     * @OA\Post(
     *     path="/api/operation/tenant/leaseback/show",
     *     tags={"租户退租"},
     *     summary="租户退租",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"contract_id"},
     *      @OA\Property(property="contract_id",type="int",description="合同id"),
     *      
     *     ),
     *       example={"contract_id":"合同id"}
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
            'contract_id'            => 'required|gt:0',
        ]);
        $contractService = new ContractService;
        $tenantService = new TenantBillService;
        $data = $contractService->model()
            ->find($request->contract_id);
        $data['bills'] = $tenantService->billDetailModel()
            ->where('contract_id', $request->contract_id)->where('type', '!=', 2)
            ->where('status', 0)->get();
        $data['deposit_bills'] = $tenantService->billDetailModel()
            ->where('contract_id', $request->contract_id)->where('type', 2)
            ->where('status', 0)->get();
        return $this->success($data);
    }
}
