<?php
namespace App\Api\Controllers\Sys;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Api\Controllers\BaseController;
use App\Api\Models\Sys\UserGroup as UserGroupModel;


class UserGroupController extends BaseController
{
    /**
     * Create a new AuthController instance.
     * 要求附带email和password（数据来源users表）
     * @return void
     */
    private $uid = 0;
    public function __construct()
    {
        $this->uid  = auth()->payload()->get('sub');
        if(!$this->uid){
            return $this->error('用户信息错误');
        }
    }

    /**
    * @OA\Post(
    *     path="/api/sys/usergroup/list",
    *     tags={"用户"},
    *     summary="获取用户组列表",
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
       if(!$pagesize || $pagesize <1){
       	$pagesize = config('app.pagesize');
       }
       if($pagesize==-1){
           $pagesize = config('export_rows');
       }
       $user = auth('api')->user();
       $result = UserGroupModel::withCount('user')->where(function ($query) use($user,$request) {
           $request->input('name') && $query->where('name', 'like', '%' . $request->input('name') . '%');
           $query->where('company_id', $user->company_id);
       })->paginate($pagesize)->toArray();
       if(!$result){
          return $this->error('查询失败!');
       }
       $data = $this->handleBackData($result);
       return $this->success($data);
    }
    /**
     * @OA\Post(
     *     path="/api/sys/usergroup/add",
     *     tags={"用户"},
     *     summary="新增用户组",
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
     *          property="project_limit",
     *          type="string",
     *          description="项目组"
     *       ),
     *     ),
     *       example={
     *              "name": "123456", "project_limit": "123456"
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
                    'name' => 'required|max:20|min:1',
        ]);
        $project_limit = implode(",", $request->input('project_limit'));
        $userinfo = auth('api')->user();
        $user = new UserGroupModel;
        $user->name = $request->input('name');
        $user->project_limit = $project_limit;
        $user->remark = $request->input('remark');
        $user->c_uid = $userinfo->id;
        $user->company_id = $userinfo->company_id;

        $result=$user->save();
        if(!$result){
             return $this->error("添加用户组失败！");
        }
        return $this->success("","添加用户组成功！");
    }
    /**
     * @OA\Post(
     *     path="/api/sys/usergroup/update",
     *     tags={"用户"},
     *     summary="修改用户组",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"name", "project_limit"},
     *       @OA\Property(
     *          property="name",
     *          type="string",
     *          description="用户名"
     *       ),
     *       @OA\Property(
     *          property="project_limit",
     *          type="string",
     *          description="项目集合"
     *       )
     *     ),
     *       example={
     *              "name": "123456", "project_limit": ""
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
                    'name' => 'required|max:20|min:1',
        ]);
        $project_limit = implode(",", $request->input('project_limit'));
        $userinfo = auth('api')->user();
        $user = UserGroupModel::find($request->input('id'));
        $user->name = $request->input('name');
        $user->project_limit = $project_limit;
        $user->remark = $request->input('remark');
        $user->c_uid = $userinfo->id;
        $user->company_id = $userinfo->company_id;

        $result=$user->save();
        if(!$result){
             return $this->error("修改用户失败！");
        }
        return $this->success("","修改用户成功！");
    }

    /**
     * @OA\Post(
     *     path="/api/sys/usergroup/show",
     *     tags={"用户"},
     *     summary="根据id获取用户组信息",
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
    public function show(Request $request){
    	$validatedData = $request->validate([
                    'id' => 'required'
        ]);
        $id = $request->input('id');
    	$data = UserGroupModel::find($id)->toArray();
    	if ($data) {
    		return $this->success($data);
    	}else{
    		return $this->error('未查询到数据！');
    	}

    }
}
