<?php

namespace App\Api\Services\Common;

use Illuminate\Support\Facades\DB;
use App\Api\Models\Company\CompanyDictType;
use App\Api\Models\Company\CompanyDict as DictModel;

/**
 * 字典服务
 */
class DictServices
{

  public function dictModel()
  {
    return new DictModel();
  }

  public function dictTypeModel()
  {
    return new CompanyDictType();
  }

  public function getByKey(array $companyIds, $dictKey)
  {
    $data = $this->dictModel()->select(DB::Raw('dict_value as value'))
      ->whereIn('company_id', $companyIds)
      ->where('is_vaild', 1)
      ->where('dict_key', $dictKey)->get()->toArray();
    return $data;
  }

  /**
   * 通过dict key 获取字典  分组，逗号隔开
   * @Author leezhua
   * @Date 2024-04-01
   * @param mixed $companyIds 
   * @param mixed $dictKey 
   * @return mixed 
   */
  public function getByKeyGroupBy($companyIds, $dictKey)
  {
    if (!is_array($companyIds)) {
      $companyIds = str2Array($companyIds);
    }
    $data = $this->dictModel()->selectRaw('group_concat(dict_value) as value')
      ->whereIn('company_id', $companyIds)
      ->where('dict_key', $dictKey)->first();
    if ($data) {
      return $data->toArray();
    }
    return $data;
  }

  /**
   * 通过dict key 获取字典
   * @Author leezhua
   * @Date 2024-04-01
   * @param mixed $companyIds 
   * @param mixed $dictKey 
   * @return mixed 
   */
  public function getDicts($companyIds, $dictKey)
  {
    if (!is_array($companyIds)) {
      $companyIds = str2Array($companyIds);
    }
    $data = $this->dictModel()->selectRaw('id, dict_value')
      ->whereIn('company_id', $companyIds)
      ->where('dict_key', $dictKey)
      ->where('is_vaild', 1)
      ->get();

    return $data;
  }
}
