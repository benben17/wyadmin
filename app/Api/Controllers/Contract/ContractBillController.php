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
use Illuminate\Contracts\Validation\Validator;
use App\Api\Services\Contract\ContractBillService;

/**
 * @OA\Tag(
 *     name="合同",
 *     description="合同管理"
 * )
 */
class ContractBillController extends BaseController
{

  /**
    
   * 合同编号
   */
  // private $tenantShareService;
  private $contractService;
  public function __construct()
  {
    parent::__construct();
    // $this->tenantShareService = new TenantShareService;
    $this->contractService = new ContractService;
  }

  /**
   * @OA\Post(
   *     path="/api/business/contract/list/stat",
   *     tags={"合同"},
   *     summary="同期合同到期，默认显示2年的数据/执行合同表头",
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

    $validatedData = $request->validate([
      'proj_ids' => 'required|array',
    ]);

    $thisMonth = date('Y-m-01', time());
    $endMonth = getNextMonth($thisMonth, 25);
    DB::enableQueryLog();
    $contractStat = $this->contractService->model()
      ->select(DB::Raw("count(*) as expire_count,sum(sign_area) as expire_area,DATE_FORMAT(end_date,'%Y-%m') as ym"))
      ->whereBetween('end_date', [$thisMonth, $endMonth . '-01'])
      ->where('contract_state', 2)
      ->whereIn('proj_id', $request->proj_ids)
      ->where(function ($q) use ($request) {
        if ($request->room_type) {
          $q->where('room_type', $request->room_type);
        }
      })
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
   *       example={"contract_id": "1","bill_rule":"[]","contract_room":"[]","free_list":"[]","contract_type":"1","sign_date":"2020-01-01","start_date":"2020-01-01","end_date":"2020-01-01","tenant_id":"1","proj_id":"1","tenant_legal_person":"张三","sign_area":"100","bill_day":"1"
   *           }
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function createContractBill(Request $request)
  {
    $msg =  [
      'bill_rule.array' => '租金规则必须是数组',
      'contract_room.array' => '房间信息必须是数组',
      'free_list.array' => '免租信息必须是数组',
      'contract_type.numeric' => '合同类型必须是数字',
      'contract_type.in' => '合同类型不正确,[1 or 2]',
      'contract_type.required' => '合同类型是必须',
      'sign_date.required' => '签署日期是必须',
      'start_date.required' => '合同开始时间是必须',
      'end_date.required' => '合同截止时间是必须',
      'tenant_id.required' => '租户ID是必须',
    ];

    $request->validate([
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
    ], $msg);
    $billService = new ContractBillService;
    $contract = $request->toArray();
    $data = array();
    $fee_list = array();
    $ruleService = new BillRuleService;
    $ruleService->validateIncrease($contract['bill_rule']);
    foreach ($contract['bill_rule'] as $k => $rule) {
      $feeList = array();
      if ($rule['type'] != 1) {
        continue;
      }
      // if ($rule['bill_type'] == 1) {  // 正常账期
      //     $feeList = $billService->createBill($contract, $rule, $this->uid);
      // } else if ($rule['bill_type'] == 2) { // 自然月账期
      //     $feeList = $billService->createBillziranyue($contract, $rule, $this->uid);
      // } 
      // } else if ($rule['bill_type'] == 2) { // 只有租金走账期顺延
      if ($rule['fee_type'] == AppEnum::rentFeeType) {
        $feeList = $billService->createBillByzhangqi($contract, $rule, $this->uid);
      } else {
        $feeList = $billService->createBill($contract, $rule, $this->uid);
      }
      // }
      array_push($fee_list, $feeList);
    }
    if ($fee_list) {
      $data['fee_bill']  = $fee_list;
    }
    if ($contract['deposit_rule']) {
      $data['deposit_bill'] = array();
      $depositBill = $billService->createDepositBill($contract['deposit_rule'], $this->uid);
      if ($depositBill) {
        array_push($data['deposit_bill'], $depositBill);
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
  // public function saveContractBill(Request $request)
  // {
  //   // $validatedData = $request->validate([
  //   //     'contract_id' => 'required',
  //   // ]);
  //   $DA = $request->toArray();
  //   try {

  //     $this->contractService->saveContractBill($DA['bills'], $this->user, $DA['proj_id'], $DA['id'], $DA['tenant_id']);
  //     return $this->success('合同账单保存成功。');
  //   } catch (\Exception $th) {
  //     Log::error("合同账单保存失败." . $th->getMessage());
  //     return $this->error("合同账单保存失败！");
  //   }
  // }
}
