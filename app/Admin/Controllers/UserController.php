<?php

namespace App\Admin\Controllers;

use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Support\Facades\Hash;

class UserController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = '用户管理';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new User());

        $grid->column('id', __('Id'));
        $grid->column('name', '用户名');
        $grid->column('realname', '真实姓名');
        $grid->column('email', '邮箱');
        $grid->column('phone', '手机号');
        $grid->column('company.name', '公司名称');
        $grid->column('Role.name', '角色名称');
        $grid->column('created_at', '创建时间');
        $grid->column('updated_at', '更新时间');
        // $grid->disableCreateButton();
        $grid->actions(function ($actions) {
            // 去掉删除
            $actions->disableDelete();

            // 去掉编辑
            //$actions->disableEdit();

            // 去掉查看
            $actions->disableView();
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
        $show = new Show(User::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('name', '用户名');
        $show->field('email', '邮箱');
        $show->field('phone', '手机号');
        $show->field('created_at', '创建时间');
        $show->field('updated_at', '更新时间');
        $show->field('remark', '备注');
        $show->panel()->tools(function ($tools) {
            $tools->disableDelete();
            $tools->disableEdit();
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
        $form = new Form(new User());
        $form->text('name', '用户名')->rules('required', ['required'   => '用户名为必填!']);
        $form->text('realname', '真实姓名')->rules('required', ['required'   => '真实姓名为必填!']);
        $form->email('email', '邮箱');
        $form->hidden('password', '密码');
        $form->text('phone', '手机号')->rules('required', ['required'   => '手机号为必填!']);
        $form->select('company_id', '所属公司')->options('/admin/sys/company/select')
            ->rules('required', ['required'   => '必须选择一个公司!']);
        $form->select('role_id', '角色名称')->options('/admin/sys/role/select')
            ->rules('required', ['required'   => '必须选择一个角色!']);
        $form->text('is_admin')->value(1);
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
        $form->submitted(function (Form $form) {

            $form->password = Hash::make('a123456');
        });

        return $form;
    }
}
