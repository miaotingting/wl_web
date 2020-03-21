<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class ValidaterProvider extends ServiceProvider
{
    public function addValidater($name, $validaterClass) {
        Validator::extend($name, $validaterClass);
        Validator::replacer($name, $validaterClass);
    }
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        $this->addValidater('mobile', 'App\Http\Validater\Mobile');
        $this->addValidater('decimal', 'App\Http\Validater\Decimal');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
