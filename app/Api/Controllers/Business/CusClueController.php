<?php

namespace App\Api\Controllers\Business;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Services\Business\CusClueService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Exception;


class CusClueController extends BaseController
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
   *     path="/api/business/clue/list",
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
    $incomeService = new CusClueService;
    DB::enableQueryLog();
    $result = $incomeService->model()->where($map)
      ->where(function ($q) use ($request) {
        $request->start_time && $q->where('clue_time', '>=', $request->start_time);
        $request->end_time && $q->where('clue_time', '<=', $request->end_time);
      })
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();

    $result = $this->handleBackData($result);
    return $this->success($result);
  }


  /**
   * @OA\Post(
   *     path="/api/business/clue/add",
   *     tags={"来电"},
   *     summary="来电新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"name","clue_type","sex","phone"},
   *       @OA\Property(property="name",type="String",description="来电名称"),
   *       @OA\Property(property="clue_type",type="String",description="来源类型"),
   *      @OA\Property(property="phone",type="String",description="电话"),
   *     ),
   *       example={
   *              "name": "1","clue_type":"type","sex":"","phone",""
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
      'clue_type' => 'required|gt:0',
      'phone' => 'required',
    ]);

    // DB::transaction(function () use ($request) {
    $incomeService = new CusClueService;
    $res = $incomeService->save($request->toArray(), $this->user);
    if ($res) {
      return $this->success('来电新增成功！');
    }
    return $this->error("来电新增失败！");
  }

  /**
   * @OA\Post(
   *     path="/api/business/clue/edit",
   *     tags={"来电"},
   *     summary="来电编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"name","clue_type","sex","phone","id"},
   *       @OA\Property(property="name",type="String",description="来电名称"),
   *       @OA\Property(property="clue_type",type="int",description="来源类型"),
   *       @OA\Property( property="phone",type="int",description="")
   *     ),
   *       example={
   *              "name": "1","clue_type":"type","sex":"","phone",""
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
      'clue_type' => 'required|gt:0',
      'phone' => 'required',
    ]);
    $incomeService = new CusClueService;
    $res = $incomeService->save($request->toArray(), $this->user);
    if ($res) {
      return $this->success('来电编辑成功！');
    }
    return $this->error("来电编辑失败！");
  }

  /**
   * @OA\Post(
   *     path="/api/business/clue/show",
   *     tags={"来电"},
   *     summary="来电查看",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="String",description="id")
   *     ),
   *       example={
   *              "id": "1"
   *           }
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
    $incomeService = new CusClueService;
    $data = $incomeService->model()->find($request->id);
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/business/clue/invalid",
   *     tags={"来电"},
   *     summary="来电设置无效",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="String",description="id")
   *     ),
   *       example={
   *              "phone",""
   *           }
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function invalid(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required|numeric|gt:0',
    ]);
    $clueService = new CusClueService;
    $clue = $clueService->model()->find($request->id);
    $clue->status = 3;
    $clue->invalid_time = nowYmd();
    $clue->remark = $request->remark;
    $res = $clue->save();
    return $this->success($res);
  }
}
