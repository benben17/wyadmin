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
   *     summary="获取菜单列表",
   *     @OA\Response(
   *         response=200,
   *         description="获取菜单列表"
   *     )
   * )
   */
  public function index(Request $request)

  {
    $request->validate([
      'menu_type' => 'required|gt:0',
    ], [
      'menu_type.required' => '菜单类型不能为空',
      'menu_type.gt' => '菜单类型必须大于0',
    ]);
    $menus = $this->menuService->getMenus($request->menu_type);
    return $this->success($menus);
  }
}
