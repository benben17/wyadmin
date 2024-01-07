<?php

namespace App\Api\Controllers\Business;

use JWTAuth;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Api\Controllers\BaseController;
use App\Api\Models\Contract\Contract as ContractModel;
use App\Api\Models\Contract\ContractRoom as ContractRoomModel;
use App\Api\Services\Contract\BillRuleService;
use App\Api\Services\Template\TemplateService;
use App\Api\Services\Contract\ContractBillService;
use App\Api\Services\Contract\ContractService;


/**
 * 合同管理
 *contract_type  1 新租 2 续约
 * 合同编号
 */

class ContractController extends BaseController
{

    public function __construct()
    {
        $this->uid  = auth()->payload()->get('sub');
        if (!$this->uid) {
            return $this->error('用户信息错误');
        }
        try {
            $this->user = auth('api')->user();
            // Log::error($this->user);
        } catch (Exception $e) {
            Log::error($e);
        }
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
     *          type="string",
     *          description="合同状态 0 待提交 1 待审核 2 已审核执行合同 98 退租 99  作废"
     *       )
     *
     *     ),
     *       example={
     *          ""
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
        $pagesize = $request->input('pagesize');
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config("per_size");
        }
        if ($pagesize == '-1') {
            $pagesize = config('export_rows');
        }
        $map = array();

        if ($request->tenant_id && $request->tenant_id > 0) {
            $map['tenant_id'] = $request->tenant_id;
        }
        if ($request->contract_type) {
            $map['contract_type'] = $request->contract_type;
        }
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
        $data =  ContractModel::where($map)
            // ->with('contractRoom')
            // ->with('freeList')
            ->where(function ($q) use ($request) {
                $request->tenant_name && $q->where('tenant_name', 'like', "%" . $request->tenant_name . "%");
                $request->sign_start_date && $q->where('sign_date', '>=', $request->sign_start_date);
                $request->sign_end_date && $q->where('sign_date', '<=', $request->sign_end_date);
                if ($request->contract_state != '') {
                    $state = str2Array($request->contract_state);
                    $q->whereIn('contract_state', $state);
                }
                $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
                $request->belong_uid && $q->where('belong_uid', $request->belong_uid);
            })->withCount('contractRoom')
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();

        $data = $this->handleBackData($data);
        foreach ($data['result'] as $k => &$v) {
            $room = ContractRoomModel::selectRaw('build_no,floor_no,case when room_type = 1 then GROUP_CONCAT(room_no) else GROUP_CONCAT(station_no)   end  rooms ')
                ->where('contract_id', $v['id'])->groupBy('contract_id')->first();
            $v['contract_room'] = $room['build_no'] . "-" . $room['floor_no'] . "-" . $room['rooms'];
        }
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/business/contract/list/stat",
     *     tags={"合同"},
     *     summary="同期合同到期，默认显示2年的数据",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={},
     *     ),
     *       example={""}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function getContractStat(Request $request)
    {
        $thisMonth = date('Y-m-01', time());
        $endMonth = getNextMonth($thisMonth, 25);
        DB::enableQueryLog();
        $contractStat = ContractModel::select(DB::Raw("count(*) as expire_count,sum(sign_area) as expire_area,DATE_FORMAT(end_date,'%Y-%m') as ym"))
            ->whereBetween('end_date', [$thisMonth, $endMonth . '-01'])
            ->where('contract_state', 2)
            ->where(function ($q) use ($request) {
                if ($request->room_type) {
                    $q->where('room_type', $request->room_type);
                }
            })
            ->groupBy("ym")
            ->orderBy("ym")->get()->toArray();
        // return response()->json(DB::getQueryLog());
        $i = 0;
        while ($i < 24) {
            foreach ($contractStat as $k => $v) {
                if ($v['ym'] === getNextMonth($thisMonth, $i)) {
                    $stat[$i]['ym'] = getNextMonth($thisMonth, $i);
                    $stat[$i]['expire_area'] = $v['expire_area'];
                    $stat[$i]['expire_count'] = $v['expire_count'];
                    $i++;
                }
            }
            $stat[$i]['ym'] = getNextMonth($thisMonth, $i);
            $stat[$i]['expire_area'] = 0;
            $stat[$i]['expire_count'] = 0;
            $i++;
        }
        return $this->success($stat);
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
        ]);
        $DA = $request->toArray();

        $DA['contract_state'] = $DA['save_type'];
        $DA['contract_state'] = $DA['save_type'];

        $contractId = 0;
        try {
            DB::transaction(function () use ($DA, &$contractId) {
                $contractService = new ContractService;
                $user = auth('api')->user();
                /** 保存，还是保存提交审核 ，保存提交审核写入审核日志 */
                $DA['contract_state'] = 0;
                $contract = $this->saveContract($DA); //格式化并保存
                if (!$contract) {
                    throw new Exception("合同保存失败", 1);
                }
                // 租赁规则
                if ($DA['bill_rule']) {
                    $ruleService = new BillRuleService;
                    $ruleService->batchSave($DA['bill_rule'], $user, $contract->id, $DA['tenant_id']);
                }
                // 押金规则
                if ($DA['deposit_rule']) {
                    $ruleService = new BillRuleService;
                    $ruleService->batchSave($DA['deposit_rule'], $user, $contract->id, $DA['tenant_id']);
                }
                // 房间
                if (!empty($DA['contract_room'])) {
                    $roomList = $this->formatRoom($DA['contract_room'], $contract->id, $DA['proj_id']);
                    $rooms = new ContractRoomModel;
                    $rooms->addAll($roomList);
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
                if (!$DA['fee_bill']) {
                    throw new Exception("无账单数据");
                }
                $contractService->saveContractBill($DA['fee_bill'], $this->user, $contract['proj_id'], $contract['id'], $contract['tenant_id']);
                if (isset($DA['deposit_bill'])) {
                    $contractService->saveContractBill($DA['deposit_bill'], $this->user, $contract['proj_id'], $contract['id'], $contract['tenant_id'], 2);
                }
                $contractService->contractLog($contract, $user);
                $contractId = $contract['id'];
            }, 2);

            return $this->success(['id' => $contractId], '创建合同成功！');
        } catch (Exception $e) {
            Log::error("创建合同失败！" . $e->getMessage());
            return $this->error("创建合同失败！");
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
        ]);
        $DA = $request->toArray();
        $res = ContractModel::select('contract_state')->find($DA['id']);
        if ($res->contract_state == 2 || $res->contract_state == 99) {
            return $this->error('正式合同或者作废合同不允许更新');
        }
        try {
            DB::transaction(function () use ($DA) {
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

                $contractService = new ContractService;
                if (!empty($DA['contract_room'])) {
                    $roomList = $this->formatRoom($DA['contract_room'], $DA['id'], $DA['proj_id'], 2);
                    DB::enableQueryLog();
                    $res = ContractRoomModel::where('contract_id', $DA['id'])->delete();
                    // return response()->json(DB::getQueryLog());
                    $rooms = new ContractRoomModel;
                    $rooms->addAll($roomList);
                }
                // 免租 全部删除后全部新增
                if ($DA['free_type']) {
                    $contractService->delFreeList($DA['id']);
                    foreach ($DA['free_list'] as $k => $v) {
                        $contractService->saveFreeList($v, $contract->id, $contract->tenant_id);
                    }
                }

                // 租赁规则
                if ($DA['bill_rule']) {
                    $ruleService = new BillRuleService;
                    $ruleService->batchUpdate($DA['bill_rule'], $user, $contract->id, $DA['tenant_id']);
                }
                // 押金规则
                if ($DA['deposit_rule']) {
                    $ruleService = new BillRuleService;
                    $ruleService->batchSave($DA['deposit_rule'], $user, $contract->id, $DA['tenant_id']);
                }
                // 保存费用账单
                $contractService->saveContractBill($DA['fee_bill'], $this->user, $contract['proj_id'], $contract['id'], $contract['tenant_id']);
                // 保存押金账单
                if (isset($DA['deposit_bill'])) {
                    $contractService->saveContractBill($DA['deposit_bill'], $this->user, $contract['proj_id'], $contract['id'], $contract['tenant_id'], 2);
                }
                $contractService->contractLog($contract, $user);
            });
            return $this->success('合同更新成功!');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error('合同更新失败');
        }
    }
    /**
     * @OA\Post(
     *     path="/api/business/contract/bill/create",
     *     tags={"合同"},
     *     summary="合同账单生成",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"contract_id" },
     *      @OA\Property(property="contract_id",type="int",
     *       description="合同id")
     *     ),
     *       example={
     *              "contract_id": ""
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function contractBill(Request $request)
    {
        $validatedData = $request->validate([
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
            'contract_room' => 'array',
            'free_list' => 'array',
        ]);
        $billService = new ContractBillService;
        $contract = $request->toArray();
        $data = array();
        $fee_list = array();
        foreach ($contract['bill_rule'] as $k => $rule) {
            $feeList = array();
            if ($rule['type'] != 1) {
                continue;
            }
            if ($rule['bill_type'] == 1) {  // 正常账期
                $feeList = $billService->createBill($contract, $rule, $this->uid);
            } else if ($rule['bill_type'] == 2) { // 自然月账期
                $feeList = $billService->createBillziranyue($contract, $rule, $this->uid);
            } else if ($rule['bill_type'] == 3) { // 只有租金走账期顺延
                if ($rule['fee_type'] == 101) {
                    $feeList = $billService->createBillByzhangqi($contract, $rule, $this->uid);
                } else {
                    $feeList = $billService->createBill($contract, $rule, $this->uid);
                }
            }
            array_push($fee_list, $feeList);
        }
        if ($fee_list) {
            $data['fee_bill']  = $fee_list;
        }
        if ($contract['deposit_rule']) {
            $data['deposit_bill'] = array();
            $deospitBill = $billService->createDepositBill($contract['deposit_rule'], $this->uid);
            if ($deospitBill) {
                array_push($data['deposit_bill'], $deospitBill);
            }
        }

        return $this->success($data);
    }
    /**
     * @OA\Post(
     *     path="/api/business/contract/bill/save",
     *     tags={"合同"},
     *     summary="合同账单保存",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"contract_id" },
     *      @OA\Property(property="contract_id",type="int",description="合同id"),
     *     ),
     *       example={
     *              "contract_id": "1"
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function saveContractBill(Request $request)
    {
        // $validatedData = $request->validate([
        //     'contract_id' => 'required',
        // ]);
        $DA = $request->toArray();
        try {
            $contractService = new ContractService;
            $contractService->saveContractBill($DA['bills'], $this->user, $DA['proj_id'], $DA['id'], $DA['tenant_id']);
            return $this->success('合同账单保存成功。');
        } catch (\Throwable $th) {
            return $this->error("合同账单保存失败！");
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
            'id' => 'required|numeric|gt:0', // 1 新签 2 续签
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
        $contract = ContractModel::find($DA['id']);
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
            $contract = new ContractModel;
            $contract->c_uid = $user->id;
            $contract->company_id = $user->company_id;
        } else {
            //编辑
            $contract = ContractModel::find($DA['id']);
            $contract->u_uid = $user->id;
        }
        // 合同编号不设置的时候系统自动生成
        if (!isset($DA['contract_no']) || empty($DA['contract_no'])) {
            $contract_prefix = getVariable($user->company_id, 'contract_prefix');
            $contract->contract_no = $contract_prefix . getContractNo();
        } else {
            $contract->contract_no = $DA['contract_no'];
        }
        $contract->free_type = isset($DA['free_type']) ? $DA['free_type'] : 0;
        $contract->proj_id = $DA['proj_id'];
        $contract->contract_state = $DA['contract_state'];
        $contract->contract_type =  $DA['contract_type'];
        $contract->violate_rate = isset($DA['violate_rate']) ? $DA['violate_rate'] : 0;
        $contract->sign_date = $DA['sign_date'];
        $contract->start_date = $DA['start_date'];
        $contract->end_date = $DA['end_date'];
        $contract->belong_uid = $user->id;
        $contract->belong_person = $user->realname;
        $contract->tenant_id = $DA['tenant_id'];
        $contract->tenant_name = $DA['tenant_name'];
        $contract->lease_term = $DA['lease_term'];
        $contract->industry = $DA['industry'];
        $contract->tenant_sign_person = isset($DA['tenant_sign_person']) ? $DA['tenant_sign_person'] : "";
        $contract->tenant_legal_person = isset($DA['tenant_legal_person']) ? $DA['tenant_legal_person'] : "";
        $contract->sign_area = $DA['sign_area'];
        $contract->rental_deposit_amount = isset($DA['rental_deposit_amount']) ? $DA['rental_deposit_amount'] : 0.00;
        $contract->rental_deposit_month = isset($DA['rental_deposit_month']) ? $DA['rental_deposit_month'] : 0;
        if (isset($DA['increase_date'])) {
            $contract->increase_date = $DA['increase_date'];
        }
        $contract->increase_rate = isset($DA['increase_rate']) ? $DA['increase_rate'] : 0;
        $contract->increase_year = isset($DA['increase_year']) ? $DA['increase_year'] : 0;
        $contract->bill_type = isset($DA['bill_type']) ? $DA['bill_type'] : 1;
        $contract->ahead_pay_month = isset($DA['ahead_pay_month']) ? $DA['ahead_pay_month'] : "";
        $contract->rental_price = isset($DA['rental_price']) ? $DA['rental_price'] : 0.00;
        $contract->rental_price_type = isset($DA['rental_price_type']) ? $DA['rental_price_type'] : 1;
        $contract->management_price = isset($DA['management_price']) ? $DA['management_price'] : 0.00;
        $contract->management_month_amount = isset($DA['management_month_amount']) ? $DA['management_month_amount'] : 0;
        // $contract->pay_method = $DA['pay_method'];
        $contract->rental_month_amount = isset($DA['rental_month_amount']) ? $DA['rental_month_amount'] : 0.00;
        $contract->rental_account_name = isset($DA['rental_account_name']) ? $DA['rental_account_name'] : "";
        $contract->rental_account_number = isset($DA['rental_account_number']) ? $DA['rental_account_number'] : "";
        $contract->rental_bank_name = isset($DA['rental_bank_name']) ? $DA['rental_bank_name'] : "";
        $contract->manager_account_name = isset($DA['manager_account_name']) ? $DA['manager_account_name'] : "";
        $contract->manager_account_number = isset($DA['manager_account_number']) ? $DA['manager_account_number'] : "";
        $contract->manager_bank_name = isset($DA['manager_bank_name']) ? $DA['manager_bank_name'] : "";
        $contract->manager_bank_id = isset($DA['manager_bank_id']) ? $DA['manager_bank_id'] : 0;
        $contract->rental_bank_id = isset($DA['rental_bank_id']) ? $DA['rental_bank_id'] : 0;
        $contract->manager_deposit_month = isset($DA['manager_deposit_month']) ? $DA['manager_deposit_month'] : 0;
        $contract->manager_deposit_amount = isset($DA['manager_deposit_amount']) ? $DA['manager_deposit_amount'] : 0.00;
        $contract->increase_show = isset($DA['increase_show']) ? $DA['increase_show'] : 0;
        $contract->manager_show = isset($DA['manager_show']) ? $DA['manager_show'] : 0;
        $contract->rental_usage = isset($DA['rental_usage']) ? $DA['rental_usage'] : "";
        $contract->room_type = isset($DA['room_type']) ? $DA['room_type'] : 1;
        $res = $contract->save();
        if ($res) {
            return $contract;
        } else {
            return false;
        }
    }

    private function formatRoom($DA, $contractId, $proj_id, $type = 1)
    {
        foreach ($DA as $k => $v) {
            if ($type != 1) {
                $BA[$k]['created_at'] = date('Y-m-d H:i:s');
            } else {
                $BA[$k]['updated_at'] = date('Y-m-d H:i:s');
            }
            $BA[$k]['contract_id']  =  $contractId;
            $BA[$k]['proj_id']      = $proj_id;
            $BA[$k]['proj_name']    = $v['proj_name'];
            $BA[$k]['build_id']     = $v['build_id'];
            $BA[$k]['build_no']     = $v['build_no'];
            $BA[$k]['floor_id']     = $v['floor_id'];
            $BA[$k]['floor_no']     = $v['floor_no'];
            $BA[$k]['room_id']      = $v['room_id'];
            $BA[$k]['room_no']      = $v['room_no'];
            $BA[$k]['room_area']    = $v['room_area'];
            $BA[$k]['room_type']    = $v['room_type'];
            $BA[$k]['station_no']   = isset($v['station_no']) ? $v['station_no'] : "";
        }
        return $BA;
    }
}
