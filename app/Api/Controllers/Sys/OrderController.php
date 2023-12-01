<?php

namespace App\Api\Controllers\Sys;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Models\Order as OrderModel;

class OrderController extends BaseController
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
     *     path="/api/sys/order/list",
     *     tags={"公司信息"},
     *     summary="获取当前公司订购",
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
        $user = auth('api')->user();
        $result = OrderModel::where('company_id', $user->company_id)
            ->with("product")->get()->toArray();
        if (!$result) {
            return $this->error('模块查询失败!');
        }
        return $this->success($result);
    }
}
