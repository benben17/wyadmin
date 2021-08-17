<?php

namespace App\Api\Controllers\Operation;

use App\Api\Controllers\BaseController;
use App\Api\Models\Operation\PubRelations;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Services\Common\ContactService;
use App\Api\Services\Operation\RelationService;
use App\Enums\AppEnum;

/**
 *   供应商管理
 */
class PubRelationController extends BaseController
{
  public function __construct()
  {
    $this->uid  = auth()->payload()->get('sub');
    if (!$this->uid) {
      return $this->error('用户信息错误');
    }
    $this->company_id = getCompanyId($this->uid);
    $this->user = auth('api')->user();
    $this->parentType = AppEnum::Relationship;
  }


  /**
   * @OA\Post(
   *     path="/api/operation/relation/list",
   *     tags={"公共关系"},
   *     summary="公共关系列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={},
   *       @OA\Property(property="pagesize",type="int",description="行数"),
   *       @OA\Property(property="name",type="int",description="公共关系名称"),
   *       @OA\Property(property="proj_ids",type="int",description="项目IDs")
   *     ),
   *       example={"proj_ids":"[]","name":""}
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
    // $validatedData = $request->validate([
    //     'order_type' => 'required|numeric',
    // ]);
    $pagesize = $request->input('pagesize');
    if (!$pagesize || $pagesize < 1) {
      $pagesize = config('per_size');
    }
    if ($pagesize == '-1') {
      $pagesize = config('export_rows');
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
    $relation = new RelationService;
    $data = $relation->model()->where(function ($q) use ($request) {
      $request->name && $q->where('name', 'like', '%' . $request->name . '%');
    })
      ->orderBy($orderBy, $order)
      ->paginate($pagesize)->toArray();
    $data = $this->handleBackData($data);
    $contactService = new ContactService;
    foreach ($data['result'] as $k => &$v) {
      $contact = $contactService->getContact($v['id'], $this->parentType);
      $v['contact_name'] = $contact['name'];
      $v['contact_phone'] = $contact['phone'];
    }
    return $this->success($data);
  }


  /**
   * @OA\Post(
   *     path="/api/operation/relation/add",
   *     tags={"公共关系"},
   *     summary="公共关系新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"proj_id","name","department","job_position"},
   *       @OA\Property(property="name",type="String",description="供应商名称"),
   *       @OA\Property(property="department",type="String",description="部门"),
   *       @OA\Property(property="address",type="String",description="地址"),
   *       @OA\Property(property="c_username",type="String",description="添加人"),
   *       @OA\Property(property="contacts",type="list",description="联系人，list")
   *     ),
   *       example={"name":"","department":"","address":"","c_username":"","contacts":""}
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
      'name'     => 'required',
      'department'     => 'required|String',
      'contacts'      => 'array',
    ]);
    $DA = $request->toArray();
    $relation = new RelationService;
    $map['company_id'] = $this->user->company_id;
    $map['name']       = $DA['name'];
    $isRepeat = $relation->model()->where($map)->exists();
    if ($isRepeat) {
      return $this->error('公共关系重复！');
    }

    $res = $relation->save($DA, $this->user);
    if (!$res) {
      return $this->error($DA['name'] . '公共关系保存失败！');
    }
    return $this->success($DA['name'] . '公共关系保存成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/relation/edit",
   *     tags={"公共关系"},
   *     summary="公共关系编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *           required={"id","department","job_position","contacts"},
   *       @OA\Property(property="name",type="String",description="公共关系"),
   *       @OA\Property(property="department",type="String",description="部门"),
   *       @OA\Property(property="contacts",type="list",description="联系人")
   *     ),
   *       example={"name":"","department":"","job_position":"","contacts":""}
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
      'id'          => 'required',
      'name'        => 'required',
      'department'  => 'required|String',
      'contacts'    => 'array',
    ]);
    $DA = $request->toArray();
    $relation = new RelationService;
    $map['company_id'] = $this->user['company_id'];
    $map['name']       = $DA['name'];
    $isRepeat = $relation->model()->where($map)
      ->where('id', '!=', $DA['id'])->exists();
    if ($isRepeat) {
      return $this->error($DA['name'] . "已存在");
    }
    $res = $relation->save($DA, $this->user, 2);
    if (!$res) {
      return $this->error($DA['name'] . '更新失败！');
    }
    return $this->success($DA['name'] . '更新成功。');
  }



  /**
   * @OA\Post(
   *     path="/api/operation/supplier/show",
   *     tags={"供应商"},
   *     summary="供应商查看",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="id",type="int",description="ID")
   *
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
  public function show(Request $request)
  {
    $validatedData = $request->validate([
      'id' => 'required|numeric|gt:0',
    ]);
    $relation = new RelationService;
    $data = $relation->model()
      ->find($request->id);

    if ($data) {
      $contactService = new ContactService;
      $data['contacts'] = $contactService->getContacts($request->id, $this->parentType);
      $data  = $data->toArray();
    }


    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/supplier/del",
   *     tags={"供应商"},
   *     summary="供应商删除",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"id"},
   *       @OA\Property(property="Ids",type="int",description="Ids")
   *
   *     ),
   *       example={"Ids":"[]"}
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
      'id' => 'required|numeric|gt:0',
    ]);
    $DA = $request->toArray();
    $res = $this->supplier->supplierModel()->whereIn('id', $request->Ids)->delete();
    if ($res) {
      return $this->success("删除成功。");
    }
    return $this->error('删除失败！');
  }
}
