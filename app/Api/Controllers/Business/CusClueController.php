<?php

namespace App\Api\Controllers\Business;

use Exception;
use DOMDocument;
use App\Enums\ClueStatus;
use App\Enums\InvoiceEnum;
use Illuminate\Http\Request;
use App\Api\Models\Tenant\Invoice;
use Maatwebsite\Excel\Facades\Excel;
use App\Api\Controllers\BaseController;
use App\Api\Excel\Business\CusClueExcel;
use App\Api\Excel\Business\CusClueImport;
use App\Api\Services\Business\CusClueService;

/**
 * @OA\Tag(
 *     name="客户线索",
 *     description="客户线索管理"
 * )
 */
class CusClueController extends BaseController
{

  private $clueService;
  public function __construct()
  {
    parent::__construct();
    $this->clueService = new CusClueService;
  }

  /**
   * @OA\Post(
   *     path="/api/business/clue/list",
   *     tags={"客户线索"},
   *     summary="客户线索列表",
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
    $map = array();
    // DB::enableQueryLog();
    $query = $this->clueService->model()->where($map)
      ->where(function ($q) use ($request) {
        $request->start_date && $q->where('clue_time', '>=', $request->start_date);
        $request->end_date && $q->where('clue_time', '<=', $request->end_date);
        $request->clue_type && $q->where('clue_type', $request->clue_type);
        $request->status && $q->where('status', $request->status);
        $request->phone && $q->where('phone', 'like', $request->end_date . "%");
      });
    $data = $this->pageData($query, $request);
    if ($request->export) {
      return $this->exportToExcel($data['result'], CusClueExcel::class);
    }
    $data['clueStat'] = $this->clueService->clueStat($request, $map);
    return $this->success($data);
  }



  /**
   * @OA\Post(
   *     path="/api/business/clue/add",
   *     tags={"客户线索"},
   *     summary="客户线索新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"name","clue_type","sex","phone"},
   *       @OA\Property(property="name",type="String",description="客户线索名称"),
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
    $res = $this->clueService->save($request->toArray(), $this->user);

    return $res ? $this->success('客户线索新增成功！') : $this->error("客户线索新增失败！");
  }

  /**
   * @OA\Post(
   *     path="/api/business/clue/edit",
   *     tags={"客户线索"},
   *     summary="客户线索编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"name","clue_type","phone","id"},
   *       @OA\Property(property="name",type="String",description="客户线索名称"),
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
    $res = $this->clueService->save($request->toArray(), $this->user);
    if ($res) {
      return $this->success('客户线索编辑成功！');
    }
    return $this->error("客户线索编辑失败！");
  }

  /**
   * @OA\Post(
   *     path="/api/business/clue/show",
   *     tags={"客户线索"},
   *     summary="客户线索查看",
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
    $data = $this->clueService->model()->find($request->id);
    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/business/clue/invalid",
   *     tags={"客户线索"},
   *     summary="客户线索设置无效",
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
    $clue = $this->clueService->model()->find($request->id);
    $clue->status = 3;
    $clue->invalid_time = nowYmd();
    $clue->invalid_reason = $request->invalid_reason;
    $res = $clue->save();
    return $this->success($res);
  }


  public function import(Request $request)
  {
    $request->validate([
      'file' => 'required|mimes:csv,txt,xlsx',
    ]);

    // Get the file from the request
    $file = $request->file('file');
    // Pass additional parameters (e.g., user) to the import class
    $import = new CusClueImport($this->user);

    // Import the data using the CusClueImport class
    try {
      Excel::import($import, $file);
      return $this->success("导入成功");
    } catch (\Exception $e) {
      // Handle the import error
      return $this->error("导入失败" . $e->getMessage());
    }
  }
}
