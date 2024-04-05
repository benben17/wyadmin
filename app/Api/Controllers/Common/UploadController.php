<?php

namespace App\Api\Controllers\Common;

use JWTAuth;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;


/**
 *  文件上传
 *
 *
 */
class UploadController extends BaseController
{

    private $saveFolder;
    private $error_msg = '';
    public function __construct()
    {
        parent::__construct();
        $this->saveFolder = $this->company_id . '/business/' . date('Ym');
    }
    private $msg = ['file.file' => '类型必须为文件！', 'file.required' => '请选择要上传的文件！'];

    /**
     * @OA\Post(
     *     path="/api/common/upload/imgs",
     *     tags={"公共"},
     *     summary="上传多张图片",
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
     *           mediaType="multipart/form-data",
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
            // 上传文件操作
            $path = Storage::putFile($this->saveFolder, $file);
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
     *           mediaType="multipart/form-data",
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
        $validator = Validator::make($request->all(), [
            'file' => 'required|file'
        ], $this->msg);
        $error = $validator->errors()->first();
        if ($error) {
            return $this->error($error);
        }
        $file = $request->file('file');
        if (!$this->checkImg($file)) {
            return $this->error($this->getError());
        }
        return $this->fileUploadReturn($file);
    }

    private function fileUploadReturn($file)
    {
        $res = Storage::putFile($this->saveFolder, $file);
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
     *           mediaType="multipart/form-data",
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


        $validator = Validator::make($request->all(), [
            'file' => 'required|file'
        ], $this->msg);
        $error = $validator->errors()->first();
        if ($error) {
            return $this->error($error);
        }
        $file = $request->file('file');
        if (!$this->checkFile($file)) {
            return $this->error($this->getError());
        }

        // 上传文件操作
        return $this->fileUploadReturn($file);
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
    {;
        $validator = Validator::make($request->all(), [
            'file' => 'required|file'
        ], $this->msg);
        $error = $validator->errors()->first();
        if ($error) {
            return $this->error($error);
        }
        $file = $request->file('file');

        $fileExt = strtolower($file->getClientOriginalExtension());
        if (!in_array($fileExt, ['doc', 'docx', 'pdf'])) {
            return $this->error("上传格式不允许");
        }
        return $this->fileUploadReturn($file);
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

    /**
     * 检查上传的文件是否合法
     * @Author leezhua
     * @Date 2024-04-05
     * @param mixed $file 
     * @return bool 
     */
    private function checkFile($file)
    {
        /* 检查文件大小 */
        $filesize = filesize($file);
        if (!$this->checkFileSize($filesize)) {
            $this->error_msg = '上传文件大小不符！';
            return false;
        }
        /* 检查文件后缀 */
        $fileExt = $file->getClientOriginalExtension();
        $allowExts = config('file_upload_allow');
        return in_array(strtolower($fileExt), $allowExts ?? []);
    }
    private function checkFileSize($size)
    {
        $maxSize = config('max_file_size') * 1024 * 1024;
        return !($size > $maxSize) || (0 == $maxSize);
    }
}
