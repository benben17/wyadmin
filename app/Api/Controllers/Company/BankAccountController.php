<?php

namespace App\Api\Controllers\Company;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Api\Models\Company\BankAccount as bankAccountModel;
use App\Api\Models\Company\FeeType;
use App\Api\Services\Company\FeeTypeService;

/**
 *
 * 银行收款账号
 */
class BankAccountController extends BaseController
{
    private $feeTypeService;
    public function __construct()
    {
        parent::__construct();
        $this->feeTypeService = new FeeTypeService;
    }
    /**
     * @OA\Post(
     *     path="/api/company/bankaccount/list",
     *     tags={"收款帐号"},
     *     summary="收款帐号列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"account_name"},
     *         @OA\Property(property="account_name",type="String",description="账户名称支持模糊查询"),
     *         @OA\Property(property="bank_name",type="String",description="银行名称"),
     *     ),
     *       example={ "account_number":"","bank_name":"","account_name":""}
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
        // QrCode::generate('Hello World!');
        $pagesize = $request->input('pagesize');
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config('per_size');
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
        $data = bankAccountModel::where(function ($q) use ($request) {
            $request->account_name && $q->where('account_name', 'like', '%' . $request->account_name . '%');
            $request->is_valid && $q->where('is_valid', $request->type);
            $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        })
            ->orderBy($orderBy, $order)->get()->toArray();

        foreach ($data as $k => &$v) {
            $v['proj_name'] = getProjNameById($v['proj_id']);

            $v['fee_types'] = $this->feeTypeService->getFeeNames($v['fee_type_id']);
        }
        // return response()->json(DB::getQueryLog());
        return $this->success($data);
    }

    public function show(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|int|gt:0',
        ]);
        $data = bankAccountModel::find($request->id)->toArray();
        $data['fee_types'] = $this->feeTypeService->getFeeNames($v['fee_type_id']);
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/company/bankaccount/add",
     *     tags={"收款帐号"},
     *     summary="收款帐号新增",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"bank_name","account_number","account_name"},
     *       @OA\Property(property="account_number",type="String",description="银行卡号"),
     *       @OA\Property(property="account_name",type="String",description="银行账户"),
     *       @OA\Property(property="bank_name",type="String",description="银行名称")
     *     ),
     *       example={ "account_number":"","bank_name":"","account_name":""})
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
            'account_name' => 'required|String|unique:bse_bank_account',
            'account_number' => 'required|String',
            'bank_name' => 'required|String|min:2',
            'fee_type_id' => 'required|String',
        ]);

        $DA =  $request->toArray();
        $user = auth('api')->user();
        $bankAccount = new bankAccountModel;
        $bankAccount['account_name'] = $DA['account_name'];
        $bankAccount['account_number'] = $DA['account_number'];
        $bankAccount['bank_name']   = $DA['bank_name'];
        $bankAccount['bank_addr']   = isset($DA['bank_addr']) ? $DA['bank_addr'] : "";
        $bankAccount['proj_id']     = $DA['proj_id'] ?? 0;
        $bankAccount['fee_type_id'] = $DA['fee_type_id'];
        $bankAccount['c_uid']       = $user->id;
        $bankAccount['is_valid']    = 1;
        $bankAccount['company_id']  = $user->company_id;
        $bankAccount['remark'] = isset($DA['remark']) ? $DA['remark'] : "";
        $res = $bankAccount->save();
        return $res ? $this->success('银行账户添加成功！') : $this->error('银行账户添加失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/company/bankaccount/edit",
     *     tags={"收款帐号"},
     *     summary="收款帐号编辑",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"bank_name","account_number","account_name"},
     *       @OA\Property(property="account_number",type="String",description="银行卡号"),
     *       @OA\Property(property="account_name",type="String",description="银行账户"),
     *       @OA\Property(property="bank_name",type="String",description="银行名称")
     *     ),
     *       example={"id":"", "account_number":"","bank_name":"","account_name":""}
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
            'id' => 'required|int|gt:0',
            'account_name' => 'required|String|',
            'account_number' => 'required|String|min:1',
            'bank_name' => 'required|String|min:1',
        ]);

        $DA =  $request->toArray();
        $map['account_name'] = $DA['account_name'];
        $checkAccount =  bankAccountModel::where($map)->where('id', '!=', $DA['id'])->exists();
        if ($checkAccount) {
            return $this->error('银行名字重复!');
        }
        $user = auth('api')->user();
        $bankAccount = bankAccountModel::find($DA['id']);
        $bankAccount['proj_id']       = $DA['proj_id'] ?? 0;
        $bankAccount['account_name'] = $DA['account_name'];
        $bankAccount['account_number'] = $DA['account_number'];
        $bankAccount['bank_name'] = $DA['bank_name'];
        $bankAccount['bank_addr'] = isset($DA['bank_addr']) ? $DA['bank_addr'] : "";
        $bankAccount['fee_type_id']       = $DA['fee_type_id'];
        $bankAccount['remark'] = isset($DA['remark']) ? $DA['remark'] : "";
        $bankAccount['u_uid'] = $user->id;
        // $bankAccount['company_id'] = $user->company_id;
        $res = $bankAccount->save();
        if ($res) {
            return $this->success('银行账户更新成功！');
        }
        return $this->error('银行账户更新失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/company/bankaccount/enable",
     *     tags={"收款帐号"},
     *     summary="收款帐号 禁用启用 ",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"Ids","is_vaild"},
     *       @OA\Property(
     *          property="Ids",
     *          type="list",
     *          description="ID集合"
     *       )
     *     ),
     *       example={"Ids": "[1]","is_vaild":0}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */
    public function enable(Request $request)
    {
        $validatedData = $request->validate([
            'Ids' => 'required|array',
            'is_valid' => 'required|int|in:1,0'
        ]);
        // return gettype($data['Ids']);
        DB::enableQueryLog();
        $data['is_valid'] = $request->is_valid;
        $res = bankAccountModel::whereIn('id', $request->Ids)->update($data);

        if ($res) {
            return $this->success('收款账户更新成功！');
        } else {
            return $this->error('收款账户更新失败！');
        }
    }
}
