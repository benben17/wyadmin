<?php

namespace App\Api\Controllers\Sys;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Api\Controllers\BaseController;
use App\Models\Company as CompanyModel;
use App\Models\User as UserModel;
use App\Api\Services\Sys\UserServices;


class UserController extends BaseController
{
    /**
     * Create a new AuthController instance.
     * 要求附带email和password（数据来源users表）
     * @return void
     */

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @OA\Post(
     *     path="/api/sys/user/list",
     *     tags={"用户"},
     *     summary="获取用户列表",
     *     description="",
     *     @OA\Parameter(
     *         name="Authorization",
     *         description="Bearer {token}",
     *         required=false,
     *         in="header",
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="The result of tasks"
     *     )
     * )
     */

    public function index(Request $request)
    {
        $pagesize = $request->input('pagesize');
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config('app.pagesize');
        }
        if ($pagesize == -1) {
            $pagesize = config('export_rows');
        }

        $user = auth('api')->user();
        // $result = UserModel::with("role:id,name")->where('company_id',$user->company_id)->paginate($pagesize);
        DB::enableQueryLog();
        $result = UserModel::with("role:id,name")->with("group:id,name")->where(function ($query) use ($user, $request) {
            $request->input('name') && $query->where('name', 'like', '%' . $request->input('name') . '%');
            $request->input('realname') && $query->where('realname', 'like', '%' . $request->input('realname') . '%');
            $query->where('company_id', '=', $user->company_id);
            $user->is_admin || $query->where('is_admin', 0);
        })
            ->with('depart:id,name')
            ->paginate($pagesize)->toArray();
        // return response()->json(DB::getQueryLog());
        if (!$result) {
            return $this->error('查询失败!');
        }

        $data = $this->handleBackData($result);
        return $this->success($data);
    }
    /**
     * @OA\Post(
     *     path="/api/sys/user/add",
     *     tags={"用户"},
     *     summary="新增用户",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"name", "password","phone","role_id"},
     *       @OA\Property(
     *          property="name",
     *          type="string",
     *          description="用户名"
     *       ),
     *       @OA\Property(
     *          property="password",
     *          type="string",
     *          description="密码"
     *       ),
     *       @OA\Property(
     *          property="phone",
     *          type="string",
     *          description="手机号"
     *       ),
     *       @OA\Property(
     *          property="role_id",
     *          type="int",
     *          description="角色ID"
     *       )
     *     ),
     *       example={
     *              "name": "123456", "password": "123456","phone": "15827068282", "role_id": "1"
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
        $messages = ['phone.regex' => '手机号不合法',];
        $validator = \Validator::make($request->all(), [
            'phone' => 'required',
            'phone' => 'regex:/^1[345789][0-9]{9}$/'
        ], $messages);
        $error = $validator->errors()->first();
        if ($error) {
            return $this->error($error);
        }
        $validatedData = $request->validate([
            'name' => 'required|unique:users|max:20|min:4',
            'realname' => 'required|max:20|min:2',
            'password' => 'required|max:20|min:4',
            'phone' => 'unique:users',
            'role_id' => 'required|numeric',
            'group_id' => 'required|numeric'
        ]);
        $userinfo = auth('api')->user();
        $user = new UserModel;
        $user->name = $request->input('name');
        $user->phone = $request->input('phone');
        $user->password = Hash::make($request->input('password'));
        $user->role_id = $request->input('role_id');
        $user->group_id = $request->input('group_id');
        $user->depart_id = $request->input('depart_id');
        $user->is_manager = $request->input('is_manager');
        $user->remark = $request->input('remark');
        $user->realname = $request->input('realname');
        $user->company_id =  $userinfo->company_id;
        $user->c_uid = $userinfo->id;

        $result = $user->save();
        if (!$result) {
            return $this->error("添加用户失败！");
        }
        return $this->success("", "添加用户成功！");
    }
    /**
     * @OA\Post(
     *     path="/api/sys/user/update",
     *     tags={"用户"},
     *     summary="修改用户",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"name", "realnme","password","phone","role_id"},
     *       @OA\Property(
     *          property="name",
     *          type="string",
     *          description="用户名"
     *       ),
     *       @OA\Property(
     *          property="password",
     *          type="string",
     *          description="密码"
     *       ),
     *       @OA\Property(
     *          property="phone",
     *          type="string",
     *          description="手机号"
     *       ),
     *       @OA\Property(
     *          property="role_id",
     *          type="int",
     *          description="角色ID"
     *       )
     *     ),
     *       example={
     *              "name": "123456", "password": "123456","phone": "15827068282", "role_id": "1"
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
        $messages = ['phone.regex' => '手机号不合法', 'phone.unique' => '手机号已存在'];
        $validator = \Validator::make($request->all(), [
            'phone' => 'required',
            'phone' => 'regex:/^1[345789][0-9]{9}$/',
            'phone' => Rule::unique('users')->ignore($request->input('id'))
        ], $messages);
        $error = $validator->errors()->first();
        if ($error) {
            return $this->error($error);
        }
        $validatedData = $request->validate([
            'realname' => 'required',
            'role_id' => 'required|numeric',
            'group_id' => 'required|numeric'
        ]);
        $userinfo = auth('api')->user();
        $user = UserModel::find($request->input('id'));
        $user->realname = $request->input('realname');
        $user->phone = $request->input('phone');
        $user->role_id = $request->input('role_id');
        $user->group_id = $request->input('group_id');
        $user->depart_id = $request->input('depart_id');
        $user->is_manager = $request->input('is_manager');
        $user->remark = $request->input('remark');
        $user->company_id =  $userinfo->company_id;

        if ($request->input('password') != '') {
            $user->password = Hash::make($request->input('password'));
        }
        $user->c_uid = $userinfo->id;

        $result = $user->save();
        if (!$result) {
            return $this->error("修改用户失败！");
        }
        return $this->success("", "修改用户成功！");
    }

    /**
     * @OA\Post(
     *     path="/api/sys/user/show",
     *     tags={"用户"},
     *     summary="根据id获取用户信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"id"},
     *       @OA\Property(
     *          property="id",
     *          type="int",
     *          description="用户"
     *       )
     *     ),
     *       example={
     *              "id": 1
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
            'id' => 'required'
        ]);
        DB::enableQueryLog();
        $id = $request->input('id');
        $data = UserModel::with("role:id,name")->with("group:id,name")->with('depart:id,name')->find($id);
        if ($data) {
            return $this->success($data);
        } else {
            return $this->error('未查询到数据！');
        }
    }
    /**
     * @OA\Post(
     *     path="/api/sys/user/enable",
     *     tags={"用户"},
     *     summary="根据id删除用户信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"Ids","is_vaild"},
     *       @OA\Property(
     *          property="Ids",
     *          type="list",
     *          description="用户"
     *       ),
     *       @OA\Property(
     *          property="is_vaild",
     *          type="int",
     *          description="1 启用 0 禁用"
     *       )
     *     ),
     *       example={
     *              "Ids": 1 ,"is_vaild":0
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
            'Ids' => 'required|array',
            'is_vaild' => 'required|int|in:0,1',
        ]);
        $DA = $request->toArray();
        $res = UserModel::whereIn('id', $DA['Ids'])->update(['is_vaild' => $DA['is_vaild']]);

        if ($res) {
            return $this->success('用户更新成功!');
        } else {
            return $this->error('用户更新失败！');
        }
    }


    /**
     * @OA\Post(
     *     path="/api/sys/user/profile",
     *     tags={"用户"},
     *     summary="保存用户变量信息",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={},
     *       @OA\Property(
     *          property="proj_id",
     *          type="list",
     *          description="用户默认选择的项目ID"
     *       ),
     *       @OA\Property(
     *          property="page_rows",
     *          type="int",
     *          description="每页显示的行数"
     *       )
     *     ),
     *       example={
     *              "proj_id": 1 ,"page_rows":0
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
     */

    public function userProfile(Request $request)
    {
        $validatedData = $request->validate([
            'proj_id' => 'required|int:gt:0',
        ]);

        $DA['default_proj_id'] = $request->proj_id;
        $DA['page_rows'] = $request->page_rows;

        $userService = new UserServices;
        $res = $userService->saveUserProfile($DA, $this->uid);
        if ($res) {
            return $this->success('用户变量保存成功。');
        } else {
            return $this->error('用户变量保存失败！');
        }
    }
}
