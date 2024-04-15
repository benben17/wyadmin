<?php

namespace App\Admin\Controllers;

use App\Models\Order;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Encore\Admin\Controllers\AdminController;

class OrderController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = '订单管理';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    public function test()
    {
        $f = new \Encore\Admin\Widgets\Form([
            'name' => '初始化数据'
        ]);
        $f->action('/adminyc/area');
        $f->textarea('name', '简介')->help('简介');
        return $content
            ->header('Create')
            ->description('description')
            ->body($f);
        return $content
            ->header('Create')
            ->description('description')
            ->body(view('test', ['name' => '初始化数据'])->render());
    }
    protected function grid()
    {
        $grid = new Grid(new Order());

        $grid->column('id', __('Id'));
        $grid->column('order_no', '订单号');
        $grid->column('status', '状态')->display(function ($value) {
            $status = config('paystatus')[$value];
            return "<span style='color:blue'>$status</span>";
        });
        $grid->column('company.name', '客户名称');
        $grid->column('product.name', '产品名称');
        $grid->column('month', '购买时长')->display(function ($value) {
            return $value . "个月";
        });
        $grid->column('price', '单价(月)');
        $grid->column('amount', '金额(元)');
        $grid->column('paytime', '支付时间');
        $grid->column('c_username', '下单人');
        $grid->column('created_at', '下单时间');
        $grid->disableCreateButton();
        $grid->actions(function ($actions) {
            // 去掉删除
            $actions->disableDelete();

            // 去掉编辑
            $actions->disableEdit();
            // $actions->append('<a href="">test</a>');
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
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Order::findOrFail($id));
        $show->field('order_no', '订单号');
        $show->field('status', '状态')->as(function () {
            return config('paystatus')[$this->status];
        });
        $show->field('company.name', '客户名称')->as(function () {
            return $this->company->name;
        });
        $show->field('month', '购买时长');
        $show->field('price', '单价(月)');
        $show->field('amount', '金额(元)');
        $show->field('paytime', '支付时间');
        $show->field('c_username', '下单人');
        $show->field('created_at', '下单时间');
        $show->field('content', '备注');
        $show->panel()->tools(function ($tools) {
            $tools->disableDelete();
        });
        return $show;
    }
}
