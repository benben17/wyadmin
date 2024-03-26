<?php

namespace App\Admin\Controllers;

use App\Models\Module;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class ModuleController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = '功能模块';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Module());

        $grid->column('id', __('Id'));
        $grid->column('title', '模块名称');
        $grid->column('name', '模块标识');
        $grid->column('price', '价格');
        $grid->column('created_at', '创建时间');
        $grid->column('updated_at', '修改时间');
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
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Module::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('title', '模块名称');
        $show->field('name', '模块标识');
        $show->field('price', '价格');
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
        $form = new Form(new Module());

        $form->text('title', '模块名称');
        $form->text('name', '模块标识');
        $form->text('price', '价格');
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
        return $form;
    }
}
