<?php

namespace App\Api\Controllers\Company;

use JWTAuth;
// use App\Exceptions\ApiException;
use App\Api\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Api\Models\Company\CompanyDict as DictModel;
use App\Api\Models\Company\CompanyDictType as DictTypeModel;

class DictController extends BaseController
{

    public function __construct()
    {

        $this->uid  = auth()->payload()->get('sub');
        if (!$this->uid) {
            return $this->error('用户信息错误');
        }
        $this->company_id = getCompanyId($this->uid);
    }
    /**
     * @OA\Post(
     *     path="/api/company/dict/list",
     *     tags={"系统业务字典"},
     *     summary="业务全局字典列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"dict_key"},
     *       @OA\Property(
     *          property="dict_key",
     *          type="String",
     *          description="字典Key channel_type:渠道类型"
     *       )
     *     ),
     *       example={
     *              "dict_key":"channel_type"
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
        $validatedData = $request->validate([
            'dict_key' => 'required|String|min:1'
        ]);   //

        $map = array();
        if ($request->is_vaild) {
            $map['is_vaild'] = $request->input('is_vaild');
        }

        DB::enableQueryLog();
        $data = DictModel::where('dict_key', $request->dict_key)
            ->where($map)
            ->whereIn('company_id', getCompanyIds($this->uid))
            ->orderBy('dict_key')
            ->get()->toArray();
        // $DA[$v] = $data;
        // return response()->json(DB::getQueryLog());

        return $this->success($data);
    }

    /**
     * @OA\Post(
     *     path="/api/company/dict/type",
     *     tags={"系统业务字典"},
     *     summary="字典类型列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={""}
     *
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
    public function dictType(Request $request)
    {
        // return $companyIds;
        DB::enableQueryLog();
        $data = DictTypeModel::where(function ($q) use ($request) {
            $request->is_edit && $q->where('is_edit', $request->is_edit);
        })->get()->toArray();
        // return response()->json(DB::getQueryLog());
        if ($data) {
            return $this->success($data);
        }
        return $this->error('获取数据失败');
    }
    /**
     * @OA\Post(
     *     path="/api/company/dict/add",
     *     tags={"系统业务字典"},
     *     summary="业务全局字典新增",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"dict_key","dict_value"},
     *       @OA\Property(
     *          property="dict_key",
     *          type="String",
     *          description="字典Key channel_type:渠道类型"
     *       ),
     *       @OA\Property(
     *          property="dict_value",
     *          type="String",
     *          description="值"
     *       )
     *     ),
     *       example={
     *              "dict_key":"channel_type","dict_value":"中介"
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
            'dict_key' => 'required|String|max:64',
            'dict_value' => 'required|String|max:256',
        ]);
        $data = $request->toArray();

        $map['dict_key']    = $data['dict_key'];
        $map['dict_value']  = $data['dict_value'];
        $checkDict = DictModel::where($map)->exists();
        if ($checkDict) {
            return $this->error('数据重复');
        }
        $dict = new DictModel;
        $dict->company_id = $this->company_id;
        $dict->dict_key = $data['dict_key'];
        $dict->dict_value = $data['dict_value'];
        $dict->is_vaild = 1;
        $dict->c_uid = $this->uid;
        $res = $dict->save();

        if ($res) {
            return $this->success('数据添加成功');
        } else {
            return $this->error('数据添加失败');
        }
    }
    /**
     * @OA\Post(
     *     path="/api/company/dict/edit",
     *     tags={"系统业务字典"},
     *     summary="字典更新",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id","dict_key","dict_value"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="字典ID"
     *       ),
     *       @OA\Property(
     *          property="dict_key",
     *          type="String",
     *          description="字典Key channel_type:渠道类型"
     *       ),
     *       @OA\Property(
     *          property="dict_value",
     *          type="String",
     *          description="值"
     *       )
     *     ),
     *       example={
     *             "id":1, "dict_key":"channel_type","dict_value":"中介"
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
            'id'    => 'required|min:1',
            'dict_key' => 'required|String|max:64',
            'dict_value' => 'required|String|max:256',
        ]);
        $data = $request->toArray();
        $data['u_uid'] = $this->uid;
        $data['company_id'] = $this->company_id;

        $map['dict_key'] = $data['dict_key'];
        $map['dict_value'] = $data['dict_value'];
        // DB::enableQueryLog();
        $checkDict = DictModel::where($map)
            ->where('id', '!=', $request['id'])->exists();

        // return response()->json(DB::getQueryLog());
        if ($checkDict) {
            return $this->error('数据重复');
        }
        $dict = DictModel::where('id', $request['id'])->update($data);
        if ($dict) {
            return $this->success('数据更新成功');
        } else {
            return $this->error('数据更新失败');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/company/dict/del",
     *     tags={"系统业务字典"},
     *     summary="字典更新",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(property="id",type="int",description="字典ID")
     *     ),
     *       example={
     *             "id":"1"
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
            'id'    => 'required|array',
        ]);
        $data = $request->toArray();
        $map['company_id'] = $this->company_id;
        $res = DictModel::where($map)->whereId($request->id)->delete();
        if ($res) {
            return $this->success('删除成功。');
        } else {
            return $this->error('删除失败！');
        }
    }
    /**
     * @OA\Post(
     *     path="/api/company/dict/enable",
     *     tags={"系统业务字典"},
     *     summary="业务全局字典启用禁用",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"Ids","is_vaild"},
     *       @OA\Property(
     *          property="Ids",
     *          type="list",
     *          description="字典ID集合"
     *       ),
     *       @OA\Property(
     *          property="is_vaild",
     *          type="int",
     *          description="1:enable 0:disable"
     *       )
     *     ),
     *       example={
     *             "dictIds":"[1,2]", "is_vaild":"1"
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
            'Ids'    => 'array',
            'is_vaild' =>  'required|in:0,1',
        ]);
        $data['is_vaild'] = $request['is_vaild'];
        $res = DictModel::whereIn('id', $request['dictIds'])
            ->update($data);
        if ($res) {
            return $this->success('更新成功！');
        } else {
            return $this->error('更新失败');
        }
    }
}
