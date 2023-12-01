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
      $feeType->fee_name = $DA['fee_name'];
      $feeType->type = $DA['type'];
      return $feeType->save();
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }

  /**
   * 费用类型启用禁用，不允许删除
   *
   * @param [type] $feeTypeIds
   * @param [type] $isValid
   * @param [type] $uid
   * @return bool
   */
  public function enable($feeTypeIds, $isValid, $uid): bool
  {
    $where['company_id']  = getCompanyId($uid);
    $data['is_valid'] = $isValid;
    // DB::enableQueryLog();
    return $this->model()->where($where)->whereIn('id', $feeTypeIds)->update($data);
    // return response()->json(DB::getQueryLog());
    // return $res;
  }

  /**
   * 获取 费用id集合 1 费用 2 押金
   *
   * @Author leezhua
   * @DateTime 2021-07-12
   * @param [type] $type
   * @param [type] $uid
   *
   * @return array
   */
  public function getFeeIds($type, $uid): array
  {
    $feeType = FeeType::select(DB::raw('group_concat(id) fee_id,type'))
      ->whereIn('company_id', getCompanyIds($uid))
      ->where(function ($q) use ($type) {
        $q->where('type', $type);
      })
      ->groupBy('type')->first();
    if ($feeType) {
      return str2Array($feeType['fee_id']);
    }
    return array();
  }
}
