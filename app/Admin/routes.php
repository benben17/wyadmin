<?php

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

Admin::routes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
], function (Router $router) {
    $router->get('/', 'HomeController@index')->name('admin.home');
    $router->get('/sys/product/select', ProductController::class . '@select');
    $router->get('/sys/company/select', CompanyController::class . '@select');
    $router->get('/sys/role/select', RoleController::class . '@select');
    $router->resource('/business/company', CompanyController::class);
    $router->resource('/business/user', UserController::class);
    $router->resource('/sys/module', ModuleController::class);
    $router->resource('/sys/role', RoleController::class);
    $router->resource('/sys/order', OrderController::class);
    $router->resource('/sys/product', ProductController::class);
    $router->get('/sys/order/test', 'OrderController@test');
});
