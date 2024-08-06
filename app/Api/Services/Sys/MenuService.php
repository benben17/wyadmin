<?php

namespace App\Api\Services\Sys;

use Exception;
use App\Api\Models\Sys\MenuModel;

class MenuService
{


  public function __construct()
  {
  }
  public function menuModel()
  {
    return new MenuModel;
  }

  public function getMenuByIds(array $ids): array
  {
    return $this->menuModel()->whereIn('id', $ids)
      ->orderBy('sort', 'asc')
      ->get()->toArray();
  }


  /** 菜单新增 */
  public function addMenu(array $DA)
  {
    try {
      $this->validateMenuData($DA);

      $DA = $this->formatMenuData($DA);

      $this->menuModel()->create($DA);
    } catch (Exception $e) {
      throw new Exception($e->getMessage());
    }
  }

  public function editMenu(array $DA)
  {
    try {
      $menu = $this->menuModel()->findOrFail($DA['id']);
      if (!$menu) {
        throw new Exception($DA['name'] . '菜单不存在');
      }

      $this->validateMenuData($DA, $DA['id']);
      $DA = $this->formatMenuData($DA);

      $menu->update($DA);
    } catch (Exception $e) {
      throw new Exception($e->getMessage());
    }
  }

  private function validateMenuData(array $DA, $id = null)
  {
    $query = $this->menuModel()->where('name', $DA['name'])
      ->where('menu_type', $DA['menu_type'])
      ->where('path', $DA['path']);

    if ($id) {
      $query->where('id', '!=', $id);
    }

    if ($query->exists()) {
      throw new Exception('菜单名称或路径已存在');
    }
  }

  private function formatMenuData(array $DA)
  {
    return [
      'pid' => $DA['pid'] ?? 0,
      'menu_type' => $DA['menu_type'] ?? 1,
      'sort' => $DA['sort'] ?? 0,
      'status' => $DA['status'] ?? 1,
      'is_show' => $DA['is_show'] ?? 1,
      'created_at' => now(),
      'updated_at' => now(),
      'name' => $DA['name'],
      'component' => $DA['component'] ?? '',
      'path' => $DA['path'],
      'icon' => $DA['icon'] ?? '',
      'permission' => $DA['permission'] ?? '',
    ];
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
    $allMenus = $this->menuModel()
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
