<?php

namespace App\Api\Controllers;

use Illuminate\Http\Request;
use function PHPSTORM_META\type;
use Illuminate\Support\Facades\DB;
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
    public function __construct()
    {
        $this->uid  = auth()->payload()->get('sub');
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

        if (!isEmptyObj($data) && is_object($data)) {
            $data = $data->toArray();
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
        if (is_object($data)) {
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

    public function formatArray($data)
    {
        return array_map(function ($value) {
            return is_null($value) ? '' : $value;
        }, $data);
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
