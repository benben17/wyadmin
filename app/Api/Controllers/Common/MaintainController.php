<?php

namespace App\Api\Controllers\Common;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Api\Controllers\BaseController;
use App\Api\Excel\Common\MaintainExcel;
use App\Api\Services\Common\BseMaintainService as maintainService;


/**
 * @OA\Tag(
 *     name="维护",
 *     description="维护管理 parent_type 1 channel 2 客户 3 供应商 4 政府关系 5 租户"
 * )
 */
class MaintainController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }



    /**
     * @OA\Post(
     *     path="/api/common/maintain/list",
     *     tags={"维护"},
     *     summary="维护列表",
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"parent_id", "parent_type", "proj_ids", "maintain_types"},
     *                 @OA\Property(property="parent_id", type="int", description="父亲ID"),
     *                 @OA\Property(property="parent_type", type="int", description="1 channel 2 客户 3 供应商 4 政府关系 5 租户"),
     *                 @OA\Property(property="proj_ids", type="string", description="项目id 多个ID（,）逗号隔开"),
     *                 @OA\Property(property="maintain_types", type="string", description="维护类型中文数组"),
     *                 @OA\Property(property="start_time", type="string", format="date", description="开始时间"),
     *                 @OA\Property(property="end_time", type="string", format="date", description="结束时间"),
     *                 @OA\Property(property="order", type="string", description="排序方式"),
     *                 @OA\Property(property="orderBy", type="string", description="排序字段  倒叙desc 正序asc"),
     *             ),
     *             example={
     *                 "value": {
     *                     "parent_id": 123,
     *                     "parent_type": 1,
     *                     "proj_ids": "",
     *                     "maintain_types": "",
     *                     "start_time": "2022-01-01",
     *                     "end_time": "2022-12-31",
     *                     "order": "asc",
     *                     "orderBy": "id"
     *                 }
     *             }
     *         )
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
            // 1 channel 2 客户 3 供应商 4 政府关系 5 租户
            'parent_type' => 'required|numeric|in:1,2,3,4,5',
        ]);

        $pagesize = $this->setPagesize($request);
        $map = array();
        if (isset($request->parent_id)) {
            $map['parent_id'] = $request->parent_id;
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

        // Log::error($this->user);
        DB::enableQueryLog();
        $maintainService  = new maintainService;
        $maintain = $maintainService->maintainModel()->where($map)
            ->with('createUser:id,realname')
            ->where(function ($q) use ($request) {
                $request->parent_type && $q->where('parent_type', $request->parent_type);
                $request->proj_ids && $q->whereIn('proj_id', str2Array($request->proj_ids));
                $request->create_person && $q->where('c_username', 'like', '%' . $request->create_person . '%');
                if ($request->start_time) {
                    $q->where('maintain_date', '>=', $request->start_time);
                }
                if ($request->end_time) {
                    $q->where('maintain_date', '<=', $request->end_time);
                }
                if ($request->maintain_user) {
                    $q->where('maintain_user', 'like', "%" . $request->maintain_user . "%");
                }
                if ($request->maintain_types) {
                    $q->whereIn('maintain_type', $request->maintain_types);
                }
                if (!$this->user['is_admin']) {
                    $q->where('role_id', $this->user['role_id']);
                }
            })
            ->orderBy($orderBy, $order)
            ->paginate($pagesize)->toArray();
        // return response()->json(DB::getQueryLog());
        // 获取主表名称
        foreach ($maintain['data'] as $k => &$v) {
            $v['name'] = $maintainService->getParentName($v['parent_id'], $request->parent_type);
            $v['maintain_type_label'] = getDictName($v['maintain_type']);
        }

        $data = $this->handleBackData($maintain);
        if ($request->export) {
            return $this->exportToExcel($data['result'], MaintainExcel::class);
        }
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/common/maintain/save",
     *     tags={"维护"},
     *     summary="维护记录编辑新增 id >0 编辑 id=0新增",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"parent_id","parent_type","maintain_type","maintain_date","maintain_record","maintain_user","c_user"},
     *       @OA\Property(
     *          property="parent_id",
     *          type="int",
     *          description="ID 渠道ID 租户ID"
     *       ),
     *       @OA\Property(
     *          property="parent_type",
     *          type="int",
     *          description="1 channel 2 客户 3 供应商 4 政府关系 5 租户"
     *       ),
     *       @OA\Property(
     *          property="maintain_type",
     *          type="String",
     *          description="维护类型"
     *       ),
     *       @OA\Property(
     *          property="maintain_date",
     *          type="date",
     *          description="维护时间"
     *       ),
     *       @OA\Property(
     *          property="maintain_record",
     *          type="String",
     *          description="维护内容"
     *       ),
     *       @OA\Property(
     *          property="maintain_feedback",
     *          type="String",
     *          description="维护反馈"
     *       ),
     *       @OA\Property(
     *          property="maintain_user",
     *          type="String",
     *          description="渠道联系人"
     *       ),
     *       @OA\Property(
     *          property="c_username",
     *          type="String",
     *          description="维护人"
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
    public function store(Request $request)
    {

        $validatedData = $request->validate([
            'parent_id'       => 'required|min:1',
            'parent_type'     => 'required|numeric|in:1,2,3,4,5',
            'maintain_type'   => 'required|String|min:1',
            'maintain_date'   => 'required|date',
            'maintain_record' => 'required|String|min:1',
            // 'proj_id'         => 'required|numeric',
        ]);

        $DA =  $request->toArray();
        $user = auth('api')->user();
        // $DA['parent_id'] = $DA['parent_id'];
        // $DA['parent_type'] = $DA['parent_type'];
        $maintain = new maintainService;
        $DA['c_username'] = isset($DA['c_username']) ? $user->realname : "";
        $res = $maintain->add($DA, $user);
        if ($res) {
            return $this->success('维护记录添加成功！');
        }
        return $this->error('维护记录添加失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/common/maintain/del",
     *     tags={"维护"},
     *     summary="维护记录删除 ",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"Ids"},
     *       @OA\Property(
     *          property="Ids",
     *          type="list",
     *          description="ID集合"
     *       )
     *     ),
     *       example={
     *              "Ids": "[1]"
     *           }
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
            'Ids' => 'required|array',
        ]);
        $data = $request->toArray();
        // return gettype($data['Ids']);
        $maintain = new maintainService;
        DB::enableQueryLog();
        $res = $maintain->delete($data['Ids']);
        // return response()->json(DB::getQueryLog());
        // return $res;
        if ($res) {
            return $this->success('维护记录删除成功！');
        } else {
            return $this->error('维护记录删除失败！');
        }
    }


    /**
     * @OA\Post(
     *     path="/api/common/maintain/show",
     *     tags={"维护"},
     *     summary="维护记录查看",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id","parent_type"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="维护记录ID"
     *       ),
     *       @OA\Property(
     *          property="parent_type",
     *          type="int",
     *          description="1 channel 2 客户 3 供应商 4 政府关系 5 租户"
     *       )
     *     ),
     *       example={
     *              "id": "1","parent_type":"1"
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
            'id' => 'required|int',
            'parent_type' => 'required|int|in:1,2,3,4,5',
        ]);

        $maintain = new maintainService;
        $data = $maintain->maintainModel()->find($request->id);
        $data['name'] = $maintain->getParentName($data['parent_id'], $data['parent_type']);
        $data['maintain_type_label'] = getDictName($data['maintain_type']);
        // $data = $maintain->showMaintain($request->id,);
        return $this->success($data);
    }
}
