<?php

namespace App\Api\Services\Sys;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\Depart;
use App\Models\User;

/**
 * 部门管理
 *
 * @Author leezhua
 * @DateTime 2021-08-21
 */
class DepartService
{
  public function model()
  {
    return new Depart;
  }

  /**
   * 检查是否重复
   *
   * @Author leezhua
   * @DateTime 2021-08-21
   * @param [type] $DA
   * @param [type] $companyId
   *
   * @return boolean
   */
  public function isRepeat($DA)
  {
    $map['name'] = $DA['name'];
    if (isset($DA['id']) && $DA['id'] > 0) {
      $res = $this->model()->where($map)->where('id', '!=', $DA['id'])
        ->where('parent_id', $DA['parent_id'])
        ->exists();
    } else {
      $res = $this->model()->where($map)->exists();
    }
    return $res;
  }
  /**
   * 用户组保存
   *
   * @Author leezhua
   * @DateTime 2021-08-21
   * @param [type] $DA
   * @param [type] $user
   *
   * @return void
   */
  public function save($DA, $user)
  {
    if (isset($DA['id']) && $DA['id'] > 0) {
      $depart = $this->model()->find($DA['id']);
      $depart->u_uid = $user['id'];
    } else {
      $depart = $this->model();
      $depart->company_id = $user['company_id'];
      $depart->c_uid = $user['id'];
    }
    $depart->name       = $DA['name'];
    $depart->parent_id  = $DA['parent_id'];
    $depart->seq        = isset($DA['seq']) ? $DA['seq'] : 0;
    $depart->remark     = isset($DA['remark']) ? $DA['remark'] : "";
    $res = $depart->save();
    return $res;
  }

  /**
   * 组织结构列表
   *
   * @Author leezhua
   * @DateTime 2021-08-21
   * @param [type] $parentId
   *
   * @return void
   */
  public function getDepartList($parentId)
  {
    DB::enableQueryLog();
    $data = $this->model()->where('parent_id', $parentId)
      ->orderBy('seq', 'asc')->get();
    // return response()->json(DB::getQueryLog());

    foreach ($data as $k => &$v) {
      $v['user_count'] = User::whereIn('depart_id', getDepartIds([$v['id']], [$v['id']]))->count();

      $children = $this->getDepartList($v['id']);
      if (!$children) {
        continue;
      }
      $v['_showChildren'] = true;
      $v['children'] = $children;
    }

    return $data;
  }

  /**
   * Undocumented function
   *
   * @Author leezhua
   * @DateTime 2021-08-23
   * @param [type] $parentId
   * @param integer $isValid
   *
   * @return array
   */
  public function getDepartSelect($parentId, $isValid = 1)
  {
    DB::enableQueryLog();
    $data = $this->model()->selectRaw('id,name,seq,is_vaild')
      ->where('parent_id', $parentId)
      ->where(function ($q) use ($isValid) {
        $isValid && $q->where('is_vaild', $isValid);
      })
      ->orderBy('seq', 'asc')->get()->toArray();
    // return response()->json(DB::getQueryLog());

    foreach ($data as $k => &$v) {
      $v['id'] = $v['id'];
      $v['label'] = $v['name'];
      $children = $this->getDepartSelect($v['id']);
      if (!$children) {
        continue;
      }
      $v['children'] = $children;
    }
    return $data;
  }
}
