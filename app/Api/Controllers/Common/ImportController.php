<?php

namespace App\Api\Controllers\Common;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Api\Controllers\BaseController;

class ImportController extends BaseController
{
  /**
   * @OA\Post(
   *     path="/api/common/import",
   *     tags={"公共"},
   *     summary="导入Excel数据",
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
   *     @OA\RequestBody(
   *         @OA\MediaType(
   *             mediaType="multipart/form-data",
   *             @OA\Schema(
   *                 required={"model", "file"},
   *                 @OA\Property(
   *                     property="model",
   *                     type="string",
   *                     description="模型名称"
   *                 ),
   *                 @OA\Property(
   *                     property="file",
   *                     type="file",
   *                     description="Excel文件"
   *                 )
   *             )
   *         )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description="导入成功"
   *     ),
   * )
   */
  public function import(Request $request)
  {
    // 1. 验证请求参数
    $request->validate([
      'model' => 'required|string|in:' . implode(',', array_keys(config('excel.mappings'))),
      'file' => 'required|file|mimes:xlsx,xls|max:4096',
      'type' => 'int|in:1,2,3,4,5',
    ], [
      'model.required' => '模型名称不能为空',
      'model.string' => '模型名称必须为字符串',
      'model.in' => '模型名称不合法',
      'file.required' => '文件不能为空',
      'file.file' => '文件必须为文件',
      'file.mimes' => '文件格式不合法',
      'file.max' => '文件大小不能超过2M',
    ]);

    $model = $request->input('model');
    $importClass = config("excel_imports.mappings.{$model}");

    // 2. 检查模型是否已配置导入类
    if (!$importClass) {
      return $this->error('导入类未配置');
    }

    // 3. 处理导入逻辑
    try {
      Excel::import(new $importClass, $request->file('file'), $this->user);
      return $this->success('导入成功');
    } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
      $failures = $e->failures();
      return $this->error('导入失败', $failures);
    } catch (\Exception $e) {
      return $this->error('导入失败', 500);
    }
  }
}
