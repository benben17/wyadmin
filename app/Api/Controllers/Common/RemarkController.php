<?php
namespace App\Api\Controllers\Common;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;


use Illuminate\Support\Facades\DB;
use App\Api\Services\BseRemark as remarkService;
use App\Api\Models\Common\Remark as remarkModel;



/**
 *  parent_type  1 房源
 *
 */
class RemarkController extends BaseController
{

    public function __construct()
    {
        $this->uid  = auth()->payload()->get('sub');
        if(!$this->uid){
            return $this->error('用户信息错误!');
        }
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
    public function list(Request $request){
        $validatedData = $request->validate([
            'parent_type' => 'required|in:1,2,3,4,5',
            'parent_id' => 'required|min:1',
        ]);
        $pagesize = $request->input('pagesize');
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config('per_size');
        }
        if($pagesize == '-1'){
            $pagesize = config('export_rows');
        }

        $map['parent_type'] = $request->parent_type;
        $map['parent_id'] = $request->parent_id;

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
        $data = remarkModel::where($map)
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
            'parent_type' => 'required|min:1|in:1,2,3,4,5',
            'remark_content' => 'required|String|min:1',
            'c_user' => 'required|String',
        ]);

        $DA =  $request->toArray();
        $user = auth('api')->user();
        // $DA['parent_id'] = $DA['parent_id'];
        // $DA['parent_type'] = $DA['parent_type'];
        $remark = new remarkService;
        $res = $remark->save($DA,$user);

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
        $res = remarkModel::find($DA['id'])->delete();
        if ($res) {
            return $this->success('备注删除成功！');
        } else {
            return $this->error('备注删除失败！');
        }
    }
}