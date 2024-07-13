<?php

namespace App\Providers;

use Encore\Admin\Config\Config;
use Godruoyi\Snowflake\Snowflake;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Godruoyi\Snowflake\LaravelSequenceResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // 雪花算法
        $this->app->singleton('snowflake', function ($app) {
            return (new Snowflake())
                ->setStartTimeStamp(strtotime('2024-01-01') * 1000)
                ->setSequenceResolver(new LaravelSequenceResolver($app->get('cache.store')));
        });
    }


    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $table = config('admin.extensions.config.table', 'admin_config');
        if (Schema::hasTable($table)) {
            Config::load();
        }
        // $url->forceScheme('https');
    }
}
