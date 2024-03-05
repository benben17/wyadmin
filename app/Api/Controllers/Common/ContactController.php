<?php

namespace App\Api\Controllers\Common;

use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Api\Services\Common\ContactService;

class ContactController extends BaseController
{
  public function __construct()
  {
    parent::__construct();
  }

  /**
   * @OA\Post(
   *     path="/api/common/contact/list",
   *     tags={"联系人"},
   *     summary="联系人列表",
   *     @OA\RequestBody(
   *         @OA\MediaType(
   *             mediaType="application/json",
   *             @OA\Schema(
   *                 schema="UserModel",
   *                 required={"parent_id", "parent_type"},
   *                 @OA\Property(property="parent_type", type="integer", description="类型"),
   *                 @OA\Property(property="parent_id", type="integer", description="父亲ID")
   *             ),
   *             example={
   *                 "parent_type": "1",
   *                 "parent_id": "0"
   *             }
   *         )
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
      'parent_id' => 'required|min:1',
      'parent_type' => 'required|numeric|in:1,2,3,4,5',
    ]);
    $contact = new ContactService;
    $data = $contact->getContacts($request->parent_id, $request->parent_type);
    if ($data) {
      return $this->success($data);
    } else {
      return $this->error("获取失败");
    }
  }

  /**
   * @OA\Post(
   *     path="/api/common/contact/save",
   *     tags={"联系人"},
   *     summary="联系人保存",
   *    @OA\RequestBody(
   *       @OA\MediaType(
   *           mediaType="application/json",
   *       @OA\Schema(
   *          schema="UserModel",
   *          required={"parent_id","parent_type"},
   *       @OA\Property(property="parent_type",type="int",description="类型"),
   *       @OA\Property(property="parent_id",type="int",description="父亲ID"),
   *        @OA\Property(property="contact_name",type="String",description="联系人名称"),
   *       @OA\Property(property="contact_phone",type="int",description="联系人电话"),
   *        @OA\Property(property="is_default",type="int",description="是否默认1，默认0 不是"),
   *     ),
   *       example={
   *              "parent_type":"1","parent_id":"0","contact_name":"0","contact_phone":"","is_default":"","contract_role":""
   *           }
   *       )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description=""
   *     )
   * )
   */

  public function save(Request $request)
  {
    $validatedData = $request->validate([
      'parent_id'     => 'required|min:1|numeric',
      'parent_type'   => 'required|numeric|in:1,2,3,4,5',
      'contact_name'  => 'required|min:1',
      'contact_phone' => 'required|min:1',
    ]);
    $contact = new ContactService;
    $res = $contact->saveContact($request->toArray(), $this->user);
    if ($res) {
      return $this->success("保存成功");
    } else {
      return $this->error("保存失败");
    }
  }



  public function del(Request $request)
  {
    $validatedData = $request->validate([
      'Ids'     => 'required|array',
    ]);
    $contact = new ContactService;
    $res = $contact->delete($request->Ids);
    if ($res) {
      return $this->success("保存成功.");
    } else {
      return $this->error("保存失败!");
    }
  }
}
