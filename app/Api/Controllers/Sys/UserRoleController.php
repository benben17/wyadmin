<?php
namespace App\Api\Controllers\Sys;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Api\Controllers\BaseController;

// use App\Api\Models\Sys\UserGroup as UserGroupModel;
use App\Api\Models\Sys\UserRole as UserRoleModel;
use App\Api\Services\Sys\UserServices;


/**
 * 角色管理
 */
class UserRoleController extends BaseController
{

  private $uid = 0;
  public function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if(!$this->uid){
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);

  }
    /**
     * @OA\Post(
     *     path="/api/sys/user/role/list",
     *     tags={"用户"},
     *     summary="获取角色列表",
     *    @OA\RequestBody(
     *       @OA\MediaType(
     *           mediaType="application/json",
     *       @OA\Schema(
     *          schema="UserModel",
     *          required={"pagesize","orderBy","order"},
     *       @OA\Property(
     *          property="name",
     *          type="String",
     *          description="角色名可模糊查询"
     *       )
     *     ),
     *       example={"name":"","orderBy":"根据那个字段排序","order":"排序方式desc 倒叙 asc 正序"}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description=""
     *     )
     * )
    */
  public function list(Request $request){
    $pagesize = $request->input('pagesize');
    if(!$pagesize || $pagesize <1){
      $pagesize = config('app.pagesize');
    }
    if($pagesize==-1){
      $pagesize = config('export_rows');
    }
    if($request->input('orderBy')){
      $orderBy = $request->input('orderBy');
    }else{
      $orderBy = 'id';
    }
    // 排序方式desc 倒叙 asc 正序
    if($request->input('order')){
      $order = $request->input('order');
    }else{
      $order = 'desc';
    }
    $companyId = array(0,$this->company_id);
    $data = UserRoleModel::whereIn('company_id',$companyId)
    ->where(function ($q) use ($request){
      $request->name && $q->where('name','like','%'.$request->name.'%');
    })->orderBy($orderBy,$order)
    ->paginate($pagesize);
    $data = $this->handleBackData($data->toArray());
    return $this->success($data);
  }
  /**
   * @OA\Post(
   *     path="/api/sys/user/role/add",
   *     tags={"用户"},
   *     summary="角色新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"name","menu_list"},
   *       @OA\Property(
   *          property="name",
   *          type="String",
   *          description="角色名称"
   *       ),
   *       @OA\Property(
   *          property="menu_list",
   *          type="String",
   *          description="menu_list"
   *       ),
   *       @OA\Property(
   *          property="remark",
   *          type="String",
   *          description="remark"
   *       )
   *     ),
   *       example={"name":"","menu_list":"","remark":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
  */
  public function store(Request $request){
    $validator = \Validator::make($request->all(), [
        'name' => 'required|String|max:64',
        'menu_list' => 'required|String',
      ]);
    $user = auth('api')->user();
    $DA = $request->toArray();
    $roleService = new UserServices;
    $checkRepeat = $roleService->isRepeat($DA,$user);
    if ($checkRepeat) {
      return $this->error('角色名称重复！');
    }
    $role = $roleService->roleModel();
    $role->name = $DA['name'];
    $role->company_id =  $user->company_id;
    $role->menu_list =  $DA['menu_list'];
    if ($DA['remark']) {
      $role->remark = $DA['remark'];
    }
    $res = $role->save();
    if ($res) {
      return $this->success('角色新增成功。');
    }else{
      return $this->error('角色新增失败!');
    }
  }

  /**
   * @OA\Post(
   *     path="/api/sys/user/role/edit",
   *     tags={"用户"},
   *     summary="角色编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","name","menu_list"},
   *       @OA\Property(
   *          property="name",
   *          type="String",
   *          description="角色名称"
   *       ),
   *       @OA\Property(
   *          property="menu_list",
   *          type="String",
   *          description="menu_list"
   *       ),
   *       @OA\Property(
   *          property="remark",
   *          type="String",
   *          description="remark"
   *       )
   *     ),
   *       example={"name":"","menu_list":"","remark":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
  */
  public function update(Request $request){
    $validator = \Validator::make($request->all(), [
        'id' => 'required|int|gt:0',
        'name' => 'required|String|max:64',
        'menu_list' => 'required|String',
      ]);
    $user = auth('api')->user();
    $DA = $request->toArray();
    $roleService = new UserServices;
    $checkRepeat = $roleService->isRepeat($DA,$user);
    if ($checkRepeat) {
      return $this->error('角色名称重复！');
    }
    $role = $roleService->roleModel()->find($DA['id']);
    $role->name = $DA['name'];
    $role->menu_list =  $DA['menu_list'];
    if ($DA['remark']) {
      $role->remark = $DA['remark'];
    }
    $res = $role->save();
    if ($res) {
      return $this->success('角色编辑成功。');
    }else{
      return $this->error('角色编辑失败!');
    }
  }

    /**
   * @OA\Post(
   *     path="/api/sys/user/role/show",
   *     tags={"用户"},
   *     summary="角色查看",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(
   *          property="id",
   *          type="String",
   *          description="角色id"
   *       )
   *     ),
   *       example={"id":""}
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
  */
  public function show(Request $request){
    $validator = \Validator::make($request->all(), [
        'id' => 'required|int|gt:0',
      ]);
    $user = auth('api')->user();
    $DA = $request->toArray();
    $roleService = new UserServices;

    $role = $roleService->getRoleById($DA['id'])->toArray();

    if ($role) {
      if ($role['menu_list']) {
        $role['menu_list'] = str2Array($role['menu_list']);
      }
      if ($role['company_id'] == 0 ) {
        $role['is_edit'] = 0;
      }else{
        $role['is_edit'] = 1;
      }
      return $this->success($role);
    }
      return $this->error('角色编辑失败!');
  }

}