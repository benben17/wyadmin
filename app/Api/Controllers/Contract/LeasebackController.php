<?php

namespace App\Api\Controllers\Contract;

use JWTAuth;
use Exception;
use App\Enums\AppEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Api\Controllers\BaseController;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Tenant\LeasebackService;
use App\Api\Services\Contract\ContractService;

/**
 * 租户退租
 */
class LeasebackController extends BaseController
{
    private $parent_type;
    private $leasebackService;
    function __construct()
    {
        parent::__construct();
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

        $pagesize = $this->setPagesize($request);
        $map = [
            'tenant_id' => $request->tenant_id ?? null,
            'type' => $request->type ?? null,
        ];

        $orderBy = $request->input('orderBy', 'leaseback_date');
        $order = $request->input('order', 'desc');


        $result = $this->leasebackService->model()
            ->where(array_filter($map))
            ->whereHas('tenant', function ($q) use ($request) {
                if ($request->tenant_name) {
                    $q->where('name', 'like', '%' . $request->tenant_name . '%');
                }
                if ($request->proj_ids) {
                    $q->whereIn('proj_id', str2Array($request->proj_ids));
                }
            })
            ->where(function ($q) use ($request) {
                if ($request->start_date && $request->end_date) {
                    $q->whereBetween('leaseback_date', [$request->start_date, $request->end_date]);
                }
            })
            ->with('tenant:id,tenant_no,name,proj_id,on_rent')
            ->with('contract:id,contract_no,start_date,end_date')
            ->whereHas('contract', function ($q) use ($request) {
                if ($request->sign_start_date && $request->sign_end_date) {
                    $q->whereBetween('sign_date', [$request->sign_start_date, $request->sign_end_date]);
                }
            })
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();

        $data = $this->handleBackData($result);
        foreach ($data['result'] as &$v) {
            $v = array_merge($v, $v['contract']);
            unset($v['contract']);
        }
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
            "fee_list" => 'array'
        ]);
        $leaseback = $this->leasebackService->model()
            ->where('contract_id', $request->contract_id)->count();
        if ($leaseback > 0) {
            return $this->error("已退租，不允许重复退租！");
        }
        $res = $this->leasebackService->save($request->toArray(), $this->user);
        if ($res) {
            return $this->success('租户退租保存成功');
        } else {
            return $this->error("租户退租失败");
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

        $this->showValidate($request);
        $contractService = new ContractService;
        $data = $contractService->model()
            ->with('contractRoom')->find($request->contract_id);
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/operation/tenant/leaseback/bill",
     *     tags={"租户退租"},
     *     summary="租户退租账单",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"contract_id","leaseback_date"},
     *      @OA\Property(property="contract_id",type="int",description="合同id"),
     *      @OA\Property(property="leaseback_date",type="String",description="退租日期"),
     *     ),
     *       example={"contract_id":"合同id","leaseback_date":"2021-01-01"}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function leasebackBill(Request $request)
    {
        $this->showValidate($request);
        $request->validate([
            'leaseback_date' => 'required|date',
        ], [
            'leaseback_date.required' => '退租日期不能为空',
            'leaseback_date.date' => '退租日期格式不正确',
        ]);

        $tenantService = new TenantBillService;

        // 使用单个查询获取所有账单，并按类型和状态分组
        $bills = $tenantService->billDetailModel()
            ->where('contract_id', $request->contract_id)
            ->orderBy('fee_type', 'asc')
            ->orderBy('tenant_id', 'asc')
            ->orderBy('charge_date', 'asc')
            ->get()
            ->groupBy(['type', 'status']);

        // 使用 collect 方法简化处理逻辑
        $feeList = $bills->get(AppEnum::feeType)->get(AppEnum::feeStatusUnReceive, collect());
        $data = collect([
            'fee_list' => $tenantService->processLeaseBackFee($feeList, $request->leaseback_date),
            'deposit_fee_list' => $tenantService->processLeaseBackFee($bills->get(AppEnum::depositFeeType, collect()), $request->leaseback_date),
        ]);

        return $this->success($data);
    }

    public function leasebackReturn(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|numeric',
        ], [
            'id.required' => '合同ID是必须',
            'remark.required' => '备注是必须'
        ]);
        if (!($this->user->role_id == 2 || $this->user->is_admin == 1)) {
            return $this->error('您没有权限操作,请联系管理员处理！');
        }
        $contractService = new ContractService;
        $contract = $contractService->model()->find($request->id);
        if (!$contract) {
            return $this->error('您所选合同不存在');
        }
        if ($contract->contract_state != AppEnum::contractLeaseBack) {

            return $this->error('您所选合同不是退租合同!');
        }

        $res = $this->leasebackService->leasebackReturn($request->id, $request->remark, $this->user);
        return $res ? $this->success('合同退回成功') : $this->error('合同退回失败');
    }


    private function showValidate($request)
    {
        return $request->validate([
            'contract_id'            => 'required|gt:0',
            // 'leaseback_date'         => 'required|date',
        ], [
            // 'leaseback_date.required' => '退租日期不能为空',
            // 'leaseback_date.date'     => '退租日期格式不正确',
            'contract_id.gt'          => '合同id不能为空',
            'contract_id.required'    => '合同id不能为空',
        ]);
    }
}
