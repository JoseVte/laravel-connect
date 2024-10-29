<?php

namespace Square1\Laravel\Connect;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\PassportServiceProvider;
use Square1\Laravel\Connect\App\Middleware\AfterConnectMiddleware;
use Square1\Laravel\Connect\Console\InitClient;
use Square1\Laravel\Connect\Console\InstallClient;
use Square1\Laravel\Connect\Console\MakeClient;

class ConnectServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     *
     * @throws BindingResolutionException
     */
    public function boot()
    {

        //registering 3rd party service providers

        //laravel passport, no need to register routes for this
        $this->app->register(PassportServiceProvider::class);

        Response::macro(
            'connect',

            function ($value, $status = 200) {
                return Response::json(
                    [
                        'data' => ConnectUtils::formatResponseData($value),
                        'time' => time(),
                    ],
                    $status
                );
            }
        );

        //Registering routes
        //Passport::routes(null, ['prefix' => config('connect.api.prefix').'/passport']);

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('connect', AfterConnectMiddleware::class);

        $this->loadRoutesFrom(__DIR__.'/App/Routes/routes_connect.php');

        if ($this->app->runningInConsole()) {
            $this->loadViewsFrom(__DIR__.'/views/client/android', 'android');
            $this->loadViewsFrom(__DIR__.'/views/client/iOS', 'ios');

            $this->commands(
                [
                    MakeClient::class,
                    InitClient::class,
                    InstallClient::class,
                ]
            );
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void {}

    /**
     * Load the standard routes file for the application.
     *
     * @param  string  $path
     */
    protected function loadRoutesFrom($path): void
    {
        Route::group(
            [
                'middleware' => ['connect'],
                'namespace' => 'Square1\Laravel\Connect\App\Http\Controllers',
                'prefix' => config('connect.api.prefix'),
                'as' => 'connect.',
            ],
            function ($router) use ($path) {
                include $path;
            }
        );
    }
}
