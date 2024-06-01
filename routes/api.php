<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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


Route::group(['prefix' => 'dashboard'], function () {
    Route::post('index', '\App\Api\Controllers\Dashboard\DashboardController@index');
    Route::post('tenant', '\App\Api\Controllers\Dashboard\DashboardController@tenantStat');
    Route::post('project', '\App\Api\Controllers\Dashboard\DashboardController@project');
    Route::post('customer', '\App\Api\Controllers\Dashboard\DashboardController@customer');
    Route::post('workOrderData', '\App\Api\Controllers\Dashboard\DashboardController@workOrderData');
});

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

    Route::post('brokerage/list', '\App\Api\Controllers\Business\ChannelBrokerageController@list');
    //政策
    Route::post('policy/list', '\App\Api\Controllers\Business\ChannelController@policyList');
    Route::post('policy/add', '\App\Api\Controllers\Business\ChannelController@storePolicy');
    Route::post('policy/show', '\App\Api\Controllers\Business\ChannelController@showPolicy');
    Route::post('policy/edit', '\App\Api\Controllers\Business\ChannelController@updatePolicy');
    // 获取此渠道带来的客户
    Route::post('customer', '\App\Api\Controllers\Business\ChannelController@getCustomer');
});

// 来电
Route::group(['prefix' => 'business/clue'], function () {
    Route::post('list', '\App\Api\Controllers\Business\CusClueController@index');
    Route::post('add', '\App\Api\Controllers\Business\CusClueController@store');
    Route::post('edit', '\App\Api\Controllers\Business\CusClueController@update');
    Route::post('del', '\App\Api\Controllers\Business\CusClueController@delete');
    Route::post('show', '\App\Api\Controllers\Business\CusClueController@show');
    Route::post('invalid', '\App\Api\Controllers\Business\CusClueController@invalid');
    Route::post('import', '\App\Api\Controllers\Business\CusClueController@import');
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

    // 部门
    Route::post('depart/list', '\App\Api\Controllers\Sys\DepartController@list');
    Route::post('depart/add', '\App\Api\Controllers\Sys\DepartController@store');
    Route::post('depart/edit', '\App\Api\Controllers\Sys\DepartController@edit');
    Route::post('depart/show', '\App\Api\Controllers\Sys\DepartController@show');
    Route::post('depart/move', '\App\Api\Controllers\Sys\DepartController@move');
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
    Route::post('enable', '\App\Api\Controllers\Business\ProjectController@enable');
    Route::post('set', '\App\Api\Controllers\Business\ProjectController@billProjEdit');
});
//公司所使用的字典
Route::group(['prefix' => 'company/dict'], function () {
    Route::post('list', '\App\Api\Controllers\Company\DictController@index');
    Route::post('add', '\App\Api\Controllers\Company\DictController@store');
    Route::post('edit', '\App\Api\Controllers\Company\DictController@update');
    Route::post('enable', '\App\Api\Controllers\Company\DictController@enable');
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
    Route::post('stat', '\App\Api\Controllers\Business\BuildingController@buildingStat');
    Route::post('export', '\App\Api\Controllers\Business\BuildingController@export');
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
    Route::post('distribute', '\App\Api\Controllers\Business\CustomerController@distribute');
    //工商信息
    Route::post('baseinfo', '\App\Api\Controllers\Business\CustomerController@getInfo');
    //编辑公司工商信息
    Route::post('baseinfo/edit', '\App\Api\Controllers\Business\CustomerController@editInfo');
    //客户编号
    Route::post('no/edit', '\App\Api\Controllers\Business\CustomerController@cusNoEdit');
    // 提醒
    Route::post('remind/list', '\App\Api\Controllers\Business\CustomerRemindController@list');
    Route::post('remind/wxlist', '\App\Api\Controllers\Business\CustomerRemindController@wxList');
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
    Route::post('list', '\App\Api\Controllers\Contract\ContractController@index');
    Route::post('add', '\App\Api\Controllers\Contract\ContractController@store');
    Route::post('edit', '\App\Api\Controllers\Contract\ContractController@update');
    Route::post('change', '\App\Api\Controllers\Contract\ContractController@change');
    //合同作废
    Route::post('disuse', '\App\Api\Controllers\Contract\ContractController@disuseContract');
    //合同审核
    Route::post('audit', '\App\Api\Controllers\Contract\ContractController@auditContract');
    Route::post('return', '\App\Api\Controllers\Contract\ContractController@adminReturn');
    Route::post('show', '\App\Api\Controllers\Contract\ContractController@show');
    // 退租
    // Route::post('leaseback', '\App\Api\Controllers\Business\ContractController@leaseBack');
    //列表看板数据 执行合同表头
    Route::post('list/stat', '\App\Api\Controllers\Contract\ContractBillController@getContractStat');

    //合同账单
    Route::post('bill/create', '\App\Api\Controllers\Contract\ContractBillController@createContractBill');
    // 变更合同账单
    Route::post('bill/change', '\App\Api\Controllers\Contract\ContractBillController@contractChangeBill');
    // Route::post('bill/save', '\App\Api\Controllers\Contract\ContractBillController@saveContractBill');
    //合同模版
    Route::post('word', '\App\Api\Controllers\Contract\ContractController@contractWord');
    // 合同附件
    Route::post('uploadAttr', '\App\Api\Controllers\Contract\ContractController@contractAttr');
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

// 活动管理
Route::group(['prefix' => 'activity'], function () {
    Route::post('list', '\App\Api\Controllers\Venue\VenueController@index');
    Route::post('add', '\App\Api\Controllers\Venue\VenueController@store');
    Route::post('show', '\App\Api\Controllers\Venue\VenueController@show');
    Route::post('edit', '\App\Api\Controllers\Venue\VenueController@update');
    Route::post('reg/pay', '\App\Api\Controllers\Venue\ActivityController@activityPay');
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
    Route::post('depart/getAll', '\App\Api\Controllers\Common\PubSelectController@getDeparts');

    Route::post('channel/getPolicyAll', '\App\Api\Controllers\Common\PubSelectController@policyAll');
    Route::post('customer/getAll', '\App\Api\Controllers\Common\PubSelectController@cusList');
    Route::post('venue/getAll', '\App\Api\Controllers\Common\PubSelectController@venueList');

    Route::post('tenant/getAll', '\App\Api\Controllers\Common\PubSelectController@tenantList');
    Route::post('supplier/getAll', '\App\Api\Controllers\Common\PubSelectController@supplierList');
    Route::post('relations/getAll', '\App\Api\Controllers\Common\PubSelectController@relationsList');
    Route::post('equipment/getAll', '\App\Api\Controllers\Common\PubSelectController@equipmentList');
    Route::post('feetype/getAll', '\App\Api\Controllers\Common\PubSelectController@feetypeList');
    Route::post('tenant/bill/detail', '\App\Api\Controllers\Common\PubSelectController@getBillDetail');
    Route::post('tenant/charge/bill', '\App\Api\Controllers\Common\PubSelectController@getChargeBill');
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
    Route::post('/record/del', '\App\Api\Controllers\Operation\MeterController@delMeterRecord');
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
    Route::post('/del', '\App\Api\Controllers\Operation\WorkOrderController@del');
});

// 隐患工单
Route::group(['prefix' => 'operation/yhworkorder'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\YhWorkOrderController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\YhWorkOrderController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\YhWorkOrderController@update');
    Route::post('/show', '\App\Api\Controllers\Operation\YhWorkOrderController@show');
    Route::post('/del', '\App\Api\Controllers\Operation\YhWorkOrderController@del');
    Route::post('/order', '\App\Api\Controllers\Operation\YhWorkOrderController@order');
    Route::post('/process', '\App\Api\Controllers\Operation\YhWorkOrderController@process');
    Route::post('/dispatch', '\App\Api\Controllers\Operation\YhWorkOrderController@orderDispatch');
    Route::post('/addRemark', '\App\Api\Controllers\Operation\YhWorkOrderController@addRemark');
    Route::post('/audit', '\App\Api\Controllers\Operation\YhWorkOrderController@audit');
});

Route::group(['prefix' => 'operation/tenant'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\TenantController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\TenantController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\TenantController@update');
    Route::post('/show', '\App\Api\Controllers\Operation\TenantController@show');

    // Route::post('/sync', '\App\Api\Controllers\Operation\TenantController@tenantSync');
    // 退租租户
    // Route::post('share/add', '\App\Api\Controllers\Operation\TenantShareController@store');
    // Route::post('share/edit', '\App\Api\Controllers\Operation\TenantShareController@update');
    // Route::post('share/show', '\App\Api\Controllers\Operation\TenantShareController@show');
});

Route::group(['prefix' => 'operation/tenant/share'], function () {
    // 分摊租户添加
    Route::post('add', '\App\Api\Controllers\Operation\TenantShareController@store');
    Route::post('list', '\App\Api\Controllers\Operation\TenantShareController@list');
    Route::post('show', '\App\Api\Controllers\Operation\TenantShareController@show');
    Route::post('fee/list', '\App\Api\Controllers\Operation\TenantShareController@feeList');
    Route::post('store', '\App\Api\Controllers\Operation\TenantShareController@tenantShareStore');
});


// 租户退租
Route::group(['prefix' => 'operation/tenant/leaseback'], function () {
    Route::post('/list', '\App\Api\Controllers\Contract\LeasebackController@list');
    Route::post('/add', '\App\Api\Controllers\Contract\LeasebackController@store');
    Route::post('/edit', '\App\Api\Controllers\Contract\LeasebackController@update');
    Route::post('/show', '\App\Api\Controllers\Contract\LeasebackController@show');
    Route::post('/bill', '\App\Api\Controllers\Contract\LeasebackController@leasebackBill');
});

//租户账单
Route::group(['prefix' => 'operation/tenant/bill'], function () {
    Route::post('list', '\App\Api\Controllers\Bill\BillController@list');
    Route::post('create', '\App\Api\Controllers\Bill\BillController@createBill');
    Route::post('show', '\App\Api\Controllers\Bill\BillController@show');
    Route::post('del', '\App\Api\Controllers\Bill\BillController@del');
    Route::post('audit', '\App\Api\Controllers\Bill\BillController@billAudit');
    Route::post('print', '\App\Api\Controllers\Bill\BillController@billPrint');
    Route::post('printView', '\App\Api\Controllers\Bill\BillController@billView');
    Route::post('reminder', '\App\Api\Controllers\Bill\BillController@billReminder');

    //租户账单详细
    Route::post('fee/list', '\App\Api\Controllers\Bill\BillDetailController@list');
    Route::post('fee/show', '\App\Api\Controllers\Bill\BillDetailController@show');
    Route::post('fee/verify', '\App\Api\Controllers\Bill\BillDetailController@verify');
    Route::post('fee/add', '\App\Api\Controllers\Bill\BillDetailController@store');
    Route::post('fee/edit', '\App\Api\Controllers\Bill\BillDetailController@edit');
    Route::post('fee/del', '\App\Api\Controllers\Bill\BillDetailController@del');

    // 费用自动同步
    Route::get('sync', '\App\Api\Controllers\Bill\BillSyncController@syncContractBill');

    // 生成word 版账单
    Route::post('toWord', '\App\Api\Controllers\Bill\BillController@billToWord');
});

// 租户押金
Route::group(['prefix' => 'operation/tenant'], function () {
    // 押金
    Route::post('deposit/list', '\App\Api\Controllers\Bill\DepositController@list');
    Route::post('deposit/show', '\App\Api\Controllers\Bill\DepositController@show');
    Route::post('deposit/add', '\App\Api\Controllers\Bill\DepositController@store');
    Route::post('deposit/edit', '\App\Api\Controllers\Bill\DepositController@edit');
    Route::post('deposit/del', '\App\Api\Controllers\Bill\DepositController@del');
    Route::post('deposit/tocharge', '\App\Api\Controllers\Bill\DepositController@toCharge');
    Route::post('deposit/receive', '\App\Api\Controllers\Bill\DepositController@receive');
    Route::post('deposit/refund', '\App\Api\Controllers\Bill\DepositController@refund');

    Route::post('deposit/record/list', '\App\Api\Controllers\Bill\DepositController@recordList');
    // 押金收款记录删除
    Route::post('deposit/record/del', '\App\Api\Controllers\Bill\DepositController@receiveRecordDel');
});
//发票
Route::group(['prefix' => 'operation/tenant/invoice'], function () {
    Route::post('list', '\App\Api\Controllers\Bill\InvoiceController@list');
    Route::post('add', '\App\Api\Controllers\Bill\InvoiceController@store');
    Route::post('edit', '\App\Api\Controllers\Bill\InvoiceController@edit');
    Route::post('show', '\App\Api\Controllers\Bill\InvoiceController@show');
    Route::post('cancel', '\App\Api\Controllers\Bill\InvoiceController@cancel');
    Route::post('title', '\App\Api\Controllers\Bill\InvoiceController@invoiceByTenant');
});

// 设备设施
Route::group(['prefix' => 'operation/equipment'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\EquipmentController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\EquipmentController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\EquipmentController@update');
    Route::post('/show', '\App\Api\Controllers\Operation\EquipmentController@show');
    Route::post('/del', '\App\Api\Controllers\Operation\EquipmentController@delete');
    Route::post('/enable', '\App\Api\Controllers\Operation\EquipmentController@enable');
    // plan 
    Route::post('/plan/show', '\App\Api\Controllers\Operation\EquipmentPlanController@show');
    Route::post('/plan/list', '\App\Api\Controllers\Operation\EquipmentPlanController@list');
    Route::post('/plan/del', '\App\Api\Controllers\Operation\EquipmentPlanController@delete');
    Route::post('/plan/add', '\App\Api\Controllers\Operation\EquipmentPlanController@store');
    Route::post('/plan/edit', '\App\Api\Controllers\Operation\EquipmentPlanController@edit');
    Route::post('/plan/generate', '\App\Api\Controllers\Operation\EquipmentPlanController@planGenerate');

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
    Route::post('/del', '\App\Api\Controllers\Operation\InspectionController@delete');

    Route::post('/record/list', '\App\Api\Controllers\Operation\InspectionController@recordList');
    Route::post('/record/add', '\App\Api\Controllers\Operation\InspectionController@recordStore');
    Route::post('/record/edit', '\App\Api\Controllers\Operation\InspectionController@recordUpdate');
    Route::post('/record/show', '\App\Api\Controllers\Operation\InspectionController@recordShow');
    Route::post('/record/del', '\App\Api\Controllers\Operation\InspectionController@recordDelete');
    Route::post('/record/audit', '\App\Api\Controllers\Operation\InspectionController@auditMeterRecord');
});

// 车位管理
Route::group(['prefix' => 'operation/parking'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\ParkingController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\ParkingController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\ParkingController@update');
    Route::post('/show', '\App\Api\Controllers\Operation\ParkingController@show');
    Route::post('/del', '\App\Api\Controllers\Operation\ParkingController@delete');
});
// 供应商管理
Route::group(['prefix' => 'operation/supplier'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\SupplierController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\SupplierController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\SupplierController@update');
    Route::post('/del', '\App\Api\Controllers\Operation\SupplierController@delete');
    Route::post('/show', '\App\Api\Controllers\Operation\SupplierController@show');
});
// 公共关系
Route::group(['prefix' => 'operation/relation'], function () {
    Route::post('/list', '\App\Api\Controllers\Operation\PubRelationController@list');
    Route::post('/add', '\App\Api\Controllers\Operation\PubRelationController@store');
    Route::post('/edit', '\App\Api\Controllers\Operation\PubRelationController@update');
    Route::post('/del', '\App\Api\Controllers\Operation\PubRelationController@delete');
    Route::post('/show', '\App\Api\Controllers\Operation\PubRelationController@show');
});

// 预充值管理
Route::group(['prefix' => 'operation/charge'], function () {
    Route::post('/list', '\App\Api\Controllers\Bill\ChargeController@list');
    Route::post('/add', '\App\Api\Controllers\Bill\ChargeController@store');
    Route::post('/edit', '\App\Api\Controllers\Bill\ChargeController@edit');
    Route::post('/cancel', '\App\Api\Controllers\Bill\ChargeController@cancel');
    Route::post('/delete', '\App\Api\Controllers\Bill\ChargeController@deleteCharge');

    Route::post('/show', '\App\Api\Controllers\Bill\ChargeController@show');
    // 核销 多条应收
    Route::post('/writeOff', '\App\Api\Controllers\Bill\ChargeController@chargeWriteOff');
    // 核销单条
    // Route::post('/writeOffOne', '\App\Api\Controllers\Bill\ChargeController@chargeWriteOffOne');
    Route::post('/record/list', '\App\Api\Controllers\Bill\ChargeController@recordList');
    Route::post('/record/delete', '\App\Api\Controllers\Bill\ChargeController@deleteRecord');
    Route::post('/refund', '\App\Api\Controllers\Bill\ChargeController@chargeRefund');
});


// 费用统计
Route::group(['prefix' => 'operation/stat'], function () {
    Route::post('bill', '\App\Api\Controllers\Stat\BillStatController@billStat');
    Route::post('/bill/month/report', '\App\Api\Controllers\Stat\BillStatController@monthlyStat');
    Route::post('/bill/year/report', '\App\Api\Controllers\Stat\BillStatController@bill_year_stat');
    Route::post('/charge/month/report', '\App\Api\Controllers\Stat\BillStatController@chargeStat');
});




Route::group(['prefix' => 'sys/excel'], function () {
    Route::post('import', '\App\Api\Controllers\Excel\ExcelController@test');
});


// 微信
Route::group(['prefix' => 'business/wx'], function () {
    Route::post('room/list', '\App\Api\Controllers\Weixin\WxRoomController@index');
    Route::post('room/show', '\App\Api\Controllers\Weixin\WxRoomController@show');
    Route::post('rooms', '\App\Api\Controllers\Weixin\WxRoomController@rooms');
    Route::post('project/list', '\App\Api\Controllers\Weixin\WxRoomController@wxGetProj');
});

// 微信 登录
Route::group(['prefix' => 'wx'], function () {
    Route::get('/weixin', '\App\Api\Controllers\Weixin\WeixinController@weixin')->name('weixin');
    Route::get('/weixin/callback', '\App\Api\Controllers\Weixin\WeixinController@weixinCallback');

    // Route::post('/user/bind', '\App\Api\Controllers\Weixin\WeiXinController@bindWx');
    // Route::post('/user/unbind', '\App\Api\Controllers\Weixin\WeiXinController@unBindWx');
    Route::post('/auth/login', '\App\Api\Controllers\Weixin\WeixinController@wxAppAuth');

    // 微信支付
    Route::post('/activity/reg/pay', '\App\Api\Controllers\Venue\ActivityController@activityPay');
    Route::post('/pay/notify_url', '\App\Api\Controllers\Venue\ActivityController@WxPayNotify');
});


Route::group(['prefix' => 'wxapp/customer'], function () {
    Route::post('/stat', '\App\Api\Controllers\Weixin\WxStatController@customerStat');
    Route::post('/list', '\App\Api\Controllers\Weixin\WxStatController@list');
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
