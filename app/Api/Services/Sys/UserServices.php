<?php

namespace App\Api\Services\Sys;

use Exception;
use Illuminate\Support\Str;
use App\Api\Models\Weixin\WxUser;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
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
        return str2Array($res['menu_list']);
      }
    }
    return [];
  }



  /**
   * 根据用户ID获取系统权限
   * @Author   leezhua
   * @DateTime 2020-07-18
   * @param    [type]     $DA [description]
   * @return   [type]         [description]
   */
  public function userAppMenu($DA)
  {
    // 直接初始化$menuList为空数组，如果没有结果直接返回
    $menuList = [];
    // 尝试获取角色信息
    $res = $this->getRoleById($DA['role_id']);
    // 如果没有找到角色信息，直接返回空数组
    if (!$res) {
      return $menuList;
    }

    // 使用MenuService获取菜单信息
    $menuService = new MenuService();
    $menus = $menuService->getMenuByIds(str2Array($res['app_menu_list']));
    // 指定需要处理的字段
    $fields = ['id', 'pid', 'name', 'icon', 'path', 'sort', 'type', 'children'];
    // 构建菜单树
    $menuList = $menuService->buildTree($menus, 0, $fields);

    return $menuList;
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
   * @Desc:   获取小程序所有菜单
   * @Author leezhua
   * @Date 2024-03-31
   * @param [type] $user
   * @return void
   */
  public function getAppMenus()
  {
    $menuService = new MenuService;
    return $menuService->getMenus(2);
  }



  /**
   * @Desc:   获取用户信息
   * @Author leezhua
   * @Date 2024-03-31
   * @param [type] $user
   * @return void
   */
  public function getLoginUserInfo(int $userId): array
  {
    $user = Auth::user(); // 从 Auth facade 中获取当前用户
    $projectInfo = $this->getLoginUser($userId);

    $isBind = 0;
    $nickname = "";
    if ($user->unionid) {
      $wxUser = WxUser::where('unionid', $user->unionid)->first();
      if ($wxUser) {
        $isBind = 1;
        $nickname = $wxUser->nickname;
      }
    }

    $result = \App\Models\Company::with("product")->find($user->company_id);

    $departName = '管理员';
    if ($user->depart_id !== 0) {
      $depart = getDepartById($user->depart_id); //  假设 getDepartById 是一个全局函数
      $departName = $depart->name ?? '';
    }

    return [
      'uuid'         => $user->id,
      'username'     => $user->name,
      'is_admin'     => $user->is_admin,
      'phone'        => $user->phone,
      'realname'     => $user->realname,
      'is_bind'      => $isBind,
      'role_id'      => $user->role_id,
      'project_info' => $projectInfo,
      'info' => [
        'name'           => $user->realname,
        'uid'            => $user->id,
        'avatar'         => $user->avatar,
        'avatar_full'    => getOssUrl($user->avatar),
        'access'         => ['admin'],
        'company_name'   => $result->name,
        'company_access' => [$result->product->en_name],
        'nickname'       => $nickname,
        'depart_name'    => $departName,
        'days'           => getVariable($user->company_id, 'year_days'),
      ]
    ];
  }


  /**
   * 将扁平数组转换为树形结构，并添加层级 ID
   *
   * @param array $data 原始数组
   * @param string $idKey ID 字段名
   * @param string $childrenKey 子节点字段名
   * @param string $parentIdKey 父节点 ID 字段名
   * @param int $parentId 父节点 ID
   * @param string $levelIdPrefix 层级 ID 前缀
   * @return array
   */
  public static function treeWithLevelId(
    array $data,
    string $idKey = 'id',
    string $childrenKey = 'sub',
    string $parentIdKey = 'parent_id',
    int $parentId = 0,
    string $levelIdPrefix = ''
  ): array {
    $tree = [];
    foreach ($data as $item) {
      if ($item[$parentIdKey] == $parentId) {
        $levelId = $levelIdPrefix . ($levelIdPrefix ? '-' : '') . $item[$idKey];
        $item['level_id'] = $levelId;
        $item[$childrenKey] = self::treeWithLevelId(
          $data,
          $idKey,
          $childrenKey,
          $parentIdKey,
          $item[$idKey],
          $levelId
        );
        $tree[] = $item;
      }
    }
    return $tree;
  }
}
