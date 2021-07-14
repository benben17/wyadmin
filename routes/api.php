<?php

use Illuminate\Http\Request;
// use App\Api\Models\Channel\Channel as test;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/login', 'AuthUserController@login');

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

// Route::group([ 'middleware' => ['auth:api']], function () {

// });
Route::group(['prefix' => 'auth'], function () {
    Route::post('login', '\App\Api\Controllers\Auth\AuthController@login');
    Route::post('logout', '\App\Api\Controllers\Auth\AuthController@logout');
    Route::post('refresh', '\App\Api\Controllers\Auth\AuthController@refresh');
    Route::post('userinfo', '\App\Api\Controllers\Auth\AuthController@userinfo');
    Route::post('editpwd', '\App\Api\Controllers\Auth\AuthController@updatepwd');
    //编辑个人用户信息
    Route::post('userinfo/edit', '\App\Api\Controllers\Auth\AuthController@editUserInfo');
});

Route::group(['prefix' => 'business/channel'], function () {
    Route::post('list', '\App\Api\Controllers\Business\ChannelController@index');
    Route::post('add', '\App\Api\Controllers\Business\ChannelController@store');
    Route::post('edit', '\App\Api\Controllers\Business\ChannelController@update');
    Route::post('del', '\App\Api\Controllers\Business\ChannelController@deleteChannel');
    Route::post('show', '\App\Api\Controllers\Business\ChannelController@show');
    Route::post('enable', '\App\Api\Controllers\Business\ChannelController@enable');

    Route::post('brokerage/list', '\App\Api\Controllers\Business\ChannelController@brokerageList');
    //政策
    Route::post('policy/list', '\App\Api\Controllers\Business\ChannelController@policyList');
    Route::post('policy/add', '\App\Api\Controllers\Business\ChannelController@storePolicy');
    Route::post('policy/show', '\App\Api\Controllers\Business\ChannelController@showPolicy');
    Route::post('policy/edit', '\App\Api\Controllers\Business\ChannelController@updatePolicy');
    // 获取此渠道带来的客户
    Route::post('customer', '\App\Api\Controllers\Business\ChannelController@getCustomer');
});
//系统类路由
Route::group(['prefix' => 'sys'], function () {
    Route::post('company/present', '\App\Api\Controllers\Sys\CompanyController@present'); //当前用户的公司信息
    Route::post('order/list', '\App\Api\Controllers\Sys\OrderController@index'); //当前用户的公司模块
    Route::post('product/list', '\App\Api\Controllers\Sys\ProductController@index'); //当前产品信息
    Route::post('user/list', '\App\Api\Controllers\Sys\UserController@index'); //用户列表
    Route::post('user/add', '\App\Api\Controllers\Sys\UserController@store'); //添加用户
    Route::post('user/edit', '\App\Api\Controllers\Sys\UserController@update'); //编辑用户
    Route::post('user/show', '\App\Api\Controllers\Sys\UserController@show'); //查看用户信息
    Route::post('user/enable', '\App\Api\Controllers\Sys\UserController@enable'); //用户禁用启用
    //用户变量信息保存
    Route::post('user/profile', '\App\Api\Controllers\Sys\UserController@userProfile');
    Route::post('usergroup/list', '\App\Api\Controllers\Sys\UserGroupController@index'); //用户列表
    Route::post('usergroup/add', '\App\Api\Controllers\Sys\UserGroupController@store'); //添加用户
    Route::post('usergroup/edit', '\App\Api\Controllers\Sys\UserGroupController@update'); //编辑用户
    Route::post('usergroup/show', '\App\Api\Controllers\Sys\UserGroupController@show'); //查看用户信息
    // 角色
    Route::post('user/role/list', '\App\Api\Controllers\Sys\UserRoleController@list');
    Route::post('user/role/add', '\App\Api\Controllers\Sys\UserRoleController@store');
    Route::post('user/role/edit', '\App\Api\Controllers\Sys\UserRoleController@update');
    Route::post('user/role/show', '\App\Api\Controllers\Sys\UserRoleController@show');

    /** 公司变量信息 */
    Route::post('company/variable/show', '\App\Api\Controllers\Sys\CompanyController@showVariable');
    Route::post('company/variable/edit', '\App\Api\Controllers\Sys\CompanyController@editVariable');
});

Route::group(['prefix' => 'common'], function () {
    Route::post('role/list', '\App\Api\Controllers\Common\RoleController@list'); //角色列表
    Route::post('dict', '\App\Api\Controllers\Common\PubController@dict'); //单个字典
    Route::post('muliDict', '\App\Api\Controllers\Common\PubController@muliDict'); //多个字典查询
    Route::post('province', '\App\Api\Controllers\Common\PubController@province'); //省份
    Route::post('city', '\App\Api\Controllers\Common\PubController@city'); //城市
    Route::post('district', '\App\Api\Controllers\Common\PubController@district'); //区
    Route::Post('upload/img', '\App\Api\Controllers\Common\UploadController@uploadImg'); //上传图片
    //上传文件
    Route::Post('upload/file', '\App\Api\Controllers\Common\UploadController@uploadFile');
    Route::Post('upload/imgs', '\App\Api\Controllers\Common\UploadController@uploadImgs');
    Route::Post('upload/contract', '\App\Api\Controllers\Common\UploadController@uploadContract');
    //编辑新增联系人
    Route::Post('contact/save', '\App\Api\Controllers\Common\ContactController@save');
    //删除联系人
    Route::Post('contact/del', '\App\Api\Controllers\Common\ContactController@delete');
    Route::post('contact/list', '\App\Api\Controllers\Common\ContactController@list');
    //维护列表
    Route::Post('maintain/list', '\App\Api\Controllers\Common\MaintainController@list');
    //编辑新增维护
    Route::Post('maintain/save', '\App\Api\Controllers\Common\MaintainController@store');
    //删除维护记录
    Route::Post('maintain/del', '\App\Api\Controllers\Common\MaintainController@delete');
    Route::Post('maintain/show', '\App\Api\Controllers\Common\MaintainController@show');
    //备注列表
    Route::Post('remark/list', '\App\Api\Controllers\Common\RemarkController@list');
    //编辑新增维护
    Route::Post('remark/save', '\App\Api\Controllers\Common\RemarkController@save');
    //删除维护记录
    Route::Post('remark/del', '\App\Api\Controllers\Common\RemarkController@delete');

    // 附件
    Route::Post('attach/add', '\App\Api\Controllers\Common\AttachmentController@store');
    Route::Post('attach/del', '\App\Api\Controllers\Common\AttachmentController@delete');
    Route::Post('attach/list', '\App\Api\Controllers\Common\AttachmentController@list');
});

//项目路由
Route::group(['prefix' => 'business/project'], function () {
    Route::post('list', '\App\Api\Controllers\Business\ProjectController@index');
    Route::post('add', '\App\Api\Controllers\Business\ProjectController@store');
    Route::post('edit', '\App\Api\Controllers\Business\ProjectController@update');
    Route::post('del', '\App\Api\Controllers\Business\ProjectController@delete');
    Route::post('show', '\App\Api\Controllers\Business\ProjectController@show');
});
//公司所使用的字典
Route::group(['prefix' => 'company/dict'], function () {
    Route::post('list', '\App\Api\Controllers\Company\DictController@index');
    Route::post('add', '\App\Api\Controllers\Company\DictController@store');
    Route::post('edit', '\App\Api\Controllers\Company\DictController@update');
    Route::post('del', '\App\Api\Controllers\Company\DictController@delete');
    //字典类型
    Route::post('type', '\App\Api\Controllers\Company\DictController@dictType');
});

//建筑管理
Route::group(['prefix' => 'business/building'], function () {
    Route::post('list', '\App\Api\Controllers\Business\BuildingController@index');
    Route::post('add', '\App\Api\Controllers\Business\BuildingController@store');
    Route::post('edit', '\App\Api\Controllers\Business\BuildingController@update');
    Route::post('del', '\App\Api\Controllers\Business\BuildingController@delete');
    Route::post('show', '\App\Api\Controllers\Business\BuildingController@show');
    Route::post('floor/del', '\App\Api\Controllers\Business\BuildingController@delFloor');

    //房源route
    Route::post('room/list', '\App\Api\Controllers\Business\BuildingRoomController@index');
    Route::post('room/add', '\App\Api\Controllers\Business\BuildingRoomController@store');
    Route::post('room/enable', '\App\Api\Controllers\Business\BuildingRoomController@enable');
    Route::post('room/edit', '\App\Api\Controllers\Business\BuildingRoomController@update');
    Route::post('room/show', '\App\Api\Controllers\Business\BuildingRoomController@show');
});

// 工位管理
Route::group(['prefix' => 'business/building/station'], function () {
    Route::post('list', '\App\Api\Controllers\Business\StationController@index');
    Route::post('show', '\App\Api\Controllers\Business\StationController@show');
    Route::post('add', '\App\Api\Controllers\Business\StationController@store');
    Route::post('edit', '\App\Api\Controllers\Business\StationController@update');
    Route::post('enable', '\App\Api\Controllers\Business\StationController@enable');
});

//公司客户路由
Route::group(['prefix' => 'business/customer'], function () {
    Route::post('list', '\App\Api\Controllers\Business\CustomerController@index');
    Route::post('add', '\App\Api\Controllers\Business\CustomerController@store');
    Route::post('edit', '\App\Api\Controllers\Business\CustomerController@update');
    Route::post('del', '\App\Api\Controllers\Business\CustomerController@delete');
    Route::post('show', '\App\Api\Controllers\Business\CustomerController@show');
    //工商信息
    Route::post('baseinfo', '\App\Api\Controllers\Business\CustomerController@getInfo');
    //编辑公司工商信息
    Route::post('baseinfo/edit', '\App\Api\Controllers\Business\CustomerController@editInfo');
    //客户编号
    Route::post('no/edit', '\App\Api\Controllers\Business\CustomerController@cusNoEdit');
    // 提醒
    Route::post('remind/list', '\App\Api\Controllers\Business\CustomerRemindController@list');
    Route::post('remind/add', '\App\Api\Controllers\Business\CustomerRemindController@store');
    Route::post('remind/edit', '\App\Api\Controllers\Business\CustomerRemindController@update');
    // 客户跟进
    Route::post('follow/list', '\App\Api\Controllers\Business\CusFollowController@list');
    Route::post('follow/add', '\App\Api\Controllers\Business\CusFollowController@store');
    Route::post('follow/edit', '\App\Api\Controllers\Business\CusFollowController@update');
    Route::post('follow/show', '\App\Api\Controllers\Business\CusFollowController@show');
});
// 招商合同管理
Route::group(['prefix' => 'business/contract'], function () {
    Route::post('list', '\App\Api\Controllers\Business\ContractController@index');
    Route::post('add', '\App\Api\Controllers\Business\ContractController@store');
    Route::post('edit', '\App\Api\Controllers\Business\ContractController@update');

    //合同作废
    Route::post('disuse', '\App\Api\Controllers\Business\ContractController@disuseContract');
    //合同审核
    Route::post('audit', '\App\Api\Controllers\Business\ContractController@auditContract');

    // 退租
    // Route::post('leaseback', '\App\Api\Controllers\Business\ContractController@leaseBack');
    //列表看板数据
    Route::post('list/stat', '\App\Api\Controllers\Business\ContractController@getContractStat');

    Route::post('show', '\App\Api\Controllers\Business\ContractController@show');
    //合同账单
    Route::post('bill/create', '\App\Api\Controllers\Business\ContractController@contractBill');
    Route::post('bill/save', '\App\Api\Controllers\Business\ContractController@saveContractBill');
    //合同模版
    Route::post('word', '\App\Api\Controllers\Business\ContractController@contractWord');
    // 合同附件
    Route::post('uploadAttr', '\App\Api\Controllers\Business\ContractController@contractAttr');
});

Route::group(['prefix' => 'sysconfig'], function () {
    Route::post('template/parm', '\App\Api\Controllers\Company\TemplateController@templateParm');
    Route::post('template/list', '\App\Api\Controllers\Company\TemplateController@list');
    Route::post('template/add', '\App\Api\Controllers\Company\TemplateController@store');
    Route::post('template/del', '\App\Api\Controllers\Company\TemplateController@delete');
    Route::post('template/edit', '\App\Api\Controllers\Company\TemplateController@update');
});
//场馆管理
Route::group(['prefix' => 'venue'], function () {
    Route::post('list', '\App\Api\Controllers\Venue\VenueController@index');
    Route::post('add', '\App\Api\Controllers\Venue\VenueController@store');
    Route::post('show', '\App\Api\Controllers\Venue\VenueController@show');
    Route::post('edit', '\App\Api\Controllers\Venue\VenueController@update');
    Route::post('enable', '\App\Api\Controllers\Venue\VenueController@enable');
    // 场馆预定
    Route::post('book/list', '\App\Api\Controllers\Venue\VenueBookController@index');
    Route::post('book/add', '\App\Api\Controllers\Venue\VenueBookController@store');
    Route::post('book/edit', '\App\Api\Controllers\Venue\VenueBookController@update');
    Route::post('book/show', '\App\Api\Controllers\Venue\VenueBookController@show');
    Route::post('book/cancel', '\App\Api\Controllers\Venue\VenueBookController@cancelBook');
    Route::post('book/settle', '\App\Api\Controllers\Venue\VenueBookController@settleVenue');
    Route::post('book/bill', '\App\Api\Controllers\Venue\VenueBookController@settleBill');
    Route::post('book/stat', '\App\Api\Controllers\Venue\VenueBookController@settleStat');
});


//消息接收
Route::group(['prefix' => 'common/msg'], function () {
    Route::post('list', '\App\Api\Controllers\Common\MessageController@list');
    Route::post('setread', '\App\Api\Controllers\Common\MessageController@setRead');
    Route::post('send', '\App\Api\Controllers\Common\MessageController@store');
    Route::post('del', '\App\Api\Controllers\Common\MessageController@delete');
    Route::post('revoke', '\App\Api\Controllers\Common\MessageController@revoke');
    //发送列表
    Route::post('send/list', '\App\Api\Controllers\Common\MessageController@sendList');

    Route::post('show', '\App\Api\Controllers\Common\MessageController@msgShow');
    Route::post('count', '\App\Api\Controllers\Common\MessageController@msgCount');
});

// 公共选择方法
Route::group(['prefix' => 'pub/'], function () {
    Route::post('proj/getAll', '\App\Api\Controllers\Common\PubSelectController@projAll');
    Route::post('building/getAll', '\App\Api\Controllers\Common\PubSelectController@buildingAll');
    Route::post('room/getAll', '\App\Api\Controllers\Common\PubSelectController@roomAll');
    Route::post('dict/getAll', '\App\Api\Controllers\Common\PubSelectController@dictAll');
    Route::post('channel/getAll', '\App\Api\Controllers\Common\PubSelectController@channelAll');
    Route::post('select/space', '\App\Api\Controllers\Common\PubSelectController@selectSpace');
    // Route::post('select/room','\App\Api\Controllers\Common\PubSelectController@selectRoom');
    Route::post('channel/getPolicyAll', '\App\Api\Controllers\Common\PubSelectController@policyAll');
    Route::post('customer/getAll', '\App\Api\Controllers\Common\PubSelectController@cusList');
    Route::post('venue/getAll', '\App\Api\Controllers\Common\PubSelectController@venueList');

    Route::post('tenant/getAll', '\App\Api\Controllers\Common\PubSelectController@tenantList');
    Route::post('supplier/getAll', '\App\Api\Controllers\Common\PubSelectController@supplierList');
    Route::post('relations/getAll', '\App\Api\Controllers\Common\PubSelectController@relationsList');
    Route::post('equipment/getAll', '\App\Api\Controllers\Common\PubSelectController@equipmentList');
    Route::post('feetype/getAll', '\App\Api\Controllers\Common\PubSelectController@feetypeList');
});

// 公司收款账户
Route::group(['prefix' => 'company'], function () {
    Route::post('bankaccount/list', '\App\Api\Controllers\Company\BankAccountController@list');
    Route::post('bankaccount/add', '\App\Api\Controllers\Company\BankAccountController@store');
    Route::post('bankaccount/edit', '\App\Api\Controllers\Company\BankAccountController@update');
    Route::post('bankaccount/show', '\App\Api\Controllers\Company\BankAccountController@show');
    Route::post('bankaccount/enable', '\App\Api\Controllers\Company\BankAccountController@enable');

    // 费用类型
    Route::post('fee/list', '\App\Api\Controllers\Company\FeeTypeController@list');
    Route::post('fee/add', '\App\Api\Controllers\Company\FeeTypeController@save');
    Route::post('fee/edit', '\App\Api\Controllers\Company\FeeTypeController@save');
    Route::post('fee/enable', '\App\Api\Controllers\Company\FeeTypeController@enable');
});

Route::group(['prefix' => 'business/stat'], function () {
    Route::post('/dashboard', '\App\Api\Controllers\Business\StatController@dashboard');
    Route::post('/customerstat', '\App\Api\Controllers\Business\StatController@getCustomerStat');
    Route::post('/contract', '\App\Api\Controllers\Business\StatController@getContractStat');
    Route::post('/staffkpi', '\App\Api\Controllers\Business\StatController@getStaffKpi');
    Route::post('/forecast/income', '\App\Api\Controllers\Business\StatController@incomeForecast');
    Route::post('/month/receive', '\App\Api\Controllers\Business\StatController@getMonthReceive');
    //room stat
    Route::post('/room/stat', '\App\Api\Controllers\Business\RoomStatController@roomStat');
});

//运营能耗
Route::group(['prefix' => 'operation/meter'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\MeterController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\MeterController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\MeterController@update');
    Route::post('/show', '\App\Api\Controllers\Operation\MeterController@show');
    Route::post('/qrcode', '\App\Api\Controllers\Operation\MeterController@qrcode');

    //抄表
    Route::post('/record/add', '\App\Api\Controllers\Operation\MeterController@addMeterRecord');
    Route::post('/record/edit', '\App\Api\Controllers\Operation\MeterController@editMeterRecord');
    Route::post('/record/show', '\App\Api\Controllers\Operation\MeterController@showMeterRecord');
    Route::post('/record/list', '\App\Api\Controllers\Operation\MeterController@listMeterRecord');
    Route::post('/record/audit', '\App\Api\Controllers\Operation\MeterController@auditMeterRecord');
});
Route::group(['prefix' => 'operation/workorder'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\WorkOrderController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\WorkOrderController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\WorkOrderController@update');
    Route::post('/show', '\App\Api\Controllers\Operation\WorkOrderController@show');
    Route::post('/cancel', '\App\Api\Controllers\Operation\WorkOrderController@cancel');
    Route::post('/order', '\App\Api\Controllers\Operation\WorkOrderController@order');
    Route::post('/process', '\App\Api\Controllers\Operation\WorkOrderController@process');
    Route::post('/close', '\App\Api\Controllers\Operation\WorkOrderController@close');
});

Route::group(['prefix' => 'operation/tenant'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\TenantController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\TenantController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\TenantController@update');
    Route::post('/show', '\App\Api\Controllers\Operation\TenantController@show');

    Route::post('/sync', '\App\Api\Controllers\Operation\TenantController@tenantSync');
    // 退租租户
    Route::post('/share/save', '\App\Api\Controllers\Operation\TenantController@shareStore');
    Route::post('/share/unlink', '\App\Api\Controllers\Operation\TenantController@unlinkShare');
});

Route::group(['prefix' => 'operation/tenant/leaseback'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\LeaseBackController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\LeasebackController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\LeaseBackController@update');
    Route::post('/show', '\App\Api\Controllers\Operation\LeaseBackController@show');
});

//租户账单
Route::group(['prefix' => 'operation/tenant/bill'], function () {
    Route::post('list', '\App\Api\Controllers\Bill\BillController@list');
    //租户账单详细
    Route::post('fee/list', '\App\Api\Controllers\Bill\BillDetailController@list');
    Route::post('fee/show', '\App\Api\Controllers\Bill\BillDetailController@show');
});

// 设备设施
Route::group(['prefix' => 'operation/equipment'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\EquipmentController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\EquipmentController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\EquipmentController@update');
    Route::post('/show', '\App\Api\Controllers\Operation\EquipmentController@show');

    Route::post('/maintain/list', '\App\Api\Controllers\Operation\EquipmentController@maintainList');
    Route::post('/maintain/add', '\App\Api\Controllers\Operation\EquipmentController@maintainStore');
    Route::post('/maintain/edit', '\App\Api\Controllers\Operation\EquipmentController@maintainUpdate');
    Route::post('/maintain/show', '\App\Api\Controllers\Operation\EquipmentController@maintainShow');
    Route::post('/maintain/del', '\App\Api\Controllers\Operation\EquipmentController@maintainDelete');
});
// 巡检
Route::group(['prefix' => 'operation/inspection'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\InspectionController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\InspectionController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\InspectionController@update');
    Route::post('/show', '\App\Api\Controllers\Operation\InspectionController@show');

    Route::post('/record/list', '\App\Api\Controllers\Operation\InspectionController@recordList');
    Route::post('/record/add', '\App\Api\Controllers\Operation\InspectionController@recordStore');
    Route::post('/record/edit', '\App\Api\Controllers\Operation\InspectionController@recordUpdate');
    Route::post('/record/show', '\App\Api\Controllers\Operation\InspectionController@recordShow');
    Route::post('/record/del', '\App\Api\Controllers\Operation\InspectionController@recordDelete');
});

// 车位管理
Route::group(['prefix' => 'operation/parking'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\ParkingController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\ParkingController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\ParkingController@update');
    Route::post('/show', '\App\Api\Controllers\Operation\ParkingController@show');
});
// 供应商管理
Route::group(['prefix' => 'operation/supplier'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\SupplierController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\SupplierController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\SupplierController@update');
    Route::post('/show', '\App\Api\Controllers\Operation\SupplierController@show');
});
// 预充值管理
Route::group(['prefix' => 'operation/charge'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\ChargeController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\ChargeController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\ChargeController@edit');
    Route::post('/del', '\App\Api\Controllers\Operation\ChargeController@del');
    Route::post('/show', '\App\Api\Controllers\Operation\ChargeController@show');
    Route::post('/audit', '\App\Api\Controllers\Operation\ChargeController@audit');
});

Route::group(['prefix' => 'wx'], function () {
    Route::get('/weixin', '\App\Api\Controllers\Weixin\WeiXinController@redirectToProvider');
    Route::get('/callback', '\App\Api\Controllers\Weixin\WeiXinController@handleProviderCallback');
    Route::post('/user/bind', '\App\Api\Controllers\Weixin\WeiXinController@bindWx');
    Route::post('/user/unbind', '\App\Api\Controllers\Weixin\WeiXinController@unBindWx');
    Route::post('/auth/login', '\App\Api\Controllers\Weixin\WeiXinController@wxAppAuth');
});




// Route::get('/test', function() {

// $result= config('paystatus')[1];
// return $result;
// //日志
// //  $data["customer_id"] = 1;
// //  $data["company_id"] = 公司id
// //  $data["content"] = "【编辑】客户信息";
// //  $data["c_uid"] = 1;
// //  $data["c_username"] = "薛伟";
// //  $result =CreateCustomerLog($data);
// // return response()->json($result);

//  //$data = \App\Models\Company::withCount('user')->get();

// 	// var_dump(getCompaCreateCustomerLog($dat)nyId(1));
//  //    exit;
//    // DB::enableQueryLog();
//    //$data = \App\Models\User::with('customer')->paginate(1);
//    // $data =\App\Models\User::find(1)->customer()->get();
//         //测试页面
//     // var_dump($data->total());
//    //         var_dump($data);
//       return response()->json($data);
//      // $data = \App\Api\Models\Channel::where("id",">",10)->find(21);
//     //return response()->json(DB::getQueryLog());
// });
Route::fallback(function () {
    return response()->json([
        'message' => '访问资源不存在!', 'code' => 404
    ], 404);
});
