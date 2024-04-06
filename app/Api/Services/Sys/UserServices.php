<?php

namespace App\Api\Services\Sys;

use Exception;
use App\Api\Models\Weixin\WxUser;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;
use App\Api\Models\Project as ProjectModel;
use App\Api\Models\Sys\UserRole as UserRoleModel;
use App\Api\Models\Sys\UserGroup as UserGroupModel;
use App\Api\Models\Sys\UserProfile as UserProfileModel;

/**
 * 用户、用户角色、用户组服务
 */
class UserServices
{

  /** 检查角色名称是否重复 */
  public function isRepeat($DA, $companyId)
  {
    $map['company_id'] = $companyId;
    $map['name'] = $DA['name'];
    if (isset($DA['id']) && $DA['id'] > 0) {
      $res = UserRoleModel::where($map)->where('id', '!=', $DA['id'])->exists();
    } else {
      $res = UserRoleModel::where($map)->exists();
    }
    return $res;
  }


  public function roleModel()
  {
    $model = new UserRoleModel;
    return $model;
  }

  public function userModel()
  {
    $model = new \App\Models\User;
    return $model;
  }



  public function getRoleById($Id)
  {
    $roleInfo = UserRoleModel::find($Id);
    return $roleInfo;
  }

  /**
   * 根据用户ID获取系统权限
   * @Author   leezhua
   * @DateTime 2020-07-18
   * @param    [type]     $DA [description]
   * @return   [type]         [description]
   */
  public function userMenu($DA)
  {
    if (!$DA['is_admin']) {
      $res = $this->getRoleById($DA['role_id']);
      if ($res) {
        return  str2Array($res['menu_list']);
      }
    }
    return [];
  }

  /**
   * 用户环境变量保存
   */
  public function saveUserProfile(array $DA, $uid)
  {
    $data = ['user_id' => $uid];
    $data['default_proj_id'] = $DA['default_proj_id'] ?? 0;
    $data['page_rows'] = $DA['page_rows'] ?? 15;
    $profile = UserProfileModel::updateOrCreate(['user_id' => $uid], $data);
    return $profile->wasRecentlyCreated || $profile->wasChanged();
  }


  /** 获取用户基本信息 */
  public function getLoginUser($uid)
  {
    $projInfo = "";
    $profile = UserProfileModel::find($uid);
    if ($profile) {
      $projInfo = ProjectModel::select('id', 'proj_name')->find($profile->default_proj_id);
    }
    return $projInfo;
  }


  /**
   * @Desc: 微信登录用户信息
   * @Author leezhua
   * @Date 2024-03-31
   * @param [type] $user
   * @param string $token
   * @return void
   */
  public function loginUserInfo($user, $token = "")
  {
    $result = \App\Models\Company::with("product")->find($user['company_id']);
    $project_info = $this->getLoginUser($user->id);
    $data = [
      'token' => $token,
      'uuid' => $user->id,
      'username' => $user->name,
      'is_admin' => $user->is_admin,
      'phone' => $user->phone
    ];
    $data['project_info'] = $project_info;
    $depart = getDepartById($user->depart_id);
    $wxUser = WxUser::where('unionid', $user['unionid'])->first();    // 获取用户系统权限，当用户is admin 的时候返回空
    $data['info'] = [
      'name'           => $user->realname,
      'uid'            => $user->id,
      'access'         => ['admin'],
      'company_name'   => $result->name,
      'company_access' => [$result->product->en_name],
      'avatar'         => $wxUser->avatar,
      'nickname'       => $wxUser->nickname,
      'depart_name'    => $depart->name
    ];
    // $data['menu_list'] = $this->userMenu($user);
    return $data;
  }

  /**
   * @Desc: 权限认证
   * @Author leezhua
   * @Date 2024-03-31
   * @param [type] $user
   * @return void
   */
  public static function filterByDepartId($query, $user, $depart_id)
  {
    if ($user['is_admin']) {
      return $query;
    }
    if ($depart_id) {
      $departIds = getDepartIds([$depart_id], [$depart_id]);
      $query->whereIn('depart_id', $departIds);
    }
    if ($user['is_manager']) {
      $departIds = getDepartIds([$user['depart_id']], [$user['depart_id']]);
      $query->whereIn('depart_id', $departIds);
    } else if (!$depart_id) {
      $query->where('c_uid', $user['id']);
    }
    return $query;
  }
}
