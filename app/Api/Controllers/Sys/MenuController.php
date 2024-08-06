<?php

use Svg\Tag\Rect;

namespace App\Api\Controllers\Sys;

use Illuminate\Http\Request;
use App\Api\Services\Sys\MenuService;
use App\Api\Controllers\BaseController;

class MenuController extends BaseController
{
  private $menuService;
  public function __construct()
  {
    parent::__construct();
    $this->menuService = new MenuService;
  }

  /**
   * @OA\Get(
   *     path="/api/sys/menu/list",
   *     tags={"系统管理"},
   *     summary="菜单列表",
   *     @OA\Parameter(
   *         name="menu_type",
   *         in="query",
   *         description="菜单类型 1:PC端 2:移动端",
   *         required=true,
   *         @OA\Schema(
   *             type="int"
   *         )
   *     ),
   *     @OA\Parameter(
   *         name="return_type",
   *         in="query",
   *         description="返回类型 tree:树形结构",
   *         required=false,
   *         @OA\Schema(
   *             type="string"
   *         )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description="菜单列表"
   *     )
   * )
   */
  public function index(Request $request)

  {
    $request->validate([
      'menu_type' => 'required|in:1,2',

    ], [
      'menu_type.required' => '菜单类型不能为空',
      'menu_type.in' => '菜单类型不正确1:PC端 2:移动端',
    ]);
    if ($request->return_type == 'tree') {
      $menus = $this->menuService->getMenus($request->menu_type);
    } else {
      $menus = $this->menuService->menuModel()
        ->where('menu_type', $request->menu_type)->get()->toArray();
    }
    return $this->success($menus);
  }

  /**
   * @OA\Post(
   *     path="/api/sys/menu/add",
   *     tags={"系统管理"},
   *     summary="新增菜单",
   *     @OA\RequestBody(
   *         @OA\MediaType(
   *             mediaType="application/json",
   *             @OA\Schema(
   *                 required={"name", "path"},
   *                 @OA\Property(property="name", type="string", description="菜单名称"),
   *                 @OA\Property(property="path", type="string", description="菜单路径"),
   *                 @OA\Property(property="pid", type="int", description="父级菜单ID"),
   *                 @OA\Property(property="menu_type", type="int", description="菜单类型 1:PC端 2:移动端"),
   *                 @OA\Property(property="sort", type="int", description="排序"),
   *                 @OA\Property(property="status", type="int", description="状态 1:启用 2:禁用"),
   *                 @OA\Property(property="is_show", type="int", description="是否显示 1:显示 2:隐藏"),
   *                 @OA\Property(property="component", type="string", description="组件路径"),
   *                 @OA\Property(property="icon", type="string", description="图标"),
   *                 @OA\Property(property="permission", type="string", description="权限标识"),
   *             )
   *         )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description="新增菜单"
   *     )
   * )
   */
  public function add(Request $request)
  {
    $request->validate([
      'pid' => 'required|numeric',
      'name' => 'required',
      'path' => 'required',
      'menu_type' => 'required|in:1,2',
    ], [
      'pid.required' => '父级菜单ID不能为空',
      'name.required' => '菜单名称不能为空',
      'path.required' => '菜单路径不能为空',
      'menu_type.required' => '菜单类型不能为空',
      'menu_type.in' => '菜单类型不正确1:PC端 2:移动端',
    ]);
    $res = $this->menuService->addMenu($request->toArray());
    if ($res) {
      return $this->success('新增菜单成功！');
    }
    return $this->error("新增菜单失败！");
  }

  //MARK: 菜单编辑
  public function edit(Request $request)
  {
    $request->validate([
      'id' => 'required|numeric',
      'pid' => 'required|numeric',
      'name' => 'required',
      'path' => 'required',
      'menu_type' => 'required|in:1,2',
    ], [
      'id.required' => '菜单ID不能为空',
      'pid.required' => '父级菜单ID不能为空',
      'name.required' => '菜单名称不能为空',
      'path.required' => '菜单路径不能为空',
      'menu_type.required' => '菜单类型不能为空',
      'menu_type.in' => '菜单类型不正确1:PC端 2:移动端',
    ]);
    $res = $this->menuService->editMenu($request->toArray());
    if ($res) {
      return $this->success('编辑菜单成功！');
    }
    return $this->error("编辑菜单失败！");
  }


  /**
   * @OA\Post(
   *     path="/api/sys/menu/del",
   *     tags={"系统管理"},
   *     summary="删除菜单",
   *     @OA\RequestBody(
   *         @OA\MediaType(
   *             mediaType="application/json",
   *             @OA\Schema(
   *                 required={"id"},
   *                 @OA\Property(property="id", type="int", description="菜单ID"),
   *             )
   *         )
   *     ),
   *     @OA\Response(
   *         response=200,
   *         description="删除菜单"
   *     )
   * )
   */
  public function delete(Request $request)
  {
    $request->validate([
      'id' => 'required|numeric',
    ], [
      'id.required' => '菜单ID不能为空',
    ]);
    $isExist = $this->menuService->menuModel()->where('pid', $request->id)->exists();
    if ($isExist) {
      return $this->error("该菜单下有子菜单，不能删除！");
    }
    $res = $this->menuService->menuModel()->whereId($request->id)->delete();
    if ($res) {
      return $this->success('删除菜单成功！');
    }
    return $this->error("删除菜单失败！");
  }
}
