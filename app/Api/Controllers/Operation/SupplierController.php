<?php
namespace App\Api\Controllers\Operation;

use App\Api\Controllers\BaseController;
use JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Api\Services\Operation\SupplierService;
use App\Api\Models\Common\Contact as ContactModel;
use App\Api\Services\Tenant\TenantService;
use App\Api\Services\Common\ContactService;
/**
 *   供应商管理
 */
class SupplierController extends BaseController
{
  public function __construct(){
      $this->uid  = auth()->payload()->get('sub');
      if (!$this->uid) {
        return $this->error('用户信息错误');
      }
      $this->company_id = getCompanyId($this->uid);
      $this->supplier = new SupplierService;
      $this->user = auth('api')->user();
      $this->parentType = 4;
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
    $pagesize = $request->input('pagesize');
    if (!$pagesize || $pagesize < 1) {
        $pagesize = config('per_size');
    }
    if($pagesize == '-1'){
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
    $data = $this->supplier->supplierModel()
    ->where(function ($q) use($request){
      $request->supplier_type && $q->where('supplier_type','like','%'.$request->supplier_type.'%');
      $request->name && $q->where('name','like','%'.$request->name.'%');
    })
    ->orderBy($orderBy,$order)
    ->paginate($pagesize)->toArray();
    $data = $this->handleBackData($data);
    $contactService = new ContactService;

    foreach ($data['result'] as $k => &$v) {
      $contact = $contactService->getContact($v['id'],$this->parentType);
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
  public function store(Request $request){
    $validatedData = $request->validate([
        'name'     => 'required',
        'supplier_type'     => 'required|String',
        'service_content'        => 'required',
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

    $res = $this->supplier->saveSupplier($DA,$this->user);
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
  public function update(Request $request){

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
    ->where('id','!=',$DA['id'])->exists();
    if ($isRepeat) {
      return $this->error('供应商重复');
    }
    $res = $this->supplier->saveSupplier($DA,$this->user,2);
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
      $data['contacts'] = $contactService->getContacts($request->id,$this->parentType);
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
    $DA= $request->toArray();
    $res = $this->supplier->supplierModel()->whereIn('id',$request->Ids)->delete();
    if ($res) {
      return $this->success("删除成功。");
    }
    return $this->error('删除失败！');

  }

}




