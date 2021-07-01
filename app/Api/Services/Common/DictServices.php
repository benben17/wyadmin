<?php

namespace App\Api\Services\Common;

use Illuminate\Support\Facades\DB;
use App\Api\Models\Company\CompanyDict as DictModel;

/**
 * 字典服务
 */
class DictServices
{

  public function getByKey(array $companyIds, $dictKey)
  {
    $data = DictModel::select(DB::Raw('dict_value as value'))
      ->whereIn('company_id', $companyIds)
      ->where('dict_key', $dictKey)->get()->toArray();
    return $data;
  }

  public function getByKeyGroupBy($companyIds, $dictKey)
  {
    if (!is_array($companyIds)) {
      $companyIds = str2Array($companyIds);
    }
    $data = DictModel::selectRaw('group_concat(dict_value) as value')
      ->whereIn('company_id', $companyIds)
      ->where('dict_key', $dictKey)->first();
    if ($data) {
      return $data->toArray();
    }
    return $data;
  }
}
