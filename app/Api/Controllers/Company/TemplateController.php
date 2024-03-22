<?php

namespace App\Api\Controllers\Company;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Api\Models\Company\Template as TemplateModel;
use App\Api\Models\Company\TemplateParm as TemplateParmModel;
use App\Api\Services\Template\TemplateService;

/**
 *
 *  type 1 合同。2 账单
 *
 */
class TemplateController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * @OA\Post(
     *     path="/api/sysconfig/template/list",
     *     tags={"合同模版"},
     *     summary="合同模版列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"type"},
     *       @OA\Property(property="type",type="int",description="类型1 合同 2 账单")
     *     ),
     *       example={"type":""}
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
            'type' => 'required|numeric|in:1,2'
        ]);

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
        $data = TemplateModel::where(function ($q) use ($request) {
            $request->name && $q->where('name', 'like', "%" . $request->name . "%");
            $request->type && $q->where('type', $request->type);
        })
            ->orderBy($orderBy, $order)
            ->get();
        // return response()->json(DB::getQueryLog());
        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/sysconfig/template/add",
     *     tags={"合同模版"},
     *     summary="合同模版新增",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"name","file_path","type"},
     *       @OA\Property(property="type",type="int",description="类型1 合同 2 账单"),
     *       @OA\Property(property="name",type="String",description="合同模版名称"),
     *       @OA\Property(property="file_path",type="String",description="文件路径")
     *     ),
     *       example={"type":"", "name":"","file_path":""}
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
            'type' => 'required|numeric|in:1,2',
            'name' => 'required|String|min:1|max:64',
            'file_path' => 'required|String',
        ]);
        $DA =  $request->toArray();
        $map['company_id'] = $this->company_id;
        $map['name'] = $DA['name'];

        $checkTemplateCount = TemplateModel::where('type', $DA['type'])->count();
        $maxCount = config('max_contract_template');
        if ($DA['type'] == 1) {
            if ($checkTemplateCount >= $maxCount) {
                return $this->error("每个公司最多能添加 ${maxCount} 个模版");
            }
        } else {
            $maxCount = $maxCount * 4;
            if ($checkTemplateCount >= $maxCount) {
                return $this->error("每个公司最多能添加 ${maxCount} 个模版");
            }
        }

        $checkTemplate = TemplateModel::where($map)->exists();
        if ($checkTemplate) {
            return $this->error('模版名称重复！');
        }
        $user = auth('api')->user();
        $template = new TemplateService;
        $res = $template->saveTemplate($DA, $user);
        if ($res) {
            return $this->success('模版添加成功！');
        }
        return $this->error('模版添加失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/sysconfig/template/edit",
     *     tags={"合同模版"},
     *     summary="合同模版编辑",
     *    @OA\RequestBody(
     *       @OA\MediaType(mediaType="application/json",
     *       @OA\Schema(schema="UserModel",required={"name","file_name","id"},
     *       @OA\Property(property="id",type="int",description="合同ID"),
     *       @OA\Property(property="name",type="String",description="合同模版名称"),
     *       @OA\Property(property="file_name",type="String",description="合同模版文件名")
     *       ),
     *       example={"id":"", "account_number":"","bank_name":"","account_name":""})
     *     ),
     *     @OA\Response(response=200,description="")
     * )
     */
    public function update(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|int|gt:0',
            'name' => 'required|String|min:1',
            'file_name' => 'required|String|min:1',
        ]);

        $DA =  $request->toArray();
        $map['name'] = $DA['name'];
        $map['company_id'] = $this->company_id;

        $checkTemplate = TemplateModel::where($map)->where('id', '!=', $DA['id'])->exists();
        if ($checkTemplate) {
            return $this->error('模版名称重复！');
        }
        $user = auth('api')->user();
        $template = new TemplateService;
        $res = $template->saveTemplate($DA, $user);
        if ($res) {
            return $this->success('合同模版更新成功！');
        }
        return $this->error('合同模版更新失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/sysconfig/template/del",
     *     tags={"合同模版"},
     *     summary="合同模版 ",
     *    @OA\RequestBody(
     *       @OA\MediaType(mediaType="application/json",
     *       @OA\Schema(schema="UserModel",required={"Ids"},
     *       @OA\Property(property="Ids",type="list",description="ID集合")
     *      ),
     *       example={"Ids": "[1]"})
     *    ),
     *     @OA\Response(response=200,description="")
     * )
     */
    public function delete(Request $request)
    {
        $validatedData = $request->validate([
            'Ids' => 'required|array',
        ]);
        // return gettype($data['Ids']);
        DB::enableQueryLog();

        $res = TemplateModel::whereIn('id', $request->Ids)->delete();

        if ($res) {
            return $this->success('合同模版删除成功！');
        }
        return $this->error('合同模版删除失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/sysconfig/template/parm",
     *     tags={"合同模版"},
     *     summary="获取合同模版变量信息 ",
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
    public function templateParm(Request $request)
    {
        DB::enableQueryLog();
        $validatedData = $request->validate([
            'type' => 'required|numeric|in:1,2',
        ]);
        $data = array();
        $map['type'] = $request->type;
        $types = TemplateParmModel::select("parm_type")->where($map)
            ->groupBy('parm_type')->get()->toArray();
        foreach ($types as $k => $v) {

            $data[$k]['title'] = $v['parm_type'];
            $data[$k]['list'] = TemplateParmModel::where($map)
                ->where('parm_type', $v['parm_type'])
                ->get()->toArray();
        }
        return $this->success($data);
    }
}
