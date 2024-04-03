<?php

namespace App\Admin\Controllers;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use App\Models\Company;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;
use App\Api\Services\Company\VariableService;
use Encore\Admin\Controllers\AdminController;

class CompanyController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = '客户信息';


    // /**
    //  * Index interface.
    //  *
    //  * @param Content $content
    //  *
    //  * @return Content
    //  */
    // public function index(Content $content)
    // {
    //     return $content
    //         ->title($this->title())
    //         // ->description($this->description['index'] ?? trans('admin.list'))
    //         ->body($this->grid());
    // }
    /**
     * Make a grid builder.
     *
     * @return Grid
     */

    protected function grid()
    {
        $grid = new Grid(new Company);
        // $grid->filter(function($filter){

        //     // 去掉默认的id过滤器
        //     // $filter->disableIdFilter();
        //     $filter->distpicker('province_id', 'city_id', 'district_id', '地域选择');
        //     // 在这里添加字段过滤器
        //     $filter->like('name', '客户名称');

        // });
        $grid->id('ID');
        $grid->column('name', '客户名称');
        $grid->column('user', '用户数')->display(function ($user) {
            return count($user);
        });
        $grid->column('product.name', '产品名称');
        $grid->column('proj_count', '项目数');
        $grid->column('expire_date', '到期时间')->date('Y-m-d');
        $grid->column('contact_per', '联系人');
        $grid->column('contact_per', '联系人');
        $grid->column('tel', '联系电话');
        $grid->column('province.name', '省份');
        $grid->column('city.name', '城市');
        $grid->column('district.name', '区');
        $grid->column('address', '联系地址');
        $grid->actions(function ($actions) {
            // 去掉删除
            $actions->disableDelete();

            // 去掉编辑
            //   $actions->disableEdit();

            // 去掉查看
            // $actions->disableView();
        });
        $grid->tools(function ($tools) {
            $tools->batch(function ($batch) {
                $batch->disableDelete();
            });
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed   $id
     * @return Show
     */
    protected function detail($id)
    {
        $company = Company::findOrFail($id);

        $show = new Show($company);
        $show->field('name', '客户名称')->setWidth(8, 2, 6);
        $show->field('credit_code', '营业执照号')->setWidth(8, 2, 6);
        $show->field('proj_count', '项目数')->setWidth(8, 2, 6);
        $show->field('contact_per', '联系人')->setWidth(8, 2, 6);
        $show->field('tel', '联系电话')->setWidth(8, 2, 6);
        $show->field('province', '所在地区')->as(function () use ($company) {
            return $company->province->name . $company->city->name . $company->district->name;
        })->setWidth(8, 2, 6);
        $show->field('address', '联系地址')->setWidth(8, 2, 6);
        $show->field('remark', '备注')->setWidth(8, 2, 6);
        $show->field('logo', '企业logo')->image()->setWidth(8, 2, 6);
        // $show->field('config', '配置信息')->json()->setWidth(4, 1, 12);

        $show->order('订单', function ($order) {
            // $order->resource('/admin/orders');
            $order->order_no('订单号');
            $order->column('status', '状态')->display(function ($value) {
                $status = config('paystatus')[$value];
                return "<span style='color:blue'>$status</span>";
            });
            $order->column('company_id', '客户名称')->display(function ($value) {
                return Company::find($value)->name;
            });
            // $order->column('product_id', '产品名称')->display(function ($value) {
            //     return \App\Models\Product::where('id', $value)->first()->en_name;
            //     Log::error($value);
            // });
            $order->column('name', '产品名称');
            $order->column('month', '时长');
            $order->column('price', '单价');
            $order->amount('总金额');
            $order->created_at('下单时间');
            $order->disableCreateButton();
            $order->disablePagination();
            $order->disableExport();
            $order->disableRowSelector();
            $order->disableActions();
            $order->disableFilter();
        });



        $show->panel()->tools(function ($tools) {
            $tools->disableDelete();
        });


        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Company);
        $form->column(2 / 3, function ($form) {
            $form->text('name', '客户名称')
                ->rules('required|min:2', ['min'   => '客户名称不能少于2个字符!']);
            $form->text('credit_code', '营业执照号');
            $form->select('product_id', '产品名称')->options('/admin/sys/product/select')
                ->rules('required', ['required'   => '必须选择一个产品!']);
            $form->text('proj_count', '项目数')
                ->rules('required|int|min:1', ['min' => '不能小于1']);
            $form->text('contact_per', '联系人')
                ->rules('required|min:2', ['min'   => '联系人不能少于2个字符!']);
            // $form->image('logo')->move('public/upload/logo');
            $form->text('tel', '联系电话')->rules('required|regex:/^1[0-9]{10}$/', ['regex' => '手机号不正确!']);
            $form->distpicker([
                'province_id' => '省',
                'city_id'     => '市',
                'district_id' => '区'
            ], '请选择区域');
            $form->text('address', '联系地址');
            $form->textarea('remark', '备注信息')->rows(5);
        });
        $form->tools(function (Form\Tools $tools) {
            // 去掉`列表`按钮
            // $tools->disableList();

            // 去掉`删除`按钮
            $tools->disableDelete();

            // 去掉`查看`按钮
            //$tools->disableView();

            // 添加一个按钮, 参数可以是字符串, 或者实现了Renderable或Htmlable接口的对象实例
            // $tools->add('<a class="btn btn-sm btn-danger"><i class="fa fa-trash"></i>&nbsp;&nbsp;delete</a>');
        });

        //$form->display('id', __('ID'));
        //

        //初始化公司变量
        $form->saved(function (Form $form) {
            $variableService = new VariableService;
            $variableService->initCompanyVariable($form->model()->id, $form->model()->name);
        });
        return $form;
    }
    public function select()
    {
        $data = Company::select('id', 'name as text')->get();
        return $data;
    }

    protected function orderGrid($orders)
    {
        $grid = new Grid($orders);

        $grid->order_no('订单号');
        $grid->column('status', '状态')->display(function ($value) {
            $status = config('paystatus')[$value] ?? '未知状态';
            return "<span style='color:blue'>$status</span>";
        });
        $grid->column('name', '产品名称');
        $grid->column('month', '时长');
        $grid->column('price', '单价');

        $grid->disableCreateButton();
        $grid->disablePagination();
        $grid->disableExport();
        $grid->disableRowSelector();
        $grid->disableActions();
        $grid->disableColumnSelector();
        $grid->disableFilter();

        return $grid;
    }
}
