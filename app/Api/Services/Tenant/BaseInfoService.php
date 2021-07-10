<?php

namespace App\Api\Services\Tenant;

use Illuminate\Support\Facades\Log;
use App\Api\Models\Tenant\BaseInfo;
use App\Api\Models\Tenant\SkyeyeLog as TenantSkyeyeLog;
use Exception;

/**
 *
 */
class BaseInfoService
{

  public function model()
  {
    $model = new BaseInfo;
    return $model;
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
    if ($type == 1) {
      $company = $this->model();
      $company->skyeye_id = isset($DA['id']) ? $DA['id'] : 0; //天眼查ID
    } else {
      $company = $this->model()->find($DA['id']);
      $company->skyeye_id = isset($DA['skyeye_id']) ? $DA['skyeye_id'] : $DA['id'];
    }

    $company->name = isset($DA['name']) ? $DA['name'] : ""; //公司名

    $company->regStatus  = isset($DA['regStatus']) ? $DA['regStatus'] : ""; //状态
    $company->historyNames = isset($DA['historyNames']) ? $DA['historyNames'] : "";
    $company->companyOrgType = isset($DA['companyOrgType']) ? $DA['companyOrgType'] : "";
    $company->regCapital = isset($DA['regCapital']) ? $DA['regCapital'] : "";
    $company->staffNumRange = isset($DA['staffNumRange']) ? $DA['staffNumRange'] : "";
    $company->industry = isset($DA['industry']) ? $DA['industry'] : "";
    $company->bondNum = isset($DA['bondNum']) ? $DA['bondNum'] : "";
    $company->type = isset($DA['type']) ? $DA['type'] : 0;
    $company->bondName = isset($DA['bondName']) ? $DA['bondName'] : "";
    $company->legalPersonName  = isset($DA['legalPersonName']) ? $DA['legalPersonName'] : "";
    $company->revokeReason = isset($DA['revokeReason']) ? $DA['revokeReason'] : "";
    $company->regNumber = isset($DA['regNumber']) ? $DA['regNumber'] : "";
    // $company->property3 = isset($DA['property3'];
    $company->creditCode = isset($DA['creditCode']) ? $DA['creditCode'] : "";
    //统一社会信用代码
    if (isset($DA['cancelDate'])) {
      $company->cancelDate = $DA['cancelDate'];
    }
    if (isset($DA['approvedTime'])) {
      $company->approvedTime = $DA['approvedTime'];
    }
    if (isset($DA['fromTime'])) {
      $company->fromTime = $DA['fromTime'];
    }
    if (isset($DA['toTime'])) {
      $company->toTime = $DA['toTime'];
    }
    if (isset($DA['estiblishTime'])) {
      $company->estiblishTime = $DA['estiblishTime'];
    }
    $company->regInstitute = isset($DA['regInstitute']) ? $DA['regInstitute'] : "";
    $company->businessScope = isset($DA['businessScope']) ? $DA['businessScope'] : "";
    $company->taxNumber = isset($DA['taxNumber']) ? $DA['taxNumber'] : "";
    $company->regLocation = isset($DA['regLocation']) ? $DA['regLocation'] : "";
    $company->tags = isset($DA['tags']) ? $DA['tags'] : "";
    $company->bondType = isset($DA['bondType']) ? $DA['bondType'] : "";
    $company->alias = isset($DA['alias']) ? $DA['alias'] : "";
    $company->isMicroEnt = isset($DA['isMicroEnt']) ? $DA['isMicroEnt'] : 0;
    $company->base = isset($DA['base']) ? $DA['base'] : "";
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
      return "";
    }
  }

  public function getByName($companyName)
  {
    $companyInfo = $this->model()->where('name', $companyName)->first();
    return $companyInfo;
  }

  public function getCompanyInfo($companyName, $user)
  {
    $skyeyeLog = TenantSkyeyeLog::where('search_name', $companyName)->first();
    if (!$skyeyeLog) {
      $companyInfo = $this->searchByskyeye($companyName, $user);
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
  public function searchByskyeye($companyName, $user)
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
      //写入本地
      // $this->add($data['result']);
      // 记录日志
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
  public function sec2Ymd($msectime)
  {
    $msectime = $msectime * 0.001;
    if (strstr($msectime, '.')) {
      sprintf("%01.3f", $msectime);
      list($usec, $sec) = explode(".", $msectime);
      $sec = str_pad($sec, 3, "0", STR_PAD_RIGHT);
    } else {
      $usec = $msectime;
      $sec = "000";
    }
    $date = date("Y-m-d", $usec);
    return $mescdate = str_replace('x', $sec, $date);
  }



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
