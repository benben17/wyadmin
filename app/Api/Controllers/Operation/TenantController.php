<?php

namespace App\Api\Controllers\Operation;

use JWTAuth;
use Exception;
use App\Enums\AppEnum;
use Illuminate\Http\Request;
use App\Api\Models\Tenant\Follow;
use App\Api\Models\Tenant\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Controllers\BaseController;
use App\Api\Services\Tenant\TenantService;
use App\Api\Services\Bill\TenantBillService;
use App\Api\Services\Tenant\BaseInfoService;
use App\Api\Services\Contract\ContractService;
use App\Api\Controllers\Bill\BillDetailController;
use App\Api\Models\Common\Contact as ContactModel;

/**
 *  租户管理
 */
class TenantController extends BaseController
{
    private $parent_type;
    private $tenantService;
    private $contractService;
    public function __construct()
    {
        parent::__construct();
        $this->parent_type = AppEnum::Tenant;
        $this->tenantService = new TenantService;
        $this->contractService = new ContractService;
    }

    /**
     * @OA\Post(
     *     path="/api/operation/tenant/list",
     *     tags={"租户"},
     *     summary="租户列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize","orderBy","order"},
     *       @OA\Property(property="name",type="String",description="客户名称"),
     *       @OA\Property(property="on_rent",type="int",description="1 在租 2 退租 0 所有")
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
        $request->validate([
            'proj_ids' => 'required|array',
        ], [
            'proj_ids.required' => '请选择项目',
            'proj_ids.array' => '项目参数错误'
        ]);
        // $map['parent_id'] = 0;

        DB::enableQueryLog();
        $query = $this->tenantService->tenantModel()
            ->where('type', AppEnum::TenantType)
            ->where(function ($q) use ($request) {
                $request->status && $q->whereIn('status', str2Array($request->status));
                $request->name && $q->where('name', 'like', '%' . $request->name . '%');
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                if (isset($request->on_rent)) {
                    $q->where('on_rent', $request->on_rent);
                }
                $request->addr && $q->where('addr', 'like', '%' . $request->addr . '%');
                $request->shop_name && $q->where('shop_name', 'like', '%' . $request->shop_name . '%');
                // 访问时间
                if ($request->visit_start_time && $request->visit_end_time) {
                    $q->whereBetween('visit_date', [$request->visit_start_time, $request->visit_end_time]);
                }
                if (isset($request->parent_id) && !empty($request->parent_id)) {
                    $q->where('parent_id', $request->parent_id);
                }
                //$q->where('parent_id', 0);
            })
            ->withCount('maintain')
            ->withCount('contract');
        // $query->get();
        // // return $result;
        // return DB::getQueryLog();
        $data = $this->pageData($query, $request);
        $contractService = new ContractService;
        foreach ($data['result'] as $k => &$tenant) {

            $signArea = $contractService->model()->where('tenant_id', $tenant['id'])->where('contract_state', AppEnum::contractExecute)->sum('sign_area');
            $tenant['total_area'] =  $signArea ?? 0.00;

            $contract = $this->contractService->model()
                ->select('start_date', 'end_date', 'tenant_id', 'lease_term', 'id', 'free_type')
                ->where('contract_state', 2)->orderByDesc('sign_date')
                ->where('tenant_id', $tenant['id'])
                ->first() ?? [];
            $tenant['start_date'] =  $contract['start_date'] ?? "";
            $tenant['end_date']   =  $contract['end_date'] ?? "";
            $tenant['lease_term'] =  $contract['lease_term'] ?? 0;

            $free = $this->contractService->freeModel()->where('tenant_id', $tenant['id'])
                ->selectRaw('sum(free_num) as total_free')->first();
            $tenant['free_num'] = "无免租";
            if ($free && $contract && $contract['free_type'] > 0) {
                $unit =  $contract['free_type'] == 1 ?  "个月" : "天";
                $tenant['free_num'] = ($free['total_free'] ?? 0) . $unit;
            }
            $tenant['rooms'] = $this->contractService->getRoomsByTenantIdSelect($tenant['id']);
        }
        // return response()->json(DB::getQueryLog());
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/operation/tenant/add",
     *     tags={"租户"},
     *     summary="租户新增",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"type","name","tenant_contact","invoice"},
     *       @OA\Property(property="type",type="int",description="客户类型 1:公司 2 个人废弃"),
     *       @OA\Property(property="name",type="String",description="客户名称"),
     *       @OA\Property(property="tenant_contact",type="Array",description="客户联系人"),
     *       @OA\Property(property="invoice",type="Array",description="客户联系人")
     *     ),
     *       example={"name":"租户名称","tenant_contact":"[]","invoice":"[]"}
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
            'name' => 'required',
            'tenant_contact' => 'array',
            'invoice' => 'array',
        ]);
        $DA = $request->toArray();
        $map['company_id']  = $this->company_id;
        $map['name']        = $request->name;
        $checkRepeat        = $this->tenantService->tenantRepeat($map);
        if ($checkRepeat) {
            return $this->error('客户名称重复!');
        }

        try {
            DB::transaction(function () use ($DA) {
                $DA['state'] = "成交客户";
                $DA['type'] = AppEnum::TenantType;
                $res = $this->tenantService->saveTenant($DA, $this->user);
                if ($res) {
                    $tenantId = $res->id;
                } else {
                    throw new Exception("租户保存失败!");
                }

                if ($DA['contacts']) {
                    $contacts = $DA['contacts'];
                    $this->user['parent_type'] = $this->parent_type;  // 联系人类型

                    $contacts = formatContact($contacts, $tenantId, $this->user, 1);
                    $contact = new ContactModel;
                    $contact->addAll($contacts);
                }
                // 更新发票信息
                if ($DA['invoice']) {
                    $invoice = $DA['invoice'];
                    $invoice['tenant_id'] = $tenantId;
                    $invoice['company_id'] = $this->company_id;
                    $invoice['proj_id'] = $DA['proj_id'];
                    $this->tenantService->saveInvoice($invoice, $this->user);
                }
                // 工商信息

                if ($DA['business_info']) {
                    $businessInfo = $DA['business_info'];
                    $businessInfo['name'] = $businessInfo['name'] ?? $DA['name'];
                    $info = new BaseInfoService;
                    if (!isset($DA['business_id']) || $DA['business_id'] == 0) {
                        $business = $info->save($businessInfo, 1);   // 新增
                        $this->tenantService->tenantModel()->whereId($tenantId)
                            ->update(['business_id' => $business->id]);
                    } else {
                        $business = $info->save($businessInfo, 2);   // 编辑
                    }
                }

                $log['tenant_id']   = $tenantId;
                $log['content']     =  $this->user['realname'] . '新增租户:' . $res->name;
                $this->tenantService->saveTenantLog($log, $this->user);
            }, 2);

            return $this->success('租户新增成功。');
        } catch (Exception $e) {
            Log::error("运营新增租户失败" . $e);
            return $this->error('租户新增失败');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/operation/tenant/edit",
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
            'id'                => 'required|numeric|gt:0',
            'name'              => 'required',
            'business_id'       => 'required',
            'contacts'          => 'array',
            'invoice'           => 'array',
            // 'tenant_share'      => 'array',
        ]);
        $DA = $request->toArray();
        $map['company_id']  = $this->company_id;
        $map['name']        = $request->name;
        $map['id']          = $request->id;
        $checkRepeat        = $this->tenantService->tenantRepeat($map, 'edit');
        if ($checkRepeat) {
            return $this->error('客户名称重复!');
        }
        try {
            DB::transaction(function () use ($DA) {
                // $user = auth('api')->user();
                $DA['type'] = AppEnum::TenantType;
                $tenantRes = $this->tenantService->saveTenant($DA, $this->user);
                if (!$tenantRes) {
                    throw new Exception("租户更新失败!");
                }
                // 写入联系人 支持多联系人写入
                if ($DA['contacts']) {
                    $contacts = $DA['contacts'];
                    $res = ContactModel::where('parent_id', $DA['id'])->delete();
                    $this->user['parent_type'] = $this->parent_type;
                    $contacts = formatContact($contacts, $DA['id'], $this->user, 2);
                    $contact = new ContactModel;
                    $contact->addAll($contacts);
                }
                // 更新发票信息
                if ($DA['invoice']) {
                    $DA['invoice']['tenant_id'] = $DA['id'];
                    $DA['invoice']['company_id'] = $this->company_id;
                    $DA['invoice']['proj_id'] = $DA['proj_id'];
                    $this->tenantService->saveInvoice($DA['invoice'], $this->user);
                }

                // 更新工商信息
                if ($DA['business_info']) {
                    $businessInfo = $DA['business_info'];
                    $businessInfo['name'] = $businessInfo['name'] ?? $DA['name'];
                    $baseInfoService = new BaseInfoService;
                    if ($DA['business_id'] == 0 || !$DA['business_id']) {
                        // Log::info("新增工商信息" . json_encode($businessInfo));
                        $business =  $baseInfoService->save($businessInfo, 1); // 新增工商信息
                        $this->tenantService->tenantModel()->whereId($DA['id'])
                            ->update(['business_id' => $business['id']]);
                    } else {
                        $baseInfoService->save($businessInfo, 2); // 更新工商信息
                    }
                }
                $log['tenant_id'] = $DA['id'];
                $log['content'] =  $this->user['realname'] . '编辑租户:' . $tenantRes->name;
                $this->tenantService->saveTenantLog($log, $this->user);
            });
            return $this->success('客户更新成功。');
        } catch (Exception $e) {
            Log::error($e);
            return $this->error('客户更新失败');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/operation/tenant/show",
     *     tags={"租户"},
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

        $data = $this->tenantService->tenantModel()
            ->with('contract')
            // ->with('tenantShare')
            // ->with('invoice')
            ->with('business_info')
            ->with('contacts')
            ->find($request->id)->toArray();

        DB::enableQueryLog();
        // return response()->json(DB::getQueryLog());
        if ($data) {
            $contractService = new ContractService;
            $data['rooms'] = $contractService->getRoomsByTenantId($request->id);

            $invoice = Invoice::where('tenant_id', $request->id)->first();
            if (!$invoice) {
                $invoice = (object) null;
            }
            $data['invoice'] = $invoice;
            if (!$data['business_info'] || empty($data['business_info'])) {
                $data['business_info'] = (object) null;
            }
            $data['share_tenant'] = $this->tenantService->tenantModel()->select("id", "tenant_no", "name", "created_at")
                ->where('parent_id', $data['id'])->get();
        }
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/operation/tenant/del",
     *     tags={"租户"},
     *     summary="租户删除",
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
    public function delete(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric|min:1',
        ], [
            'id.required' => '请选择租户',
            'id.numeric' => '租户参数错误',
            'id.min' => '租户参数错误'
        ]);
        $id = $request->id;
        $tenant = $this->tenantService->tenantModel()->find($id);
        if (!$tenant) {
            return $this->error('租户不存在');
        }

        if ($tenant->parent_id > 0) {
            return $this->error('存在分摊租户不能删除');
        }
        $contract = $this->contractService->model()->where('tenant_id', $id)->count();
        if ($contract > 0) {
            return $this->error('租户存在合同，不能删除');
        }
        $billDetailService = new TenantBillService;
        $billDetail = $billDetailService->billDetailModel()->where('tenant_id', $id)->count();
        if ($billDetail > 0) {
            return $this->error('租户存在账单，不能删除');
        }
        $res = $this->tenantService->deleteTenantById($id);

        return $res ? $this->success('租户删除成功') : $this->error('租户删除失败');
    }
}
