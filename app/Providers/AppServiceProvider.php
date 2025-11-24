<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use App\Http\Controllers\Artifacts\ConstantsController;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap with any application services.
     */
    public function boot(): void
    {
        Inertia::share('const', function () {
           // return true; //(new ConstantsController())->getAppConstants();
        });
    }
}
