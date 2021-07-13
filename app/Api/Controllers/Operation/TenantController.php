<?php

namespace App\Api\Controllers\Operation;

use JWTAuth;
use Exception;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Common\Contact as ContactModel;
use App\Api\Models\Contract\ContractRoom;
use App\Api\Models\Tenant\BaseInfo;
use App\Api\Services\Contract\ContractService;
use App\Api\Services\CustomerInfoService;
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
        $map['type'] = 2;
        DB::enableQueryLog();
        $result = $this->tenantService->tenantModel()->where($map)
            ->where('type', AppEnum::TenantType)
            ->where(function ($q) use ($request) {
                $request->name && $q->where('name', 'like', '%' . $request->name . '%');
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
            })
            ->with('rooms')
            ->withCount('maintain')
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();
        // return response()->json(DB::getQueryLog());

        $data = $this->handleBackData($result);
        // return $data;

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
                    $this->tenantService->saveInvoice($DA['invoice']);
                }
                // 工商信息

                if ($DA['business_info']) {
                    $businessInfo = $DA['business_info'];
                    $businessInfo['name'] = $DA['name'];
                    $info = new BaseInfoService;
                    $business = $info->save($businessInfo, 1);   // 1 新增
                    if ($business) {
                        $businessData['business_id'] = $business->id;
                        $update = $this->tenantService->tenantModel()->whereId($tenantId)->update($businessData);
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
                    $this->tenantService->saveInvoice($DA['invoice']);
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
            ->with('tenantShare')
            ->with('invoice')
            ->with('business')
            ->find($request->id);

        DB::enableQueryLog();
        // return response()->json(DB::getQueryLog());
        if ($data) {
            $contractService = new ContractService;
            $data['rooms'] = $contractService->getRoomsByTenantId($data['id']);
        }
        return $this->success($data);
    }



    /**
     * @OA\Post(
     *     path="/api/operation/tenant/share/save",
     *     tags={"租户"},
     *     summary="租户分摊字表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"parent_id","name"},
     *       @OA\Property(
     *          property="parent_id",
     *          type="int",
     *          description="父亲租户ID"
     *       ),
     *       @OA\Property(
     *          property="name",
     *          type="String",
     *          description="分摊租户名称"
     *       ),
     *       @OA\Property(
     *          property="share_type",
     *          type="int",
     *          description="分摊类型1面积2 比例3固定金额"
     *       )
     *     ),
     *       example={"parent_id":1,"name":"","share_type":""}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function shareStore(Request $request)
    {
        $validatedData = $request->validate([
            'parent_id' => 'required|numeric|gt:0',
            'name' => 'required',
            'share_type' => 'required|numeric|in:1,2,3',
        ]);
        $DA = $request->toArray();
        try {
            $this->tenantService->saveShare($DA, $this->user);
            return $this->success("保存成功");
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error("保存失败");
        }
    }
    /**
     * @OA\Post(
     *     path="/api/operation/tenant/share/unlink",
     *     tags={"租户"},
     *     summary="解绑分摊租户",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="租户id"
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
    public function unlinkShare(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|numeric|gt:0',
        ]);

        $res = $this->tenantService->unlinkShare($request->id);
        if ($res) {
            return $this->success("删除分摊租户成功");
        } else {
            return $this->error("删除分摊租户失败");
        }
    }

    /**
     * @OA\Post(
     *     path="/api/operation/tenant/sync",
     *     tags={"租户"},
     *     summary="招商信息同步到租户",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"contract_id"},
     *       @OA\Property(
     *          property="contract_id",
     *          type="int",
     *          description="招商合同id"
     *       )
     *     ),
     *       example={"contract_id":1}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */

    public function tenantSync(Request $request)
    {
        $validatedData = $request->validate([
            'contract_id' => 'required|numeric|gt:0', // 招商合同id
        ]);

        $res = $this->tenantService->tenantSync($request->contract_id, $this->user);
        if ($res) {
            return $this->success("同步完成。");
        } else {
            return $this->error("同步失败！");
        }
    }
}
