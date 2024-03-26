<?php

namespace App\Api\Controllers\Common;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Models\Role as RoleModel;
use App\Models\Area as AreaModel;

class PubController extends BaseController
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
    *     path="/api/common/all_dict",
    *     tags={"公共"},
    *     summary="获取全局字典,根据参数来调取指定字典",
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
    *    @OA\RequestBody(
    *       @OA\MediaType(
    *           mediaType="application/json",
    *       @OA\Schema(
    *          schema="UserModel",
    *          required={"names"},
    *       @OA\Property(
    *          property="names",
    *          type="string",
    *          description="字典名称对象"
    *       )
    *     ),
    *       example={
    *              "names":"[]"
    *           }
    *       )
    *     ),
    *     @OA\Response(
    *         response=200,
    *         description="The result"
    *     )
    * )
    */

   public function allDict(Request $request)
   {
      $messages = [
         'names.required' => '字典名称不能为空！',
         'names.array' => '字典名称必须为数组！',
      ];
      $validatedData = $request->validate([
         'names' => 'required|array'
      ], $messages);
      $names = $request->input('names');
      foreach ($names as $key => $value) {
         $data[$value] = config($value);
      }
      if (!$data) {
         return $this->error('字典查询失败!');
      }
      return $this->success($data);
   }
   /**
    * @OA\Post(
    *     path="/api/common/dict",
    *     tags={"公共"},
    *     summary="获取全局单个字典",
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
    *    @OA\RequestBody(
    *       @OA\MediaType(
    *           mediaType="application/json",
    *       @OA\Schema(
    *          schema="UserModel",
    *          required={"name"},
    *       @OA\Property(
    *          property="name",
    *          type="string",
    *          description=""
    *       )
    *     ),
    *       example={
    *              "name": "sex"
    *           }
    *       )
    *     ),
    *     @OA\Response(
    *         response=200,
    *         description="The result"
    *     )
    * )
    */

   public function dict(Request $request)
   {
      $messages = [
         'name.required' => '字典名称不能为空！',
      ];
      $validatedData = $request->validate([
         'name' => 'required'
      ], $messages);
      $name = $request->input("name");
      $result = config($name);
      if (!$result) {
         return $this->error('字典查询失败!');
      }
      return $this->success($result);
   }
   /**
    * @OA\Post(
    *     path="/api/common/province",
    *     tags={"公共"},
    *     summary="获取省份列表",
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
    *         description="The result"
    *     )
    * )
    */

   public function province()
   {
      $result = AreaModel::where('parent_id', 1)->get();
      if (!$result) {
         return $this->error('省份查询失败!');
      }
      return $this->success($result);
   }

   /**
    * @OA\Post(
    *     path="/api/common/city",
    *     tags={"公共"},
    *     summary="获取城市列表",
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
    *    @OA\RequestBody(
    *       @OA\MediaType(
    *           mediaType="application/json",
    *       @OA\Schema(
    *          schema="UserModel",
    *          required={"id"},
    *       @OA\Property(
    *          property="id",
    *          type="integer",
    *          description="上级id"
    *       )
    *     ),
    *       example={
    *              "id": 2
    *           }
    *       )
    *     ),
    *     @OA\Response(
    *         response=200,
    *         description="The result"
    *     )
    * )
    */

   public function city(Request $request)
   {
      $messages = [
         'id.required' => '省份id不能为空！',
      ];
      $validatedData = $request->validate([
         'id' => 'required'
      ], $messages);
      $id = $request->input("id");
      $result = AreaModel::where('parent_id', $id)->get();
      if (!$result) {
         return $this->error('城市查询失败!');
      }
      return $this->success($result);
   }

   /**
    * @OA\Post(
    *     path="/api/common/district",
    *     tags={"公共"},
    *     summary="获取区域列表",
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
    *    @OA\RequestBody(
    *       @OA\MediaType(
    *           mediaType="application/json",
    *       @OA\Schema(
    *          schema="UserModel",
    *          required={"id"},
    *       @OA\Property(
    *          property="id",
    *          type="integer",
    *          description="上级id"
    *       )
    *     ),
    *       example={
    *              "id": 36
    *           }
    *       )
    *     ),
    *     @OA\Response(
    *         response=200,
    *         description="The result"
    *     )
    * )
    */

   public function district(Request $request)
   {
      $messages = [
         'id.required' => '城市id不能为空！',
      ];
      $validatedData = $request->validate([
         'id' => 'required'
      ], $messages);
      $id = $request->input("id");
      $result = AreaModel::where('parent_id', $id)->get();
      if (!$result) {
         return $this->error('区域查询失败!');
      }
      return $this->success($result);
   }
}
