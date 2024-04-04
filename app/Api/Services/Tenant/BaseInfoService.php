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
    $company              = $this->model()->where('name', $DA['name'])->first() ?? $this->model();
    $company->skyeye_id   = $DA['skyeye_id'] ?? $DA['id'];
    // }

    $company->name              = $DA['name'] ?? "";
    $company->regStatus         = $DA['regStatus'] ?? "";
    $company->historyNames      = $DA['historyNames'] ?? "";
    $company->companyOrgType    = $DA['companyOrgType'] ?? "";
    $company->regCapital        = $DA['regCapital'] ?? "";
    $company->staffNumRange     = $DA['staffNumRange'] ?? "";
    $company->industry          = $DA['industry'] ?? "";
    $company->bondNum           = $DA['bondNum'] ?? "";
    $company->type              = $DA['type'] ?? 0;
    $company->bondName          = $DA['bondName'] ?? "";
    $company->legalPersonName   = $DA['legalPersonName'] ?? "";
    $company->revokeReason      = $DA['revokeReason'] ?? "";
    $company->regNumber         = $DA['regNumber'] ?? "";
    $company->creditCode        = $DA['creditCode'] ?? "";
    $company->cancelDate        = $DA['cancelDate'] ?? null;
    $company->approvedTime      = $DA['approvedTime'] ?? null;
    $company->fromTime          = $DA['fromTime'] ?? null;
    $company->toTime            = $DA['toTime'] ?? null;
    $company->estiblishTime     = $DA['estiblishTime'] ?? null;
    $company->regInstitute      = $DA['regInstitute'] ?? "";
    $company->businessScope     = $DA['businessScope'] ?? "";
    $company->taxNumber         = $DA['taxNumber'] ?? "";
    $company->regLocation       = $DA['regLocation'] ?? "";
    $company->tags              = $DA['tags'] ?? "";
    $company->bondType          = $DA['bondType'] ?? "";
    $company->alias             = $DA['alias'] ?? "";
    $company->isMicroEnt        = $DA['isMicroEnt'] ?? 0;
    $company->base              = $DA['base'] ?? "";

    $res = $company->save();

    if ($res) {
      return $company;
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
    $skyeyeLog = TenantSkyeyeLog::where('search_name', $companyName)->first();
    if (!$skyeyeLog) {
      $companyInfo = $this->searchBySkyeye($companyName, $user);
    } else {
      $skyeyeLog = $skyeyeLog->toArray();
      $skyeye = json_decode($skyeyeLog['search_result'], true);
      $companyInfo = $this->formatSkyeyeData($skyeye);
    }
    // Log::info(json_encode($companyInfo));
    return $companyInfo;
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
    $name = urlencode($companyName);
    $url = $skyeyeConfig['apiUrl'] . $name;
    $curl = curl_init();
    if (!empty($header)) {
      curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
      curl_setopt($curl, CURLOPT_HEADER, 0); //返回response头部信息
    }
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    if (!empty($data)) {
      curl_setopt($curl, CURLOPT_HTTPGET, 1);
      curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    $output = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($output, true);

    if ($data['error_code'] == 0) {
      $this->saveSkyeyeSearchLog($companyName, $data['result'], $user);
      return $this->formatSkyeyeData($data['result']);
    } else {
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

    if (isset($DA['cancelDate']) && $DA['cancelDate']) {
      $DA['cancelDate'] = $this->sec2Ymd($DA['cancelDate']);
    }
    if (isset($DA['approvedTime']) && $DA['approvedTime']) {
      $DA['approvedTime'] = $this->sec2Ymd($DA['approvedTime']); //'经营开始时间'
    }
    if (isset($DA['fromTime']) && $DA['fromTime']) {
      $DA['fromTime'] = $this->sec2Ymd($DA['fromTime']);
    }
    if (isset($DA['toTime']) && $DA['toTime']) {
      $DA['toTime'] = $this->sec2Ymd($DA['toTime']);
    }
    if (isset($DA['estiblishTime']) && $DA['estiblishTime']) {
      $DA['estiblishTime'] = $this->sec2Ymd($DA['estiblishTime']);
    }
    if (isset($DA['regCapital'])) {
      $DA['regCapital'] = str_replace("万人民币", "", $DA['regCapital']);
    }

    return $DA;
  }
}
