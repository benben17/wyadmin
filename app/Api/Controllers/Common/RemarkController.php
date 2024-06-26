<?php

namespace App\Api\Controllers\Common;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;


use Illuminate\Support\Facades\DB;
use App\Api\Services\Common\BseRemarkService;
use App\Enums\AppEnum;

/**
 * 公共方法备注 
 *
 * @Author leezhua
 * @DateTime 2024-03-25
 */
class RemarkController extends BaseController
{
    /**
     *  parent_type  1 房源 2 客户 3 供应商 4 政府关系 5 租户 6 线索 
     * const Channel       = 1;  //  渠道
     *    Customer      = 2;  // 客户
     *    Supplier      = 3;  //  供应商
     *    Relationship  = 4;  // 公共关系
     *     Tenant        = 5;  // 租户
     *    CusClue       = 6;  // 线索
     *    YhWorkOrder   = 7;  // 隐患工单
     *
     */
    protected $remarkService;
    public function __construct()
    {
        parent::__construct();
        $this->remarkService = new BseRemarkService;
    }

    /**
     * @OA\Post(
     *     path="/api/common/remark/list",
     *     tags={"公共"},
     *     summary="备注列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize","parent_type"},
     *       @OA\Property(
     *          property="pagesize",
     *          type="int",
     *          description="每页行数"
     *       ),
     *       @OA\Property(
     *          property="parent_id",
     *          type="int",
     *          description="父亲ID"
     *       ),
     *       @OA\Property(
     *          property="parent_type",
     *          type="int",
     *          description="父类型 1 channel 2 客户 3 供应商 4 政府关系 5 租户"
     *       ),
     *       @OA\Property(
     *          property="start_time",
     *          type="date",
     *          description="开始时间"
     *       ),
     *       @OA\Property(
     *          property="end_time",
     *          type="date",
     *          description="结束时间"
     *       ),
     *       @OA\Property(
     *          property="order",
     *          type="String",
     *          description="排序方式"
     *       ),
     *       @OA\Property(
     *          property="orderBy",
     *          type="String",
     *          description="排序字段  倒叙desc 正序asc"
     *       )
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
    public function list(Request $request)
    {
        $validatedData = $request->validate([
            'parent_type' => 'required|gt:0',
            // 'parent_id' => 'required|min:1',
        ]);
        $pagesize = $this->setPagesize($request);


        // 排序字段
        if ($request->input('orderBy')) {
            $orderBy = $request->input('orderBy');
        } else {
            $orderBy = 'created_at';
        }
        // 排序方式desc 倒叙 asc 正序
        if ($request->input('order')) {
            $orderByAsc = $request->input('order');
        } else {
            $orderByAsc = 'desc';
        }
        DB::enableQueryLog();
        $data = $this->remarkService->model()
            ->where(function ($query) use ($request) {
                $request->parent_type && $query->where('parent_type', $request->parent_type);
                $request->parent_id && $query->where('parent_id', $request->parent_id);
            })
            ->orderBy($orderBy, $orderByAsc)
            ->paginate($pagesize)->toArray();

        // return response()->json(DB::getQueryLog());
        $data = $this->handleBackData($data);
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/common/remark/save",
     *     tags={"公共"},
     *     summary="新增备注",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"parent_id","parent_type","remark_content","c_user"},
     *       @OA\Property(
     *          property="parent_id",
     *          type="int",
     *          description="ID 渠道ID 租户ID"
     *       ),
     *       @OA\Property(
     *          property="parent_type",
     *          type="int",
     *          description="1 房源 "
     *       ),
     *       @OA\Property(
     *          property="remark_content",
     *          type="String",
     *          description="备注内容"
     *       )
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
    public function save(Request $request)
    {
        $validatedData = $request->validate([
            'parent_id' => 'required|min:1',
            'parent_type' => 'required|min:1',
            'remark_content' => 'required|String|min:1',
            'c_user' => 'required|String',
        ]);

        $DA =  $request->toArray();
        $user = auth('api')->user();
        // $DA['parent_id'] = $DA['parent_id'];
        // $DA['parent_type'] = $DA['parent_type'];
        $res = $this->remarkService->save($DA, $user);

        if ($res) {
            return $this->success('备注添加成功！');
        } else {
            return $this->error('备注添加失败！');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/common/remark/del",
     *     tags={"公共"},
     *     summary="删除备注",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="ID"
     *       )
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
    public function delete(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required',
        ]);

        $DA =  $request->toArray();
        $res = $this->remarkService->model()->find($DA['id'])->delete();
        if ($res) {
            return $this->success('备注删除成功！');
        } else {
            return $this->error('备注删除失败！');
        }
    }
}
