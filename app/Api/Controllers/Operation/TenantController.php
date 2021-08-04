<?php

namespace App\Api\Controllers\Operation;

use JWTAuth;
use Exception;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Common\Contact as ContactModel;
use App\Api\Models\Tenant\Invoice;
use App\Api\Services\Contract\ContractService;
use App\Api\Services\Tenant\BaseInfoService;
use App\Api\Services\Tenant\TenantService;
use App\Enums\AppEnum;

/**
 *  租户管理
 */
class TenantController extends BaseController
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
        $pagesize = $request->input('pagesize');
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config('per_size');
        }
        if ($pagesize == '-1') {
            $pagesize = config('export_rows');
        }
        $map = array();
        // $map['parent_id'] = 0;
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

        $result = $this->tenantService->tenantModel()
            ->where($map)
            ->where('type', AppEnum::TenantType)
            ->where(function ($q) use ($request) {
                $request->status && $q->whereIn('status', str2Array($request->status));
                $request->name && $q->where('name', 'like', '%' . $request->name . '%');
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->on_rent && $q->where('on_rent', $request->on_rent);
            })
            ->withCount('maintain')
            ->withCount('contract')
            ->with(['contractStat'  => function ($q) {
                $q->select(DB::Raw('id,sum(sign_area) total_area,tenant_id '));
                $q->where('room_type', 1);
                $q->wherehas('billRule', function ($subQuery) {
                    $subQuery->where('fee_type', AppEnum::rentFeeType);
                });
                $q->groupBy('tenant_id');
            }])
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();
        // return response()->json(DB::getQueryLog());

        $data = $this->handleBackData($result);
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/operation/tenant/add",
     *     tags={"租户"},
     *     summary="租户新增-分摊租户新增",
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
        $DA['type'] = AppEnum::TenantType;
        try {
            DB::transaction(function () use ($DA) {
                $res = $this->tenantService->saveTenant($DA, $this->user);
                if ($res) {
                    $tenantId = $res->id;
                } else {
                    throw new Exception("租户保存失败!");
                }
                if ($DA['tenant_contact']) {
                    $contacts = $DA['tenant_contact'];
                    $this->user['parent_type'] = $this->parent_type;  // 联系人类型
                    $contacts = formatContact($contacts, $tenantId, $this->user, 1);
                    $contact = new ContactModel;
                    $contact->addAll($contacts);
                }
                // 更新发票信息
                if ($DA['invoice']) {
                    $DA['invoice']['tenant_id']  = $tenantId;
                    $DA['invoice']['company_id'] = $this->company_id;
                    $DA['invoice']['proj_id']    = $DA['proj_id'];
                    $this->tenantService->saveInvoice($DA['invoice'], $this->user);
                }
                // 工商信息

                if ($DA['business_info']) {
                    $businessInfo = $DA['business_info'];
                    $businessInfo['name'] = $DA['name'];
                    $info = new BaseInfoService;
                    $business = $info->save($businessInfo, 1);   // 1 新增
                    if ($business) {
                        $businessData['business_id'] = $business->id;
                        $this->tenantService->tenantModel()->whereId($tenantId)->update($businessData);
                    }
                }
            });
            return $this->success('租户新增成功。');
        } catch (Exception $e) {
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
            'tenant_contact'    => 'array',
            'invoice'           => 'array',
            'tenant_share'      => 'array',
        ]);
        $DA = $request->toArray();
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
                $user = auth('api')->user();
                $res = $this->tenantService->saveTenant($DA, $this->user);
                if (!$res) {
                    throw new Exception("租户更新失败!");
                }
                // 写入联系人 支持多联系人写入
                if ($DA['tenant_contact']) {
                    $contacts = $DA['tenant_contact'];
                    $res = ContactModel::where('parent_id', $DA['id'])->delete();
                    $user['parent_type'] = $this->parent_type;
                    $contacts = formatContact($contacts, $DA['id'], $user, 2);
                    $contact = new ContactModel;
                    $contact->addAll($contacts);
                }
                // 更新发票信息
                if ($DA['invoice']) {
                    $DA['invoice']['tenant_id'] = $DA['id'];
                    $this->tenantService->saveInvoice($DA['invoice'], $this->user);
                }

                // 更新工商信息
                if (isset($DA['business_info']) && $DA['business_info']) {
                    $businessInfo = $DA['business_info'];
                    $businessInfo['business_id'] = $DA['business_id'];
                    $businessInfo['name'] = $DA['name'];
                    $info = new BaseInfoService;
                    $business = $info->save($businessInfo, 2);
                }
            });
            return $this->success('客户更新成功。');
        } catch (Exception $e) {
            Log::error($e->getMessage());
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
            ->with('business')
            ->with('contacts')
            ->find($request->id);

        DB::enableQueryLog();
        // return response()->json(DB::getQueryLog());
        if ($data) {
            $contractService = new ContractService;
            $data['rooms'] = $contractService->getRoomsByTenantId($data['id']);

            $invoice = Invoice::find($data['invoice_id']);
            if (!$invoice) {
                $invoice = (object) null;
            }
            $data['invoice'] = $invoice;
            if (!$data['business'] || empty($data['business'])) {
                $data['business'] = (object) null;
            }
        }
        return $this->success($data);
    }
}
