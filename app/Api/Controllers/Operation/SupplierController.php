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
use App\Api\Services\Operation\SupplierService;

/**
 *   供应商管理
 */
class SupplierController extends BaseController
{
  private $parentType;
  private $supplier;
  public function __construct()
  {
    parent::__construct();
    $this->supplier = new SupplierService;
    $this->parentType = AppEnum::Supplier;
  }


  /**
   * @OA\Post(
   *     path="/api/operation/supplier/list",
   *     tags={"供应商"},
   *     summary="供应商列表",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={},
   *       @OA\Property(property="supplier_type",type="String",
   *         description="专业"),
   *       @OA\Property(property="pagesize",type="int",description="行数"),
   *       @OA\Property(property="name",type="int",description="供应商名称"),
   *       @OA\Property(property="proj_ids",type="int",description="项目IDs")
   *     ),
   *       example={"supplier_type":1,"proj_ids":"[]","name":""}
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

    $query = $this->supplier->supplierModel()
      ->where(function ($q) use ($request) {
        $request->proj_ids && $q->whereIn('proj_id', $request->proj_ids);
        $request->supplier_type && $q->where('supplier_type', 'like', '%' . $request->supplier_type . '%');
        $request->name && $q->where('name', 'like', '%' . $request->name . '%');
      });

    $data = $this->pageData($query, $request);
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
   *     path="/api/operation/supplier/add",
   *     tags={"供应商"},
   *     summary="供应商新增",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"proj_id","name","supplier_type","service_content"},
   *       @OA\Property(property="name",type="String",description="供应商名称"),
   *       @OA\Property(property="supplier_type",type="String",description="专业"),
   *       @OA\Property(property="service_content",type="String",description="服务内容"),
   *       @OA\Property(property="maintain_depart",type="String",description="维护部门"),
   *       @OA\Property(property="contacts",type="list",description="联系人")
   *     ),
   *       example={"name":"","supplier_type":"","service_content":"",
   *       "maintain_depart":"","contacts":""}
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
      'supplier_type'     => 'required|String',
      // 'service_content'        => 'required',
      'maintain_depart'        => 'required',
      'contacts'      => 'array',
    ]);
    $DA = $request->toArray();

    $map['company_id'] = $this->user->company_id;
    $map['name']       = $DA['name'];
    $isRepeat = $this->supplier->supplierModel()->where($map)->exists();
    if ($isRepeat) {
      return $this->error('供应商重复');
    }

    $res = $this->supplier->saveSupplier($DA, $this->user);
    if (!$res) {
      return $this->error('供应商保存失败！');
    }
    return $this->success('供应商保存成功。');
  }

  /**
   * @OA\Post(
   *     path="/api/operation/supplier/edit",
   *     tags={"供应商"},
   *     summary="供应商编辑",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *           required={"id","supplier_type","service_content","maintain_depart","contacts"},
   *       @OA\Property(property="name",type="String",description="供应商名称"),
   *       @OA\Property(property="supplier_type",type="String",description="专业"),
   *       @OA\Property(property="service_content",type="String",description="服务内容"),
   *       @OA\Property(property="maintain_depart",type="String",description="维护部门"),
   *       @OA\Property(property="contacts",type="list",description="联系人")
   *     ),
   *       example={"name":"","supplier_type":"","service_content":"","maintain_depart":"","contacts":""}
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
      'id'              => 'required',
      'name'     => 'required',
      'supplier_type'     => 'required|String',
      'service_content'        => 'required',
      'maintain_depart'        => 'required',
      'supplier_contact'      => 'array',
    ]);
    $DA = $request->toArray();

    $map['company_id'] = $this->user['company_id'];
    $map['name']       = $DA['name'];
    $isRepeat = $this->supplier->supplierModel()->where($map)
      ->where('id', '!=', $DA['id'])->exists();
    if ($isRepeat) {
      return $this->error('供应商重复');
    }
    $res = $this->supplier->saveSupplier($DA, $this->user, 2);
    if (!$res) {
      return $this->error('更新失败！');
    }
    return $this->success('更新成功。');
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
    $data = $this->supplier->supplierModel()
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
   *          required={"Ids"},
   *       @OA\Property(property="Ids",type="list",description="id集合"),
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
        $this->supplier->supplierModel()->whereIn('id', $DA['Ids'])->delete();
        $contactService = new ContactService;
        $contactService->delContact($DA['Ids'], $this->parentType);

        $commonMaintain = new BseMaintainService;
        $commonMaintain->delMaintain($DA['Ids'], $this->parentType);
      });
      return $this->success('删除成功！');
    } catch (\Exception $e) {
      Log::error('删除失败：' . $e->getMessage());
      return $this->error('删除失败！' . $e->getMessage());
    }
  }
}
