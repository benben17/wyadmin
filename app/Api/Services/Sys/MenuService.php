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



  public function getMenuByIds(array $ids): array
  {
    return $this->menuModel->whereIn('id', $ids)
      ->orderBy('sort', 'asc')
      ->get()->toArray();
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
      $DA['created_at'] = nowYmd();
      $DA['updated_at'] = nowYmd();
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
   * @param array $fields 需要保留的字段
   * @return array
   */
  public function buildTree(array $menus, int $parentId = 0, array $fields = []): array
  {
    // 如果指定了字段，则确保'id'和'pid'字段总是存在
    if (!empty($fields)) {
      $fields = array_unique(array_merge(['id', 'pid'], $fields));
      $fieldsFlipped = array_flip($fields); // 预先计算以提高效率
    }

    $tree = [];
    foreach ($menus as $menu) {
      if ($menu['pid'] == $parentId) {
        // 根据是否指定了字段来构建节点
        $node = empty($fields) ? $menu : array_intersect_key($menu, $fieldsFlipped);
        // 递归构建子树
        $node['children'] = $this->buildTree($menus, $menu['id'], $fields);
        $tree[] = $node;
      }
    }

    return $tree;
  }
}
