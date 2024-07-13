<?php

namespace App\Api\Services\Tenant;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Tenant\BusinessInfo;
use App\Api\Models\Tenant\SkyeyeLog as TenantSkyeyeLog;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 *
 */
class BaseInfoService
{

  public function model()
  {
    return new BusinessInfo;
  }
  /**
   * 更新公司工商信息，手工更新
   * @Author   leezhua
   * @DateTime 2020-06-06
   * @param    [type]     $DA [description]
   */
  public function save($DA, $type = 1)
  {
    // Log::error(json_encode($DA));
    // if ($type == 1) {
    //   $company              = $this->model();
    //   $company->skyeye_id   = $DA['id'] ?? 0;
    // } else {
    $baseInfo              = $this->model()->where('name', $DA['name'])->first() ?? $this->model();
    $baseInfo->skyeye_id   = $DA['skyeye_id'] ?? $DA['id'] ?? 0;
    // }

    $baseInfo->name              = $DA['name'] ?? "";
    $baseInfo->regStatus         = $DA['regStatus'] ?? "";
    $baseInfo->historyNames      = $DA['historyNames'] ?? "";
    $baseInfo->companyOrgType    = $DA['companyOrgType'] ?? "";
    $baseInfo->regCapital        = $DA['regCapital'] ?? "";
    $baseInfo->staffNumRange     = $DA['staffNumRange'] ?? "";
    $baseInfo->industry          = $DA['industry'] ?? "";
    $baseInfo->bondNum           = $DA['bondNum'] ?? "";
    $baseInfo->type              = $DA['type'] ?? 0;
    $baseInfo->bondName          = $DA['bondName'] ?? "";
    $baseInfo->legalPersonName   = $DA['legalPersonName'] ?? "";
    $baseInfo->revokeReason      = $DA['revokeReason'] ?? "";
    $baseInfo->regNumber         = $DA['regNumber'] ?? "";
    $baseInfo->creditCode        = $DA['creditCode'] ?? "";
    $baseInfo->cancelDate        = $DA['cancelDate'] ?? null;
    $baseInfo->approvedTime      = $DA['approvedTime'] ?? null;
    $baseInfo->fromTime          = $DA['fromTime'] ?? null;
    $baseInfo->toTime            = $DA['toTime'] ?? null;
    $baseInfo->estiblishTime     = $DA['estiblishTime'] ?? null;
    $baseInfo->regInstitute      = $DA['regInstitute'] ?? "";
    $baseInfo->businessScope     = $DA['businessScope'] ?? "";
    $baseInfo->taxNumber         = $DA['taxNumber'] ?? "";
    $baseInfo->regLocation       = $DA['regLocation'] ?? "";
    $baseInfo->tags              = $DA['tags'] ?? "";
    $baseInfo->bondType          = $DA['bondType'] ?? "";
    $baseInfo->alias             = $DA['alias'] ?? "";
    $baseInfo->isMicroEnt        = $DA['isMicroEnt'] ?? 0;
    $baseInfo->base              = $DA['base'] ?? "";

    $res = $baseInfo->save();

    if ($res) {
      return $baseInfo;
    }
    return $res;
  }



  /**
   * [getMaintain description]
   * @Author   leezhua
   * @DateTime 2020-06-04
   * @param    integer    $id          [description]
   * @param    string     $CompanyName [description]
   * @return   [type]                  [description]
   */
  public function getById($id)
  {
    $companyInfo = $this->model()->find($id);
    if ($companyInfo) {
      return $companyInfo->toArray();
    } else {
      return (object)[];
    }
  }
  /**
   * 通过公司名获取工商信息
   * @Author leezhua
   * @Date 2024-04-01
   * @param mixed $companyName 
   * @return mixed 
   */
  public function getByName($companyName)
  {
    $companyInfo = $this->model()->where('name', $companyName)->first();
    return $companyInfo;
  }

  /**
   * 通过公司名获取工商信息,如果没有则通过天眼查获取
   * @Author leezhua
   * @Date 2024-04-01
   * @param mixed $companyName 
   * @param mixed $user 
   * @return array|false 
   * @throws BindingResolutionException 
   */
  public function getCompanyInfo($companyName, $user)
  {
    // 尝试从缓存中获取公司信息
    $skyeyeLog = TenantSkyeyeLog::where('search_name', 'like', '%' . $companyName . '%')->first();

    // 如果没有找到缓存的记录，通过 Skyeye 搜索公司信息
    if (!$skyeyeLog) {
      return $this->searchBySkyeye($companyName, $user);
    }

    // 如果找到了缓存的记录，解码搜索结果并格式化数据
    $skyeye = json_decode($skyeyeLog->search_result, true);
    return $this->formatSkyeyeData($skyeye);
  }

  /**
   * 通过天眼查获取工商信息
   * @Author   leezhua
   * @DateTime 2020-06-04
   * @param    [type]     $CompanyName [description]
   * @return   [type]                  [description]
   */
  public function searchBySkyeye($companyName, $user)
  {
    $skyeyeConfig = config('skyeye');
    $header  = array(
      'Authorization:' . $skyeyeConfig['token'],
    );
    $url = $skyeyeConfig['apiUrl'] . urlencode($companyName);
    $output =  http_request($url, $header);
    $data   = json_decode($output, true);

    if ($data['error_code'] == 0) {
      $this->saveSkyeyeSearchLog($companyName, $data['result'], $user);
      return $this->formatSkyeyeData($data['result']);
    } else {
      throw new Exception($data['reason']);
      return false;
    }
  }



  /**
   * 根据公司统计公司调用skyeye查询次数
   * @Author   leezhua
   * @DateTime 2020-07-16
   * @param    [type]     $companyId [description]
   * @return   [type]                [description]
   */
  public function skyeyeSearchCount($companyId)
  {
    $searchCount =  TenantSkyeyeLog::where('company_id', $companyId)->count();
    return $searchCount;
  }

  /**
   * 保存skyeye 查询日志
   * @Author   leezhua
   * @DateTime 2020-07-16
   * @param    [type]     $companyName  [description]
   * @param    [type]     $searchResult [description]
   * @param    [type]     $user         [description]
   * @return   [type]                   [description]
   */
  public function saveSkyeyeSearchLog($companyName, $searchResult, $user)
  {
    try {
      $skyeyeLog = new TenantSkyeyeLog;
      $skyeyeLog->company_id = $user['company_id'];
      $skyeyeLog->search_name = $companyName;
      $skyeyeLog->c_uid = $user['id'];
      $skyeyeLog->search_result = json_encode($searchResult);
      $skyeyeLog->save();
    } catch (Exception $e) {
      Log::info($user['company_id'] . json_encode($searchResult));
      Log::error($e->getMessage());
    }
  }

  //时间格式转换。毫秒转 Y-m-d H:i:s
  // $msectime 毫秒

  public function sec2Ymd($milliseconds)
  {
    $seconds = $milliseconds * 0.001;
    if (strstr($seconds, '.')) {
      sprintf("%01.3f", $seconds);
      list($usec, $sec) = explode(".", $seconds);
      $sec = str_pad($sec, 3, "0", STR_PAD_RIGHT);
    } else {
      $usec = $seconds;
      $sec = "000";
    }
    $date = date("Y-m-d", $usec);
    return str_replace('x', $sec, $date);
  }


  /**
   * 格式化天眼查数据
   * @Author leezhua
   * @Date 2024-04-01
   * @param array $DA 
   * @return array 
   */
  private function formatSkyeyeData(array $DA)
  {
    // 定义需要转换日期格式的字段
    $dateFields = ['cancelDate', 'approvedTime', 'fromTime', 'toTime', 'estiblishTime'];

    foreach ($dateFields as $field) {
      if (isset($DA[$field]) && $DA[$field]) {
        $DA[$field] = $this->sec2Ymd($DA[$field]);
      }
    }
    // 处理注册资本字段
    if (isset($DA['regCapital'])) {
      $DA['regCapital'] = str_replace("万人民币", "", $DA['regCapital']);
    }

    return $DA;
  }
}
