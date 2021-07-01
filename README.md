##环境信息
php 版本为7.2 以上版本
php-mysqlnd 模块
MySQL 5.7 以上版本

#安装所需组件
更改数据库链接串需要清理一下文件
- `rm -rf bootstrap/cache/*.php`
- `php artisan config:cache`
- `php artisan cache:clear`
- `php artisan view:clear`
- `php artisan route:clear`
- 

## 必须存在的目录
- storage/framework/cache/data
- storage/framework/sessions
- storage/framework/views






Excel 扩展!   
- PHP extension php_zip enabled
- PHP extension php_xml enabled
- PHP extension php_gd2 enabled
- PHP extension php_zip enabled.   phpoffice

[测试日志](http://note.youdao.com/s/6L586NmV)


刷新 api文档
- php artisan l5-swagger:generate
    


[Excel文档](https://docs.laravel-excel.com/3.1/getting-started/upgrade.html)

https://open.weixin.qq.com/connect/qrconnect?appid=wxcea7671633cc6ac2&redirect_uri=https%3A%2F%2Fapi.stararea.cn%2Fapi%2Fwx%2Fcallback&response_type=code&scope=snsapi_login&state=3d6be0a4035d839573b04816624a415e#wechat_redirect