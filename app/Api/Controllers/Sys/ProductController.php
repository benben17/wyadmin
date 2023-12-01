<?php

namespace App\Api\Controllers\Sys;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Models\Product as ProductModel;

class ProductController extends BaseController
{
    /**
     * Create a new AuthController instance.
     * 要求附带email和password（数据来源users表）
     * @return void
     */
    private $uid = 0;
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @OA\Post(
     *     path="/api/sys/product/list",
     *     tags={"公司信息"},
     *     summary="获取当前产品列表",
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

    public function index()
    {
        $result = ProductModel::get()->toArray();
        if (!$result) {
            return $this->error('产品列表查询失败!');
        }
        return $this->success($result);
    }
}
