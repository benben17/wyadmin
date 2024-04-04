<?php

namespace App\Api\Services\Company;

use Exception;
use Illuminate\Support\Facades\DB;
use App\Api\Models\Company\FeeType;
use Illuminate\Support\Facades\Log;


/**
 * 账单费用类型信息
 */
class FeeTypeService
{
  public function model(): FeeType
  {
    try {
      return new FeeType;
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
  public function enable($feeTypeIds, $isValid, $user): bool
  {
    $where['company_id']  = $user['company_id'];
    $data['is_valid'] = $isValid;
    $data['u_uid'] = $user['id'];
    return $this->model()->where($where)->whereIn('id', $feeTypeIds)->update($data);
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


  /**
   * 根据feeId 获取feeNames 返回数组
   *
   * @Author leezhua
   * @DateTime 2024-03-05
   * @param array $feeIds
   *
   * @return array
   */
  public function getFeeNames($feeIds): array
  {
    $feeType = FeeType::selectRaw('GROUP_CONCAT(fee_name) as fee_names')
      ->whereIn('id', str2Array($feeIds))->first();
    return str2Array($feeType['fee_names']) ?? array();
  }
}
