<?php


namespace App\Api\Services\Company;

use Illuminate\Support\Facades\Log;
use App\Api\Models\Company\CompanyDict;

class DictService
{
  public function dictModel()
  {
    return new CompanyDict;
  }

  /**
   * 业务全局字典编辑和新增
   *
   * @param [type] $dict_key
   * @param [type] $is_vaild
   * @return void
   */
  public function dictSave($dict, $user)
  {
    try {
      if (isset($dict['id']) && $dict['id'] > 0) {
        $dictModel = $this->dictModel()->find($dict['id']);
        $dictModel->u_uid = $user['id'];
      } else {
        $dictModel = $this->dictModel();
        $dictModel->company_id = $user['company_id'];
        $dictModel->c_uid = $user['id'];
      }
      $dictModel->dict_key = $dict['dict_key'];
      $dictModel->dict_value = $dict['dict_value'];
      $dictModel->is_vaild = $dict['is_vaild'];
      return $dictModel->save();
    } catch (\Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }
}
