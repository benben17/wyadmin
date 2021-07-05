<?php

namespace App\Api\Services\Company;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Api\Models\Company\FeeType;


/**
 * 账单费用类型信息
 */
class FeeTypeService
{
  public function model()
  {
    try {
      $model = new FeeType;
      return $model;
    } catch (Exception $e) {
      Log::error($e->getMessage());
      throw new Exception("模型获取失败！");
    }
  }

  /**
   * 费用保存、系统默认自带一部分费用类型，其他的费用类型用户可自行添加
   *
   * @param [type] $DA
   * @param [type] $user
   * @return void
   */
  public function save($DA, $user)
  {
    try {
      if (isset($DA['id']) && $DA['id'] > 0) {
        $feeType = $this->model()->find($DA['id']);
        $feeType->u_uid = $user['id'];
      } else {
        $feeType = $this->model();
        $feeType->company_id = $user['company_id'];
        $feeType->c_uid = $user['id'];
      }
      $feeType->fee_name = $DA['free_name'];
      $feeType->fee_type = isset($DA['free_type']) ? $DA['free_type'] : 1;
      return $feeType->save();
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }

  /**
   * 费用类型启用禁用，不允许删除
   *
   * @param [type] $feeTypeId
   * @param [type] $is_vaild
   * @param [type] $uid
   * @return void
   */
  public function enable($feeTypeIds, $isVaild, $uid)
  {
    $where['company_id']  = getCompanyId($uid);

    $data['is_vaild'] = $isVaild;
    DB::enableQueryLog();
    return $this->model()->where($where)->whereIn('id', $feeTypeIds)->update($data);
    // return response()->json(DB::getQueryLog());
    // return $res;
  }
}
