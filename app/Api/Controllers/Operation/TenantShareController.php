<?php

namespace App\Api\Controllers\Operation;

use JWTAuth;
use Exception;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Models\Bill\TenantShareRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Common\Contact as ContactModel;
use App\Api\Services\Contract\ContractService;
use App\Api\Services\Tenant\BaseInfoService;
use App\Api\Services\Tenant\ShareRuleService;
use App\Api\Services\Tenant\TenantService;
use App\Enums\AppEnum;

/**
 *  租户分摊
 */
class TenantShareController extends BaseController
{
    public function __construct()
    {
        $this->uid  = auth()->payload()->get('sub');
        if (!$this->uid) {
            return $this->error('用户信息错误');
        }
        $this->company_id = getCompanyId($this->uid);
        $this->user = auth('api')->user();
        $this->parent_type = AppEnum::Tenant;
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

        $result = $this->tenantService->tenantModel();

        // return response()->json(DB::getQueryLog());

        $data = $this->handleBackData($result);
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
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'parent_tenant_id' => 'required',
            'contract_id' => 'required|gt:0',
            // 'share_type' => 'required|in:1,2,3',
            'share_list' => 'required|array',
            // 'share_rules.tenant_id' => 'required|gt:0',
            // 'share_rules.contract_id' => 'required|gt:0',
            // 'share_rules.share_type' => 'required|in:1,2,3',
            // 'share_rules.fee_type' => 'required|gt:0',
        ]);
        $DA = $request->toArray();



        try {
            DB::transaction(function () use ($DA) {
                $shareService = new ShareRuleService;
                $res = $shareService->batchSaveShare($DA, $this->user);
                // $contractService = new ContractService;
                // $contract = $contractService->model()->find($DA['contract_id']);

                $this->tenantService->tenantModel()->whereIn('id', $res['tenantIds'])
                    ->update(['parent_id' => $DA['parent_tenant_id']]);
            });
            return $this->success('租户分摊新增成功。');
        } catch (Exception $e) {
            Log::error("租户分摊" . $e);
            return $this->error('租户分摊新增失败');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/operation/tenant/share/edit",
     *     tags={"租户"},
     *     summary="租户编辑",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id","cus_type","cus_name"},
     *       @OA\Property(property="id",type="int",description="客户id"),
     *       @OA\Property(property="cus_type",type="int",description="客户类型 1:公司 2 个人"),
     *       @OA\Property(property="cus_name",type="String",description="客户名称")
     *     ),
     *       example={
     *              "id":1,"cus_type":1,"cus_name":"公司客户"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function update(Request $request)
    {
        $validatedData = $request->validate([
            'parent_tenant_id' => 'required',
            'contract_id' => 'required|gt:0',
            'share_type' => 'required|in:1,2,3',
            'share_list' => 'required|array',
            // 'share_rules.tenant_id' => 'required|gt:0',
            // 'share_rules.contract_id' => 'required|gt:0',
            // 'share_rules.share_type' => 'required|in:1,2,3',
            // 'share_rules.fee_type' => 'required|gt:0',
        ]);
        $DA = $request->toArray();
        // Log::error($DA['share_type']);
        try {
            DB::transaction(function () use ($DA) {
                $shareService = new ShareRuleService;
                $shareService->model()->where('contract_id', $DA['contract_id'])->delete();
                $contractService = new ContractService;
                $contractService->model()->whereId($DA['contract_id'])->update(['share_type' => $DA['share_type']]);
                $res = $shareService->batchSaveShare($DA, $this->user);

                if (!$res['tenantIds']) {
                    throw new Exception("分摊租户为空");
                }
                // 更新原有分摊租户信息
                $this->tenantService->tenantModel()->where('parent_id', $DA['parent_tenant_id'])
                    ->update(['parent_id' => 0]);
                // 更新分摊租户 父id
                $this->tenantService->tenantModel()->whereIn('id', $res['tenantIds'])
                    ->update(['parent_id' => $DA['parent_tenant_id']]);
            });
            return $this->success('租户分摊编辑成功。');
        } catch (Exception $e) {
            Log::error("租户分摊" . $e);
            return $this->error('租户分摊编辑失败');
        }
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
            'id' => 'required|numeric|min:1',
        ]);

        $contractService = new ContractService;
        DB::enableQueryLog();
        $data = $contractService->model()
            ->select('id', 'tenant_id', 'tenant_name', 'contract_no', 'proj_id', 'share_type')
            ->where('contract_state', 2)
            // ->with('shareRule')
            ->with('contractRoom')
            ->find($request->id);
        // return response()->json(DB::getQueryLog());

        $shareService = new ShareRuleService;
        $shareList = $shareService->model()->selectRaw('tenant_id,contract_id,share_type')
            ->groupBy('tenant_id', 'share_type')->get();
        foreach ($shareList as $k => &$v) {
            $map['contract_id'] = $v['contract_id'];
            $map['tenant_id']   = $v['tenant_id'];
            $v['share_rule'] =  $shareService->model()->where($map)->get();
        }
        $data['share_list'] = $shareList;
        return $this->success($data);
    }
}
