<?php

namespace App\Api\Controllers\Company;

use JWTAuth;
// use App\Exceptions\ApiException;
use App\Api\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Api\Services\Company\FeeTypeService;

class FeeTypeController extends BaseController
{

  public function __construct()
  {

    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->feeService = new FeeTypeService;
  }

  /**
   * @OA\Post(
   *     path="/api/company/fee/list",
   *     tags={"费用类型"},
   *     summary="费用类型列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={},
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

    $companyIds = getCompanyIds($this->uid);
    $data = $this->feeService->model()
      ->whereIn('company_id', $companyIds)
      ->orderBy($orderBy, $order)->get()->toArray();
    // return response()->json(DB::getQueryLog());
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/company/fee/add",
   *     tags={"费用类型"},
   *     summary="费用类型新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"fee_name"},
   *       @OA\Property(property="fee_name",type="String",description="费用类型")
   *     ),
   *       example={
   *              "fee_name":"中介费"
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
      'fee_name' => 'required|String|max:32',
    ]);

    $where['fee_name'] = $request->fee_name;
    $companyIds = getCompanyIds($this->uid);
    $count = $this->feeService->model()->whereIn('company_id', $companyIds)->where($where)->count();
    if ($count > 0) {
      return $this->error('数据重复!');
    }
    $res = $this->feeService->save($request->toArray(), $this->uid);
    if ($res) {
      return $this->success('数据添加成功');
    } else {
      return $this->error('数据添加失败');
    }
  }

  /**
   * @OA\Post(
   *     path="/api/company/fee/enable",
   *     tags={"费用类型"},
   *     summary="费用类型启用禁用，当启用的时候禁用，当禁用的时候启用",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"ids","is_vaild"},
   *        @OA\Property(property="is_vaild",type="int",description="1启用0禁用"),
   *        @OA\Property(property="ids",type="list",description="id集合")
   *     ),
   *       example={ "is_vaild":"1","ids":"[]"
   *           }
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
      'ids' => 'required|array',
      'is_vaild' => 'required|numeric|in:0,1'
    ]);

    $res = $this->feeService->enable($request->ids, $request->is_vaild, $this->uid);
    if ($res) {
      return $this->success('数据更新成功。');
    } else {
      return $this->error('数据更新失败!');
    }
  }
}
