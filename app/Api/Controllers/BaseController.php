<?php

namespace App\Api\Controllers;

use Illuminate\Http\Request;
use App\Api\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Info(
 *     version="v1.0.0",
 *     title="物业管理系统 OpenApi"
 * ),
 * @OA\Server(
 *     url="http://localhost:8080",
 *     description="API Server"
 * ),
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="apiKey",
 *     in="header",
 *     name="Authorization",
 *     description="Bearer Token"
 * )
 */

class BaseController extends Controller
{

    protected $uid;
    protected $company_id;
    protected $user;
    protected $sortType = ['asc', 'desc'];
    public function __construct()
    {

        try {
            $payload = auth('api')->payload();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            Log::error('Token error: ' . $e->getMessage());
            return $this->error('token 错误！');
        }
        // Log::alert($payload->get('guard'));
        if ($payload->get('guard') !== 'api') {
            return $this->error('token 错误！');
        }

        $this->uid = $payload->get('sub');
        if (!$this->uid) {
            return $this->error('用户信息错误');
        }
        $this->user = auth('api')->user();
        $this->company_id = getCompanyId($this->uid);
    }


    public function authUser()
    {
        if (!$this->uid) {
            return $this->error('用户信息错误');
        }
    }

    //成功返回
    public function success($data, $msg = "ok")
    {
        if (is_object($data)) {
            !isEmptyObj($data) && $data = $data->toArray();
        }
        $this->parseNull($data);
        $result = [
            "code"      => 200,
            "message"   => $msg,
            "data"      => $data,
        ];
        return response()->json($result, 200);
    }

    //失败返回
    public function error($msg = "fail", $code = 4000)
    {
        $result = [
            "code" => $code,
            "message" => $msg,
        ];
        return response()->json($result, 200);
    }

    //如果返回的数据中有 null 则那其值修改为空 （安卓和IOS 对null型的数据不友好，会报错）

    public function parseNull(&$data)
    {
        if (is_array($data)) {
            foreach ($data as &$v) {
                $this->parseNull($v);
            }
        } else {
            if (is_null($data)) {
                $data = "";
            } elseif (is_float($data)) {
                $data = numFormat($data);
            }
        }
    }


    public function handleBackData($data)
    {
        if (!empty($data) && is_object($data)) {
            $data = $data->toArray();
        }
        $backData['result'] = $data['data'];
        $backData['pageInfo'] =  [
            'currentPage'   => $data['current_page'],
            'totalPage'     => $data['last_page'],
            'totalNum'      => $data['total']
        ];
        return $backData;
    }

    /**
     * 格式化数组,null 替换成 ''
     * @Author leezhua
     * @Date 2024-04-01
     * @param array $data 
     * @return void
     */
    public function formatArray($data)
    {
        // return array_map(function ($value) {
        //     return is_null($value) ? '' : $value;
        // }, $data);
        array_walk($data, function (&$value) { //  &$value 表示引用传递，直接修改原数组元素
            $value = is_null($value) ? '' : $value;
        });
    }

    /**
     * 分页数据
     * @Author leezhua
     * @Date 2024-04-01
     * @param mixed $query 
     * @param mixed $request 
     * @return array  
     */
    public function pageData($query, $request)
    {
        // 分页
        $pagesize = $this->setPagesize($request);
        // 排序
        $order = $request->orderBy ?? 'created_at';
        // 排序方式
        $sort = $request->order ?? 'desc';
        if (!in_array($sort, $this->sortType)) {
            $sort = 'desc';
        }
        $data = $query->orderBy($order, $sort)->paginate($pagesize)->toArray();
        // 返回数据并格式化
        return $this->handleBackData($data);
    }
    /**
     * 设置分页 默认每页20条
     *
     * @Author leezhua
     * @DateTime 2024-03-29
     * @param Request $request
     *
     * @return integer
     */
    public function setPagesize(Request $request): int
    {
        $pagesize = $request->pagesize;
        if (!$pagesize || $pagesize < 1) {
            $pagesize = config('per_size');
        }
        if ($request->export) {
            $pagesize = config('export_rows');
        }
        return $pagesize;
    }


    /**
     * 应用基于部门的权限限制到查询。
     *
     * @param $query  要修改的查询构建器实例。
     * @param int|null $departId 要过滤的部门 ID（如果适用）。
     * @param array $user 用户数据数组（包含 'is_admin', 'is_manager' 和 'uid'）。
     *
     * @return $query 修改后的查询构建器。
     */
    function applyUserPermission($query, int $departId = null, $user)
    {
        // If the user is not an admin, apply department restrictions
        if ($user['is_admin']) {
            return $query;
        }

        // If a department ID is provided...
        if ($departId) {
            // Get all related department IDs (including children, potentially)
            $departIds = getDepartIds([$departId], [$departId]);
            $query->whereIn('depart_id', $departIds);

            // If the user is a manager...
        } else if ($user['is_manager']) {
            // Get all related department IDs for the manager's department
            $departIds = getDepartIds([$user['depart_id']], [$user['depart_id']]);
            $query->whereIn('depart_id', $departIds);
        } else {
            // Restrict results to the user's own data
            $query->where('belong_uid', $user['uid']); // Make sure 'belong_uid' is the correct field 
        }

        return $query;
    }

    /**
     * Export data to Excel using a specified export class.
     *
     * @param mixed $data The data to be exported.
     * @param string $exportClass The class name of the export.
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse The Excel file download response.
     */
    public function exportToExcel($data, $exportClass)
    {
        $export = new $exportClass($data);
        $fileName = date('Ymd') . ".xlsx";
        return Excel::download($export, $fileName, \Maatwebsite\Excel\Excel::XLSX);
    }
}
