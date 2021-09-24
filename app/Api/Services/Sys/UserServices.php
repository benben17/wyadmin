<?php

namespace App\Api\Services\Sys;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

use App\Api\Models\Sys\UserGroup as UserGroupModel;
use App\Api\Models\Sys\UserRole as UserRoleModel;
use App\Api\Models\Sys\UserProfile as UserProfileModel;
use App\Api\Models\Project as ProjectModel;
use App\Api\Models\Weixin\WxInfo;

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
    } else {
      return [];
    }
  }

  /**
   * 用户环境变量保存
   */
  public function saveUserProfile(array $DA, $uid)
  {
    $profile = UserProfileModel::find($uid);
    if (!$profile) {
      $profile = new UserProfileModel;
    }
    $profile->user_id = $uid;
    if (isset($DA['default_proj_id']) && $DA['default_proj_id'] > 0) {
      $profile->default_proj_id = $DA['default_proj_id'];
    }
    if (isset($DA['page_rows']) && $DA['page_rows'] > 0) {
      $profile->page_rows     = $DA['page_rows'];
    }
    $res = $profile->save();
    return $res;
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
    $wxInfo = WxInfo::where('unionid', $user['unionid'])->first();    // 获取用户系统权限，当用户is admin 的时候返回空
    $data['info'] = [
      'name' => $user->realname,
      'uid' => $user->id,
      // 'avatar' => $user->avatar,
      'access' => ['admin'],
      'company_name' => $result->name,
      'company_access' => [$result->product->en_name],
      'avatar' => $wxInfo->avatar,
      'nickname' => $wxInfo->nickname,
      'depart_name' => $depart->name
    ];
    // $data['menu_list'] = $this->userMenu($user);
    return $data;
  }
}
