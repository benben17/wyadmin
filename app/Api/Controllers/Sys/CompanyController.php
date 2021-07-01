<?php

namespace App\Api\Controllers\Sys;

use JWTAuth;
//use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Api\Controllers\BaseController;
use App\Models\Company as CompanyModel;
use App\Api\Services\Company\VariableService;

class CompanyController extends BaseController
{
    /**
     * Create a new AuthController instance.
     * 要求附带email和password（数据来源users表）
     * @return void
     */
    private $uid = 0;
    public function __construct()
    {
        $this->uid  = auth()->payload()->get('sub');
        if (!$this->uid) {
            return $this->error('用户信息错误');
        }
        $this->company_id = getCompanyId($this->uid);
    }

    /**
     * @OA\Post(
     *     path="/api/sys/company/present",
     *     tags={"公司信息"},
     *     summary="获取当前用户公司信息",
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

    public function present()
    {
        $user = auth('api')->user();
        $result = CompanyModel::with("product")->withCount("user")->find($user->company_id);
        $result->config = json_decode($result->config, true);
        if (!$result) {
            return $this->error('模块查询失败!');
        }
        return $this->success($result);
    }
    /**
     * @OA\Post(
     *     path="/api/sys/company/variable/show",
     *     tags={"公司信息"},
     *     summary="获取当前用户公司变量信息",
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

    public function showVariable()
    {
        $variable = new VariableService;
        $data = $variable->getCompanyVariable($this->company_id);
        $company = \App\Models\Company::select('name', 'expire_date', 'proj_count')->find($this->company_id);
        $usedProjCount = \App\Api\Models\Project::where('company_id', $this->company_id)->count();
        $data['company_name']   = $company['name'];
        $data['expire_date']    = $company['expire_date'];
        $data['proj_count']     = $company['proj_count'];
        $data['used_proj_count'] = $usedProjCount;
        return $this->success($data);
    }
    /**
     * @OA\Post(
     *     path="/api/sys/company/variable/edit",
     *     tags={"公司信息"},
     *     summary="当前用户公司信息修改",
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

    public function editVariable(Request $request)
    {
        $validatedData = $request->validate([
            'cus_prefix' => 'required',
            'contract_due_remind' => 'required|int|gt:0',
            'msg_revoke_time' => 'required|int|gt:0',
            'contract_prefix' => 'required|String',
        ]);
        $DA = $request->toArray();
        $variable = new VariableService;
        $user = auth('api')->user();
        $res  = $variable->editVariable($DA, $user);
        if ($res) {
            return $this->success('编辑成功。');
        }
        return $this->error('编辑失败！');
    }
}
