<?php

namespace App\Api\Controllers\Common;

use JWTAuth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Api\Controllers\BaseController;
use App\Api\Services\Common\AttachmentService;



/**
 * @OA\Tag(
 *     name="公共方法",
 *     description="附件管理"
 * )
 */
class AttachmentController extends BaseController
{
    private $attachment;
    public function __construct()
    {
        parent::__construct();
        // $this->user = auth('api')->user();
        $this->attachment = new AttachmentService;
    }
    /**
     * @OA\Post(
     *     path="/api/common/attach/add",
     *     tags={"公共方法"},
     *     summary="附件上传",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"parent_id","parent_type","name","file_path"},
     *       @OA\Property(
     *          property="parent_id",
     *          type="int",
     *          description="ID 渠道ID 租户ID"
     *       ),
     *       @OA\Property(
     *          property="parent_type",
     *          type="int",
     *          description="1 channel 2 租户 3 合同  4 供应商 5 公共关系"
     *       ),
     *       @OA\Property(
     *          property="name",
     *          type="String",
     *          description="附件名称"
     *       ),
     *       @OA\Property(
     *          property="file_path",
     *          type="String",
     *          description="文件路径"
     *       )
     *     ),
     *       example={"parent_id": "","parent_type":"","name":"","file_path":""}
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
            'parent_id' => 'required|min:1',
            'parent_type' => 'required|numeric|in:1,2,3,4,5',
            'name' => 'required|String|min:1',
            'file_path' => 'required|String|max:512',
        ]);
        $DA =  $request->toArray();

        $res = $this->attachment->save($DA, $this->user);
        if ($res) {
            return $this->success('附件上传成功。');
        }
        return $this->error('附件上传失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/common/attach/del",
     *     tags={"公共方法"},
     *     summary="附件删除 ",
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

        $res = $this->attachment->delete($data['Ids']);

        return $res ? $this->success('附件删除成功。') : $this->error('附件删除失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/common/attach/list",
     *     tags={"公共方法"},
     *     summary="附件列表 ",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"parent_id","parent_type"},
     *       @OA\Property(
     *          property="parent_id",
     *          type="int",
     *          description="父id"
     *       ),
     *       @OA\Property(
     *          property="parent_type",
     *          type="int",
     *          description="父类型"
     *       )
     *     ),
     *       example={
     *              "parent_id": "","parent_type":"1 channel 2 租户 3 合同  4 供应商 5 公共关系"
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
            'parent_id' => 'required|int|gt:0',
            'parent_type' => 'required|int|gt:0',
        ]);

        $query = $this->attachment->model()
            ->where('parent_id', $request->parent_id)
            ->where('parent_type', $request->parent_type);
        $data = $this->pageData($query, $request);
        return $this->success($data);
    }
}
