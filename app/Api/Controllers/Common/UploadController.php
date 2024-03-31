<?php

namespace App\Api\Controllers\Common;

use JWTAuth;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use Illuminate\Support\Facades\Storage;

/**
 *  文件上传
 *
 *
 */
class UploadController extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }
    private $error_msg = '';




    public function uploadImgs(Request $request)
    {
        // $validator = \Validator::make($request->all(), [
        //     'file' => 'required|array'
        // ]);
        // $error = $validator->errors()->first();
        // if ($error) {
        //     return $this->error($error);
        // }
        $files = $request->allFiles();
        if (!is_array($files)) {
            return $this->error("文件错误!");
        }
        $paths = array();
        foreach ($files as $file) {
            if (!$this->checkImg($file)) {
                return $this->error($this->getError());
            }
            $company_id = $this->company_id;
            $saveFolder = $company_id . '/business/' . date('Ymd');
            // 上传文件操作
            $path = Storage::putFile($saveFolder, $file);
            if ($path) {
                unset($file);
                array_push($paths, $path);
            }
        }
        return $this->success($paths);
    }
    /**
     * @OA\Post(
     *     path="/api/common/upload/img",
     *     tags={"公共"},
     *     summary="上传图片",
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
     *          required={"file"},
     *       @OA\Property(
     *          property="file",
     *          type="file",
     *          description="文件"
     *       )
     *     ),
     *       example={
     *              "file":""
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="The result"
     *     )
     * )
     */
    public function uploadImg(Request $request)
    {
        $messages = ['file.file' => '类型必须为图片！', 'file.required' => '请选择要上传的图片！'];
        $validator = \Validator::make($request->all(), [
            'file' => 'required|file'
        ], $messages);
        $error = $validator->errors()->first();
        if ($error) {
            return $this->error($error);
        }
        $file = $request->file('file');
        if (!$this->checkImg($file)) {
            return $this->error($this->getError());
        }
        $user = auth('api')->user();
        $company_id = $user->company_id;
        $saveFolder = $company_id . '/business/' . date('Ymd');
        // 上传文件操作
        $res = Storage::putFile($saveFolder, $file);
        if ($res) {
            $data['path'] = $res;
            unset($file);
            return $this->success($data);
        }
        return $this->error('文件上传失败！');
    }

    /**
     * @OA\Post(
     *     path="/api/common/upload/file",
     *     tags={"公共"},
     *     summary="上传文件",
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
     *          required={"file"},
     *       @OA\Property(
     *          property="file",
     *          type="file",
     *          description="文件"
     *       )
     *     ),
     *       example={
     *              "file":""
     *           }
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="The result"
     *     )
     * )
     */


    public function uploadFile(Request $request)
    {

        $messages = [
            'file.file' => '类型必须为文件！',
            'file.required' => '请选择要上传的文件！'
        ];
        $validator = Validator::make($request->all(), [
            'file' => 'required|file'
        ], $messages);
        $error = $validator->errors()->first();
        if ($error) {
            return $this->error($error);
        }
        $file = $request->file('file');
        if (!$this->checkFile($file)) {
            return $this->error($this->getError());
        }
        $company_id = $this->company_id;
        $saveFolder = $company_id . '/business/' . date('Ymd');
        // 上传文件操作
        $res = Storage::putFile($saveFolder, $file);
        if ($res) {
            $data['path'] = $res;
            unset($file);
            return $this->success($data);
        }
        return $this->error('文件上传失败！');
    }


    /**
     * @OA\Post(
     *     path="/api/common/upload/contract",
     *     tags={"公共"},
     *     summary="上传合同以及合同模版",
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
     *           mediaType="form",
     *       @OA\Schema(schema="UserModel",required={"file"},
     *       @OA\Property(property="file",type="file",description="支持pdf,doc,docx")
     *     ),
     *       example={"file":""}
     *       )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="The result"
     *     )
     * )
     */

    public function uploadContract(Request $request)
    {

        $messages = ['file.file' => '类型必须为文件！', 'file.required' => '请选择要上传的文件！'];
        $validator = \Validator::make($request->all(), [
            'file' => 'required|file'
        ], $messages);
        $error = $validator->errors()->first();
        if ($error) {
            return $this->error($error);
        }
        $file = $request->file('file');
        // $tmpPath = $file->getRealPath();
        $fileExt = strtolower($file->getClientOriginalExtension());
        if (!in_array($fileExt, ['doc', 'docx', 'pdf'])) {
            return $this->error("上传格式不允许");
        }
        // $file_name=$file->getClientOriginalName();
        $user = auth('api')->user();
        $company_id = $user->company_id;
        // $fileName = date('Ymd').'/'.$user->company_id."-".$file_name;
        $saveFolder = $company_id . '/contract';
        $res = Storage::putFile($saveFolder, $file);
        if ($res) {
            $data['path'] = $res;
            unset($file);
            return $this->success($data);
        }
        return $this->error('文件上传失败！');
    }



    public function getError()
    {
        return $this->error_msg;
    }


    private function checkImg($file)
    {
        /* 检查文件大小 */
        $filesize = filesize($file);
        if (!$this->checkImgSize($filesize)) {
            $this->error_msg = '上传文件大小不符！';
            return false;
        }
        /* 检查文件后缀 */
        if (!$this->checkImgExt($file->getClientOriginalExtension())) {
            $this->error_msg = '上传文件格式不允许';
            return false;
        }
        return true;
    }
    private function checkImgSize($size)
    {
        $maxSize = config('max_file_size') * 1024 * 1024;
        return !($size > $maxSize) || (0 == $maxSize);
    }
    /**
     * 检查上传的文件后缀是否合法
     * @param string $ext 后缀
     */
    private function checkImgExt($ext)
    {
        $fileExts = config('img_upload_allow');
        return empty($fileExts) ? true : in_array(strtolower($ext), $fileExts);
    }

    private function checkFile($file)
    {
        /* 检查文件大小 */
        $filesize = filesize($file);
        if (!$this->checkFileSize($filesize)) {
            $this->error_msg = '上传文件大小不符！';
            return false;
        }
        /* 检查文件后缀 */
        if (!$this->checkFileExt($file->getClientOriginalExtension())) {
            $this->error_msg = '上传文件格式不允许';
            return false;
        }
        return true;
    }
    private function checkFileSize($size)
    {
        $maxSize = config('max_file_size') * 1024 * 1024;
        return !($size > $maxSize) || (0 == $maxSize);
    }
    /**
     * 检查上传的文件后缀是否合法
     * @param string $ext 后缀
     */
    private function checkFileExt($ext)
    {
        $fileExts = config('file_upload_allow');
        return empty($fileExts) ? true : in_array(strtolower($ext), $fileExts);
    }


    private function saveFile($fileName, $tmpFile, $disk = 'public')
    {
        if (Storage::disk($disk)->put($fileName, file_get_contents($tmpFile))) {
            return ['code' => 1, 'msg' => $fileName];
        }
        return false;
    }
}
