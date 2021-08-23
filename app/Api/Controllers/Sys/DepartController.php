<?php

namespace App\Api\Controllers\Sys;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Controllers\BaseController;
use App\Api\Services\Sys\DepartService;
use App\Models\Company as CompanyModel;
use App\Models\User as UserModel;
use App\Api\Services\Sys\UserServices;


class DepartController extends BaseController
{
  public function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->user = auth('api')->user();
  }

  /**
   * @OA\Post(
   *     path="/api/sys/depart/list",
   *     tags={"部门"},
   *     summary="获取部门列表",
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

  public function list(Request $request)
  {

    // $map = array();
    $departService = new DepartService;
    $result = $departService->getDepartList(0);
    return $this->success($result);
  }

  /**
   * @OA\Post(
   *     path="/api/sys/depart/add",
   *     tags={"部门"},
   *     summary="部门新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"name","parent_id"},
   *       @OA\Property(
   *          property="name",
   *          type="String",
   *          description="部门名称"
   *       ),
   *       @OA\Property(
   *          property="parent_id",
   *          type="String",
   *          description="父ID"
   *       )
   *     ),
   *       example={
   *              "name":"channel_type","parent_id":"0"
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
      'name' => 'required|String|max:64',
      'parent_id' => 'required|int',
    ]);
    $data = $request->toArray();
    $departService = new DepartService;
    $isRepeat = $departService->isRepeat($data);
    if ($isRepeat) {
      return $this->error($data['name'] . '-部门重复！');
    }
    if ($request->parent_id == 0) {
      $res = $departService->model()->where('parent_id', $request->parent_id)->count();
      if ($res > 0) {
        return $this->error('公司只允许一个总部：' . $data['name']);
      }
    }
    $res = $departService->save($data, $this->user);
    if ($res) {
      return $this->success('部门添加成功');
    } else {
      return $this->error('部门添加失败');
    }
  }

  /**
   * @OA\Post(
   *     path="/api/sys/depart/edit",
   *     tags={"部门"},
   *     summary="部门编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","name","parent_id"},
   *       @OA\Property(
   *          property="id",
   *          type="String",
   *          description="部门id"
   *       ),
   *       @OA\Property(
   *          property="name",
   *          type="String",
   *          description="部门名称"
   *       ),
   *       @OA\Property(
   *          property="parent_id",
   *          type="String",
   *          description="父ID"
   *       )
   *     ),
   *       example={
   *              "name":"部门名称","parent_id":"0"
   *           }
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function edit(Request $request)
  {
    $validatedData = $request->validate([
      'id'   => 'required|int',
      'name' => 'required|String|max:64',
      'parent_id' => 'required|int',
    ]);
    $data = $request->toArray();
    $departService = new DepartService;
    $isRepeat = $departService->isRepeat($data);
    if ($isRepeat) {
      return $this->error($data['name'] . '-部门重复！');
    }
    if ($request->parent_id == 0) {
      $res = $departService->model()->where('parent_id', $request->parent_id)
        ->where('id', '!=', $request->id)
        ->count();
      if ($res > 0) {
        return $this->error('公司只允许一个总部');
      }
    }
    $res = $departService->save($data, $this->user);
    if ($res) {
      return $this->success('部门编辑成功.');
    } else {
      return $this->error('部门编辑失败!');
    }
  }

  /**
   * @OA\Post(
   *     path="/api/sys/depart/move",
   *     tags={"部门"},
   *     summary="部门移动",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","new_parent_id"},
   *       @OA\Property(
   *          property="id",
   *          type="String",
   *          description="部门id"
   *       ),
   *       @OA\Property(
   *          property="new_parent_id",
   *          type="String",
   *          description="父ID"
   *       )
   *     ),
   *       example={
   *              "name":"id","new_parent_id":"0"
   *           }
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */
  public function move(Request $request)
  {
    $validatedData = $request->validate([
      'id'   => 'required|int',
      'new_depart_id'   => 'required|int|gt:0',
    ]);
    $data = $request->toArray();
    $departService = new DepartService;

    $res = $departService->model()
      ->where('id', $data['id'])
      ->update(['parent_id' => $request->new_depart_id]);
    if ($res) {
      return $this->success('部门迁移成功.');
    } else {
      return $this->error('部门迁移失败!');
    }
  }

  /**
   * @OA\Post(
   *     path="/api/sys/depart/enable",
   *     tags={"部门"},
   *     summary="部门禁用/启用",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id","new_parent_id"},
   *       @OA\Property(
   *          property="id",
   *          type="String",
   *          description="部门id"
   *       ),
   *       @OA\Property(
   *          property="new_parent_id",
   *          type="String",
   *          description="父ID"
   *       )
   *     ),
   *       example={
   *              "name":"id","new_parent_id":"0"
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
      'id'   => 'required|int',
      'is_vaild' => 'required|in:0,1'
    ]);
    $data = $request->toArray();
    $departService = new DepartService;
    $departIds = getDepartIds($request->id);
    array_push($departIds, $request->id);
    $res = $departService->model()->whereIn('id', $departIds)->update(['is_vaild' => $request->is_vaild]);
    if ($res) {
      return $this->success('部门以及子部门禁用成功.');
    } else {
      return $this->error('部门以及子部门禁用失败!');
    }
  }

  /**
   * @OA\Post(
   *     path="/api/sys/depart/show",
   *     tags={"部门"},
   *     summary="查看",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(
   *          property="id",
   *          type="String",
   *          description="部门id"
   *       )
   *     ),
   *       example={
   *              "id":"id"
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
      'id'   => 'required|int',

    ]);

    $departService = new DepartService;

    $data = $departService->model()->find($request->id);
    return $this->success($data);
  }
}
