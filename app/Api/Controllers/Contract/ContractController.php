<?php

namespace App\Api\Controllers\Contract;

use JWTAuth;
use Exception;
use App\Enums\AppEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Controllers\BaseController;
use App\Api\Services\Contract\BillRuleService;
use App\Api\Services\Contract\ContractService;
use App\Api\Services\Template\TemplateService;
use App\Api\Services\Tenant\TenantShareService;

/**
 * @OA\Tag(
 *     name="合同",
 *     description="合同管理"
 * )
 */
class ContractController extends BaseController
{

    /**
     * 合同管理
     */
    private $tenantShareService;
    private $contractService;
    public function __construct()
    {
        parent::__construct();
        $this->tenantShareService = new TenantShareService;
        $this->contractService = new ContractService;
    }

    /**
     * @OA\Post(
     *     path="/api/business/contract/list",
     *     tags={"合同"},
     *     summary="合同列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize",},
     *       @OA\Property(
     *          property="pagesize",
     *          type="int",
     *          description="每页行数"
     *       ),
     *       @OA\Property(
     *          property="proj_id",
     *          type="int",
     *          description="项目ID"
     *       ),
     *       @OA\Property(
     *          property="sign_start_date",
     *          type="date",
     *          description="签订开始日期"
     *       ),
     *       @OA\Property(
     *          property="sign_end_date",
     *          type="date",
     *          description="签订结束日期"
     *       ),
     *       @OA\Property(
     *          property="contract_state",
     *          type="list",
     *          description="合同状态 0 待提交 1 待审核 2 已审核执行合同 98 退租 99  作废"
     *       ),
     *        @OA\Property(
     *          property="belong_uid",
     *          type="int",
     *          description="合同所属人id"
     *       )
     *
     *     ),
     *       example={
     *          "belong_uid":1,"contract_state":"[]","sign_end_date":"","sign_start_date":""
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */

    public function index(Request $request)
    {
        $map = array();
        if ($request->tenant_id && $request->tenant_id > 0) {
            $map['tenant_id'] = $request->tenant_id;
        }
        if ($request->contract_type) {
            $map['contract_type'] = $request->contract_type;
        }

        DB::enableQueryLog();
        $subQuery =  $this->contractService->model()->where($map)
            // ->with('contractRoom')
            // ->with('freeList')
            ->where(function ($q) use ($request) {
                $request->tenant_name && $q->where('tenant_name', 'like', "%" . $request->tenant_name . "%");
                $request->sign_start_date && $q->where('sign_date', '>=', $request->sign_start_date);
                $request->sign_end_date && $q->where('sign_date', '<=', $request->sign_end_date);
                $request->contract_state && $q->whereIn('contract_state', $request->contract_state);
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->belong_uid && $q->where('belong_uid', $request->belong_uid);
            })->withCount('contractRoom');

        $data = $this->pageData($subQuery, $request);
        $overdueRemind = getVariable($this->company_id, 'contract_due_remind');
        $overdueTime = strtotime(getPreYmdByDay(nowYmd(), $overdueRemind));
        $nowTime = time();
        foreach ($data['result'] as $k => &$v) {
            $v['room'] = $this->contractService->getContractRoom($v['id']);
            $v['is_share'] = $this->tenantShareService->isShare($v['id']);
            $v['overdue_remind'] = 0;
            if (strtotime($v['end_date']) > $overdueTime && strtotime($v['end_date']) < $nowTime) {
                $v['overdue_remind'] = 1;
            }
        }
        return $this->success($data);
    }



    /**
     * @OA\Post(
     *     path="/api/business/contract/add",
     *     tags={"合同"},
     *     summary="合同新增",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"contract_state","sign_date","start_date","end_date",},
     *      @OA\Property(
     *       property="contract_state",
     *       type="int",
     *       description="0:草稿 1:待审核 2:正式合同")
     *       ),
     *       @OA\Property(
     *          property="sign_date",
     *          type="date",
     *          description="签署日期"
     *       ),
     *       @OA\Property(
     *          property="start_date",
     *          type="date",
     *          description="合同开始时间"
     *       ),
     *       @OA\Property(
     *          property="end_date",
     *          type="date",
     *          description="合同截止时间"
     *       ),
     *       example={
     *              "contract_state": "1","sign_date":"","start_date":"1","end_date":""
     *           }
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
            'save_type' => 'required|in:0,1', // 0 只保存 1 保存并提交
            'contract_type' => 'required|numeric|in:1,2', // 1 新签 2 续签
            'sign_date' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'tenant_id' => 'required|numeric',
            'proj_id' => 'required|numeric',
            'tenant_legal_person' => 'required|String|between:1,64',
            'sign_area' => 'required|numeric|gt:0',
            // 'bill_day' => 'required|numeric',
            'bill_rule' => 'array',
            'deposit_rule' => 'array',
            'contract_room' => 'array',
            'free_list' => 'array',
            'fee_bill' => 'array',
            'deposit_bill' => 'array'
        ], [
            'save_type.required' => '保存类型是必须',
            'contract_type.required' => '合同类型是必须',
            'sign_date.required' => '签署日期是必须',
            'start_date.required' => '合同开始时间是必须',
            'end_date.required' => '合同截止时间是必须',
            'tenant_id.required' => '租户ID是必须',
            'bill_rule.array' => '租金规则必须是数组',
            'deposit_rule.array' => '押金规则必须是数组',
            'contract_room.array' => '房间信息必须是数组',
            'free_list.array' => '免租信息必须是数组',
            'fee_bill.array' => '费用账单必须是数组',
            'deposit_bill.array' => '押金账单必须是数组',

        ]);
        $DA = $request->toArray();
        $DA['contract_state'] = $DA['save_type'];
        // $DA['contract_state'] = $DA['save_type'];
        $contractId = 0;
        try {
            DB::transaction(function () use ($DA, &$contractId) {
                // $DA['rental_bank_id'] = getBankIdByFeeType(AppEnum::rentFeeType, $DA['proj_id']);
                // $DA['manager_bank_id'] = getBankIdByFeeType(AppEnum::managerFeeType, $DA['proj_id']);
                $contractService = new ContractService;
                $user = auth('api')->user();
                /** 保存，还是保存提交审核 ，保存提交审核写入审核日志 */
                $DA['contract_state'] = 0;
                $contract = $this->saveContract($DA); //格式化并保存
                $tenantId = $DA['tenant_id'];
                if (!$contract) {
                    throw new Exception("合同保存失败", 1);
                }
                // 租赁规则
                if ($DA['bill_rule']) {
                    $ruleService = new BillRuleService;
                    // 保存租金规则
                    $ruleService->ruleBatchSave($DA['bill_rule'], $user, $contract->id, $tenantId, true);
                }
                // 押金规则
                if ($DA['deposit_rule']) {
                    $ruleService = new BillRuleService;
                    $ruleService->ruleBatchSave($DA['deposit_rule'], $user, $contract->id, $tenantId, true);
                }
                // 房间
                if (!empty($DA['contract_room'])) {
                    $roomList = $this->formatRoom($DA['contract_room'], $contract->id, $DA['proj_id'], $tenantId);
                    $rooms = $this->contractService->contractRoomModel()->addAll($roomList);
                }
                // 免租
                $freeList = $DA['free_list'];
                if (!empty($freeList)) {
                    foreach ($freeList as $k => $v) {
                        // 保存免租信息
                        $contractService->saveFreeList($v, $contract->id, $contract->tenant_id);
                    }
                }
                // 保存合同账单
                if ($DA['fee_bill']) {
                    $contractService->saveContractBill($DA['fee_bill'], $this->user, $contract['proj_id'], $contract['id'], $contract['tenant_id']);
                }
                if (isset($DA['deposit_bill'])) {
                    $contractService->saveContractBill($DA['deposit_bill'], $this->user, $contract['proj_id'], $contract['id'], $contract['tenant_id'], 2);
                }
                $contractService->contractLog($contract, $user);
                $contractId = $contract['id'];
            }, 2);

            return $this->success(['id' => $contractId], '创建合同成功！');
        } catch (Exception $e) {
            Log::error("创建合同失败！" . $e->getMessage());
            return $this->error("创建合同失败！" . $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/business/contract/show",
     *     tags={"合同"},
     *     summary="根据渠道id获取合同信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="合同ID"
     *       )
     *     ),
     *       example={"id": 11}
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
            'id' => 'required|numeric|gt:0',
        ]);
        DB::enableQueryLog();
        $contractId = $request->id;

        $contractService = new ContractService;
        $data = $contractService->showContract($contractId, $this->uid);
        if ($data) {
            $data['contract_log'] = $contractService->getContractLogById($contractId);
            $shareTenant = new TenantShareService;
            $data['share_tenant'] = $shareTenant->getShareTenantsByContractId($data['id']);
            $data['is_share'] = $this->tenantShareService->isShare($data['id']);
        }
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/business/contract/edit",
     *     tags={"合同"},
     *     summary="合同编辑",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id" ,"contract_state"},
     *      @OA\Property(
     *       property="id",
     *       type="int",
     *       description="合同Id"
     *     ),
     *     @OA\Property(
     *       property="contract_state",
     *       type="int",
     *       description="合同状态 0 草稿 1 待审核 2 正式合同")
     *     ),
     *       example={
     *              "id": "1","contract_state":"1"
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
            'id' => 'required|numeric|gt:0', // 1 新签 2 续签
            'contract_room' => 'array',
            'save_type' => 'required|numeric|in:0,1',
            'free_list' => 'array',
            'bill_rule' => 'array',
            'fee_bill' => 'array'
        ], [
            'save_type.required' => '保存类型是必须',
            'contract_room.array' => '房间信息必须是数组',
            'free_list.array' => '免租信息必须是数组',
            'bill_rule.array' => '租金规则必须是数组',
            'fee_bill.array' => '费用账单必须是数组',
        ]);
        $DA = $request->toArray();
        $res = $this->contractService->model()->select('contract_state')->find($DA['id']);
        if ($res->contract_state == 2 || $res->contract_state == 99) {
            return $this->error('正式合同或者作废合同不允许更新');
        }
        try {
            DB::transaction(function () use ($DA) {
                // $DA['rental_bank_id'] = getBankIdByFeeType(AppEnum::rentFeeType, $DA['proj_id']);
                // $DA['manager_bank_id'] = getBankIdByFeeType(AppEnum::managerFeeType, $DA['proj_id']);
                $user = auth('api')->user();
                /** 保存，还是保存提交审核 ，保存提交审核写入审核日志 */
                if ($DA['save_type'] == 1) {
                    $DA['contract_state'] = 1;
                } else {
                    $DA['contract_state'] = 0;
                }

                $contract = $this->saveContract($DA, 'update'); //直接保存
                if (!$contract) {
                    throw new Exception("保存失败");
                }
                $tenantId = $contract->tenant_id;
                // $contractId = $contract->id;
                if (!empty($DA['contract_room'])) {
                    $roomList = $this->formatRoom($DA['contract_room'], $DA['id'], $DA['proj_id'], $tenantId, 2);
                    // DB::enableQueryLog();
                    $this->contractService->contractRoomModel()->where('contract_id', $DA['id'])->delete();

                    $this->contractService->contractRoomModel()->addAll($roomList);
                }
                // 免租 全部删除后全部新增
                if ($DA['free_type']) {
                    $this->contractService->delFreeList($DA['id']);
                    foreach ($DA['free_list'] as $k => $v) {
                        $this->contractService->saveFreeList($v, $contract->id, $contract->tenant_id);
                    }
                }
                $ruleService = new BillRuleService;
                // 租赁规则
                $ruleList = array();
                if ($DA['bill_rule']) {
                    $ruleList = array_merge($ruleList, $DA['bill_rule']);
                }
                // 押金规则
                if ($DA['deposit_rule']) {
                    $ruleList = array_merge($ruleList, $DA['deposit_rule']);
                }
                $ruleService->ruleBatchSave($ruleList, $user, $contract->id, $DA['tenant_id'], false);
                // 保存费用账单
                if ($DA['fee_bill']) {
                    $this->contractService->saveContractBill($DA['fee_bill'], $this->user, $contract['proj_id'], $contract['id'], $contract['tenant_id']);
                }
                // 保存押金账单
                $depositBill = $DA['deposit_bill'] ?? array();
                $this->contractService->saveContractBill($depositBill, $this->user, $contract['proj_id'], $contract['id'], $contract['tenant_id'], 2);

                $this->contractService->contractLog($contract, $user);
            });
            return $this->success('合同更新成功!');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error('合同更新失败');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/business/contract/word",
     *     tags={"合同"},
     *     summary="生成合同",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"contractId" ,"templateId"},
     *      @OA\Property(
     *       property="contractId",
     *       type="int",
     *       description="合同Id"
     *     ),
     *     @OA\Property(
     *       property="templateId",
     *       type="int",
     *       description="合同模版ID")
     *     ),
     *       example={
     *              "contractId": "1","templateId":"1"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function ContractWord(Request $request)
    {

        $validatedData = $request->validate([
            'templateId' => 'required|numeric|gt:0',
            'contractId' => 'required|numeric|gt:0',
        ]);
        $contractService = new ContractService;
        $contract = $contractService->showContract($request->contractId, false);
        $template = new TemplateService;
        $tem = $template->getTemplate($request->templateId);

        $parm['templateFile'] = public_path('template/') . md5($tem['name']) . ".docx";
        try {
            if (!is_dir(public_path('template/'))) {
                mkdir(public_path('template/'), 0755, true);
            }
            // 合同模版本地不存在则从OSS下载，OSS 没有则报错
            if (!$parm['templateFile'] || !file_exists($parm['templateFile'])) {
                $fileUrl = getOssUrl($tem['file_path']);
                $downloadTemlate = copy(trim($fileUrl), $parm['templateFile']);
                if (!$downloadTemlate) {
                    return $this->error('合同模版不存在');
                }
            }
        } catch (Exception $e) {
            Log::error("模版错误，请重新上传模版" . $e);
            return $this->error('模版错误，请重新上传模版');
        }

        $parm['fileName'] = $contract['tenant_name'] . date('Ymd', time()) . ".docx";
        $filePath = "/uploads/" . nowYmd() . "/" . $this->user['company_id'] . "/";
        $parm['savePath'] = public_path() . $filePath;

        $result = $template->exportContract($parm, $contract);
        if ($request) {
            $res['file'] = $filePath . $parm['fileName'];
            return $this->success($res);
        }
        return $this->error('系统错误，请联系管理员');
    }


    /**
     * @OA\Post(
     *     path="/api/business/contract/disuse",
     *     tags={"合同"},
     *     summary="合同作废",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id" ,"remark"},
     *      @OA\Property(
     *       property="id",
     *       type="int",
     *       description="合同Id"
     *     ),
     *     @OA\Property(
     *       property="remark",
     *       type="String",
     *       description="备注原因")
     *     ),
     *       example={
     *              "id": "1","remark":"合同作废"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function disuseContract(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|numeric|gt:0', //
            'remark' => 'required|String', //  备注
        ]);
        $DA = $request->toArray();
        $user = auth('api')->user();
        $contractService = new ContractService;
        $res = $contractService->disuseContract($DA, $user);
        if ($res['code']) {
            return $this->success('合同作废成功。');
        } else {
            return $this->error($res['msg']);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/business/contract/audit",
     *     tags={"合同"},
     *     summary="合同审核",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id" ,"remark","audit_state"},
     *      @OA\Property(property="id",type="int",description="合同Id"),
     *      @OA\Property(property="remark",type="String", description="备注原因"),
     *      @OA\Property(property="audit_state",type="int", description="审核状态1 通过 0 不通过")
     *     ),
     *       example={
     *              "id": "1","remark":"合同作废","audit_state":"1"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function auditContract(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|numeric|gt:0', // 合同Id
            'audit_state' => 'required|numeric|in:0,1', // 0 不同 1 通过
        ]);
        $DA = $request->toArray();
        $contract = $this->contractService->model()->find($DA['id']);
        if ($contract['contract_state'] == 2) {
            return $this->error("合同已经审核完成。");
        } else if ($contract['contract_state'] != 1) {
            return $this->error("合同不是待审核状态");
        }
        $contractService = new ContractService;
        $res = $contractService->auditContract($DA, $this->user);
        if ($res) {
            return $this->success('合同审核完成');
        } else {
            return $this->error('合同审核失败');
        }
    }




    /**
     * @OA\Post(
     *     path="/api/business/contract/change",
     *     tags={"合同"},
     *     summary="合同变更",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"contract_state","sign_date","start_date","end_date",},
     *      @OA\Property(
     *       property="contract_state",
     *       type="int",
     *       description="0:草稿 1:待审核 2:正式合同")
     *       ),
     *       @OA\Property(
     *          property="sign_date",
     *          type="date",
     *          description="签署日期"
     *       ),
     *       @OA\Property(
     *          property="start_date",
     *          type="date",
     *          description="合同开始时间"
     *       ),
     *       @OA\Property(
     *          property="end_date",
     *          type="date",
     *          description="合同截止时间"
     *       ),
     *       example={
     *              "contract_state": "1","sign_date":"","start_date":"1","end_date":""
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */

    public function change(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|numeric',
            'sign_date' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'tenant_id' => 'required|numeric',
            'proj_id' => 'required|numeric',
            'tenant_legal_person' => 'required|String|between:1,64',
            'sign_area' => 'required|numeric|gt:0',
            'bill_rule' => 'array',
            'free_list' => 'array',
            'fee_bill' => 'array',
        ]);
        $DA = $request->toArray();
        $tenantService = new TenantShareService;
        $shareTenants = $tenantService->model()->where('contract_id', $DA['id'])->count();
        if ($shareTenants > 0) {
            return $this->error("已有分摊租户不允许做变更");
        }

        try {
            DB::transaction(function () use ($DA) {
                $contractService = new ContractService;
                /** 保存，还是保存提交审核 ，保存提交审核写入审核日志 */
                $contract = $this->saveContract($DA, 'update'); //变更
                if (!$contract) {
                    throw new Exception("保存失败");
                }

                // 租赁规则
                if ($DA['bill_rule']) {
                    $ruleService = new BillRuleService;
                    $ruleService->ruleBatchSave($DA['bill_rule'], $this->user, $contract->id, $DA['tenant_id'], false);
                }

                // 免租
                $freeList = $DA['free_list'];
                if (!empty($freeList)) {
                    // 保存新的免租信息
                    $contractService->delFreeList($DA['id']);
                    foreach ($freeList as $free) {
                        $contractService->saveFreeList($free, $contract->id, $contract->tenant_id);
                    }
                }
                // 合同老账单处理， 需要删除的时候删除 tenant bill detail 数据
                if (!$DA['fee_bill']) {
                    throw new Exception("无账单数据");
                }
                $contractService->changeOldContractBill($DA['fee_bill']);

                if (!$DA['new_fee_bill']) {
                    throw new Exception("无新账单数据");
                }
                // throw new Exception("aaa", 200, $DA['new_fee_bill']);

                $contractService->changeContractBill($DA['new_fee_bill'], $contract, $this->user);


                // 保存日志
                $log['id'] = $contract['id'];
                $log['remark'] = $this->user['realname'] . "在" . nowTime() . "变更合同";
                $log['contract_id'] = $contract['id'];
                $log['title'] = "合同变更";
                $log['c_uid'] = $this->uid;
                $log['c_username'] = $this->user['realname'];
                $contractService->saveLog($log);
            }, 2);

            return $this->success('合同变更成功！');
        } catch (Exception $e) {
            Log::error("合同变更失败！" . $e->getMessage());
            return $this->error("合同变更失败！");
        }
    }

    /**
     * @OA\Post(
     *     path="/api/business/contract/return",
     *     tags={"合同"},
     *     summary="合同退回编辑状态",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id" ,"remark"},
     *      @OA\Property(
     *       property="id",
     *       type="int",
     *       description="合同Id"
     *     ),
     *     @OA\Property(
     *       property="remark",
     *       type="String",
     *       description="备注原因")
     *     ),
     *       example={
     *              "id": "1","remark":"合同退回编辑状态"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function adminReturn(Request $request)
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
        $contract = $this->contractService->model()->find($request->id);
        if (!$contract) {
            return $this->error('您所选合同不存在');
        }
        if ($contract->contract_state != AppEnum::contractExecute) {
            return $this->error('您所选合同不是执行中状态!');
        }
        $res = $this->contractService->adminReturn($request->id, $request->remark, $this->user);
        return $res ? $this->success('合同退回成功') : $this->error('合同退回失败');
    }


    /**
     * 格式化合同
     * @Author   leezhua
     * @DateTime 2021-06-01
     * @param    [type]     $DA   [description]
     * @param    string     $type [description]
     * @return   [type]           [description]
     */
    private function saveContract($DA, $type = "add")
    {
        $user = auth('api')->user();
        if ($type == "add") {
            $contract = $this->contractService->model();
            $contract->c_uid = $user->id;
            $contract->company_id = $user->company_id;
        } else {
            //编辑
            $contract = $this->contractService->model()->find($DA['id']);
            $contract->u_uid = $user->id;
        }
        // 合同编号不设置的时候系统自动生成
        if (!isset($DA['contract_no']) || empty($DA['contract_no'])) {

            $contract->contract_no = getContractNo($user->company_id);
        } else {
            $contract->contract_no = $DA['contract_no'];
        }
        $contract->free_type             = isset($DA['free_type']) ? $DA['free_type'] : 0;
        $contract->proj_id               = $DA['proj_id'];
        $contract->contract_state        = $DA['contract_state'];
        $contract->contract_type         = $DA['contract_type'];
        $contract->violate_rate          = isset($DA['violate_rate']) ? $DA['violate_rate'] : 0;
        $contract->sign_date             = $DA['sign_date'];
        $contract->start_date            = $DA['start_date'];
        $contract->end_date              = $DA['end_date'];
        $contract->belong_uid            = $DA['belong_uid'] ?? $user->id;
        $contract->belong_person         = $DA['belong_person'] ?? $user->realname;
        $contract->tenant_id             = $DA['tenant_id'];
        $contract->tenant_name           = $DA['tenant_name'];
        $contract->lease_term            = $DA['lease_term'];
        $contract->industry              = $DA['industry'];
        $contract->tenant_sign_person    = isset($DA['tenant_sign_person']) ? $DA['tenant_sign_person'] : "";
        $contract->tenant_legal_person   = isset($DA['tenant_legal_person']) ? $DA['tenant_legal_person'] : "";
        $contract->sign_area             = $DA['sign_area'];
        $contract->rental_deposit_amount = isset($DA['rental_deposit_amount']) ? $DA['rental_deposit_amount'] : 0.00;
        $contract->rental_deposit_month  = isset($DA['rental_deposit_month']) ? $DA['rental_deposit_month'] : 0;
        if (isset($DA['increase_date'])) {
            $contract->increase_date = $DA['increase_date'];
        }
        $contract->increase_rate           = isset($DA['increase_rate']) ? $DA['increase_rate'] : 0;
        $contract->increase_year           = isset($DA['increase_year']) ? $DA['increase_year'] : 0;
        $contract->bill_type               = isset($DA['bill_type']) ? $DA['bill_type'] : 1;
        $contract->ahead_pay_month         = isset($DA['ahead_pay_month']) ? $DA['ahead_pay_month'] : "";
        $contract->rental_price            = isset($DA['rental_price']) ? $DA['rental_price'] : 0.00;
        $contract->rental_price_type       = isset($DA['rental_price_type']) ? $DA['rental_price_type'] : 1;
        $contract->management_price        = isset($DA['management_price']) ? $DA['management_price'] : 0.00;
        $contract->management_month_amount = isset($DA['management_month_amount']) ? $DA['management_month_amount'] : 0;
        $contract->rental_month_amount    = isset($DA['rental_month_amount']) ? $DA['rental_month_amount'] : 0.00;
        $contract->manager_bank_id        = isset($DA['manager_bank_id']) ? $DA['manager_bank_id'] : 0;
        $contract->rental_bank_id         = isset($DA['rental_bank_id']) ? $DA['rental_bank_id'] : 0;
        $contract->manager_deposit_month  = isset($DA['manager_deposit_month']) ? $DA['manager_deposit_month'] : 0;
        $contract->manager_deposit_amount = isset($DA['manager_deposit_amount']) ? $DA['manager_deposit_amount'] : 0.00;
        $contract->increase_show          = isset($DA['increase_show']) ? $DA['increase_show'] : 0;
        $contract->manager_show           = isset($DA['manager_show']) ? $DA['manager_show'] : 0;
        $contract->rental_usage           = isset($DA['rental_usage']) ? $DA['rental_usage'] : "";
        $contract->room_type              = isset($DA['room_type']) ? $DA['room_type'] : 1;
        $res = $contract->save();
        if ($res) {
            return $contract;
        } else {
            return false;
        }
    }

    private function formatRoom($DA, $contractId, $proj_id, $tenantId, $type = 1): array
    {
        $currentDateTime = nowTime();
        foreach ($DA as $k => $v) {
            $BA[$k] = [
                'contract_id' => $contractId,
                'tenant_id'   => $tenantId,
                'proj_id'     => $proj_id,
                'proj_name'   => $v['proj_name'],
                'build_id'    => $v['build_id'],
                'build_no'    => $v['build_no'],
                'floor_id'    => $v['floor_id'],
                'floor_no'    => $v['floor_no'],
                'room_id'     => $v['room_id'],
                'room_no'     => $v['room_no'],
                'room_area'   => $v['room_area'],
                'room_type'   => $v['room_type'],
                'station_no'  => $v['station_no'] ?? "",

            ];
            $BA[$k][$type != 1 ? 'created_at' : 'updated_at'] = $currentDateTime;
        }
        return $BA;
    }

    public function checkRepeat($contractNo, $projId, $contractId, $type = 'add')
    {
        if ($type == 'add') {
            $contract = $this->contractService->model()
                ->where('contract_no', $contractNo)
                ->where('proj_id', $projId)->exists();
        } else {
            $contract = $this->contractService->model()
                ->where('contract_no', $contractNo)
                ->where('proj_id', $projId)
                ->where('id', '!=', $contractId)
                ->exists();
        }
        return $contract;
    }
}
