<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
// Route::get('/register', function(){
//     \App\User::create([
//         'name' => 'admin',
//         'email' => 'test@bb.com',
//         'password' => '123456'
//     ]);
// });



Route::fallback(function () {
  return response()->json([
    'message' => '访问资源不存在!', 'code' => 404
  ], 404);
});
