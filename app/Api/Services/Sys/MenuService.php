<?php

namespace App\Api\Services\Sys;

use Exception;
use App\Api\Models\Sys\MenuModel;

class MenuService
{

  private $menuModel;

  public function __construct()
  {
    $this->menuModel = new MenuModel;
  }


  /** 菜单新增 */
  public function addMenu($DA)
  {
    try {
      // 判断 name不允许重复 path 也不允许重复
      $exists = $this->menuModel->where('name', $DA['name'])->whereOr('path', $DA['path'])->count() > 0;
      if ($exists) {
        throw new Exception('菜单名称已存在');
      }

      // 格式化传入的数据 
      $DA['pid']         = $DA['pid'] ?? 0;
      $DA['menu_type']   = $DA['menu_type'] ?? 1;
      $DA['sort']        = $DA['sort'] ?? 0;
      $DA['status']      = $DA['status'] ?? 1;
      $DA['is_show']     = $DA['is_show'] ?? 1;
      $DA['create_time'] = nowYmd();
      $DA['update_time'] = nowYmd();
      $DA['name']        = $DA['name'];
      $DA['component']   = $DA['component'] ?? '';
      $DA['path']        = $DA['path'];
      $DA['icon']        = $DA['icon'] ?? '';
      $DA['permission']  = $DA['permission'] ?? '';

      $res = $this->menuModel->create($DA);
    } catch (Exception $e) {
      throw new Exception($e->getMessage());
    }
  }

  /**
   * 获取PC端菜单
   *
   * @param int $menuType 菜单类型
   * @return array
   */

  public function getMenus(int $menuType)
  {
    // 获取父菜单数据
    $allMenus = $this->menuModel
      ->where('menu_type', $menuType)
      ->orderBy('sort', 'asc')
      ->get()
      ->toArray();

    // 调用递归函数构建树形结构
    return $this->buildTree($allMenus, 0);
  }

  /**
   * 递归构建树形结构
   *
   * @param array $menus 所有菜单数据
   * @param int $parentId 父级菜单ID
   * @return array
   */
  private function buildTree(array $menus, int $parentId = 0): array
  {
    $tree = [];
    foreach ($menus as $menu) {
      if ($menu['pid'] == $parentId) {
        $menu['children'] = $this->buildTree($menus, $menu['id']);
        $tree[] = $menu;
      }
    }
    return $tree;
  }
}
