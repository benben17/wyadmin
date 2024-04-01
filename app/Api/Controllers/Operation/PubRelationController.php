<?php

namespace App\Api\Controllers\Operation;

use JWTAuth;
use App\Enums\AppEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Controllers\BaseController;

use App\Api\Services\Common\ContactService;
use App\Api\Services\Common\BseMaintainService;
use App\Api\Services\Operation\RelationService;

/**
 *   供应商管理
 */
class PubRelationController extends BaseController
{
  private $parentType;
  private $relationService;
  private $contactService;
  public function __construct()
  {
    parent::__construct();
    $this->parentType = AppEnum::Relationship;
    $this->relationService = new RelationService;
    $this->contactService = new ContactService;
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
   *       example={"proj_ids":"[]","name":"","pagesize":10,"orderBy":"id","order":"desc"}
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


    $query = $this->relationService->model()->where(function ($q) use ($request) {
      $request->name && $q->where('name', 'like', '%' . $request->name . '%');
    });

    $data = $this->pageData($query, $request);

    foreach ($data['result'] as $k => &$v) {
      $contact = $this->contactService->getContact($v['id'], $this->parentType);
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
      'name'     => 'required|string|min:2|max:255',
      'department'     => 'required|String',
      'contacts'      => 'array',
    ]);
    $DA = $request->toArray();
    $map['company_id'] = $this->company_id;
    $map['name']       = $DA['name'];
    $isRepeat = $this->relationService->model()->where($map)->exists();
    if ($isRepeat) {
      return $this->error('公共关系重复！');
    }

    $res = $this->relationService->save($DA, $this->user);
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
      'name'        => 'required|string|min:2|max:255',
      'department'  => 'required|string',
      'contacts'    => 'array',
    ]);
    $DA = $request->toArray();
    $map['company_id'] = $this->user['company_id'];
    $map['name']       = $DA['name'];
    $isRepeat = $this->relationService->model()->where($map)
      ->where('id', '!=', $DA['id'])->exists();
    if ($isRepeat) {
      return $this->error($DA['name'] . "已存在");
    }
    $res = $this->relationService->save($DA, $this->user, 2);
    if (!$res) {
      return $this->error($DA['name'] . '更新失败！');
    }
    return $this->success($DA['name'] . '更新成功。');
  }



  /**
   * @OA\Post(
   *     path="/api/operation/relation/show",
   *     tags={"公共关系"},
   *     summary="公共关系查看",
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
    $data = $this->relationService->model()
      ->find($request->id);

    if ($data) {
      $data['contacts'] = $this->contactService->getContacts($request->id, $this->parentType);
      $data  = $data->toArray();
    }


    return $this->success($data);
  }

  /**
   * @OA\Post(
   *     path="/api/operation/relation/del",
   *     tags={"公共关系"},
   *     summary="公共关系删除",
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
    try {
      DB::transaction(function () use ($DA) {
        $this->relationService->model()->whereIn('id', $DA['Ids'])->delete();
        $this->contactService->delContact($DA['Ids'], $this->parentType);
        $maintainService = new BseMaintainService;
        $maintainService->delMaintain($DA['Ids'], $this->parentType);
      }, 2);
      return $this->success('公共关系删除成功。');
    } catch (\Exception $e) {
      Log::error($e->getMessage());
      return $this->error("公共关系删除失败！");
    }
  }
}
