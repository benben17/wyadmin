<?php

namespace App\Api\Controllers\Business;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Services\Business\IncomeTelService;
use App\Api\Services\Business\IncomingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Exception;

use App\Enums\AppEnum;

class IncomeController extends BaseController
{

  public function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->user = auth('api')->user();
  }

  /**
   * @OA\Post(
   *     path="/api/business/income/list",
   *     tags={"来电"},
   *     summary="来电列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"pagesize","orderBy","order"},
   *       @OA\Property(property="list_type",type="int",description="// 1 客户列表 2 在租户 3 退租租户")
   *     ),
   *       example={
   *
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
    // $validatedData = $request->validate([
    //     'type' => 'required|int|in:1,2,3', // 1 客户列表 2 在租户 3 退租租户
    // ]);
    $pagesize = $request->input('pagesize');
    if (!$pagesize || $pagesize < 1) {
      $pagesize = config('per_size');
    }
    if ($pagesize == '-1') {
      $pagesize = config('export_rows');
    }
    $map = array();
    if ($request->sex) {
      $map['sex'] = $request->sex;
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
    $incomeService = new IncomeTelService;
    $result = $incomeService->model()->where($map)
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    $data = $this->handleBackData($result);
    return $this->success($data);
  }


  /**
   * @OA\Post(
   *     path="/api/business/income/add",
   *     tags={"来电"},
   *     summary="来电新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"name","income_type","sex","phone"},
   *       @OA\Property(property="name",type="String",description="来电名称"),
   *       @OA\Property(property="income_type",type="String",description="来源类型"),
   *       @OA\Property( property="sex",type="int",description="1:男2 女")
   *     ),
   *       example={
   *              "name": "1","income_type":"type","sex":"","phone",""
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
      'income_type' => 'required|String|max:64',
      'phone' => 'required',
      'sex' => 'required|numeric|in:1,2',
    ]);

    // DB::transaction(function () use ($request) {
    $incomeService = new IncomeTelService;
    $res = $incomeService->save($request->toArray(), $this->user);
    if ($res) {
      return $this->success('来电新增成功！');
    }
    return $this->error("来电新增失败！");
  }

  /**
   * @OA\Post(
   *     path="/api/business/income/edit",
   *     tags={"来电"},
   *     summary="来电编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"name","income_type","sex","phone","id"},
   *       @OA\Property(property="name",type="String",description="来电名称"),
   *       @OA\Property(property="income_type",type="String",description="来源类型"),
   *       @OA\Property( property="sex",type="int",description="1:男2 女")
   *     ),
   *       example={
   *              "name": "1","income_type":"type","sex":"","phone",""
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
      'id' => 'required|numeric|gt:0',
      'income_type' => 'required|String|max:64',
      'phone' => 'required',
      'sex' => 'required|numeric|in:1,2',
    ]);
    $incomeService = new IncomeTelService;
    $res = $incomeService->save($request->toArray(), $this->user);
    if ($res) {
      return $this->success('来电编辑成功！');
    }
    return $this->error("来电编辑失败！");
  }
}
