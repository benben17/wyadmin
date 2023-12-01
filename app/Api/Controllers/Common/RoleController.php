<?php

namespace App\Api\Controllers\Common;

use JWTAuth;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Models\Role as RoleModel;

class RoleController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @OA\Post(
     *     path="/api/common/role/list",
     *     tags={"公共"},
     *     summary="获取角色列表",
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
    public function list()
    {
        $companyIds = array($this->company_id, 0);
        $result = RoleModel::whereIn('company_id', $companyIds)->get();
        if (!$result) {
            return $this->error('角色查询失败!');
        }
        return $this->success($result);
    }
}
