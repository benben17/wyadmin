# README.md

## 环境信息

- php 版本为7.2 以上版本
- php-mysqlnd 模块
- MySQL 5.7 以上版本
- redis服务以及predis模块

### 安装所需组件

`# composer install`

### 更改数据库链接串需要清理一下文件

- `# rm -rf bootstrap/cache/*.php`
- `# php artisan config:cache`
- `# php artisan cache:clear`
- `# php artisan view:clear`
- `# php artisan route:clear`
-

### 必须存在的目录

- storage/framework/cache/data
- storage/framework/sessions
- storage/framework/views

### Excel 扩展

- PHP extension php_zip enabled
- PHP extension php_xml enabled
- PHP extension php_gd2 enabled
- PHP extension php_zip enabled.   phpoffice

[更新日志](/doc/changelog.md)

### 刷新 api文档

- `# php artisan l5-swagger:generate`

[Excel文档](https://docs.laravel-excel.com/3.1/getting-started/upgrade.html)

[微信登录二维码](https://open.weixin.qq.com/connect/qrconnect?appid=wxcea7671633cc6ac2&redirect_uri=https%3A%2F%2Fapi.scly.vip%2Fapi%2Fwx%2Fcallback&response_type=code&scope=snsapi_login&state=3d6be0a4035d839573b04816624a415e#wechat_redirect)

## 部署

#### 安装环境

- nginx
`apt install nginx`
- MySQL
- php 7.4
- redis
`apt install redis`
- 安装
`php composer-setup.php --install-dir=/usr/local/bin --filename=composer`
- 下载代码
`git clone git@github.com:benben17/wyadmin.git`
`php artisan storage:link`
- 变量信息
`cd wyadmin`
`cp .env.example  .env`
- 变量信息
