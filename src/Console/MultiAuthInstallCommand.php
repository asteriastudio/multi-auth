<?php

namespace Bmatovu\MultiAuth\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;

class MultiAuthInstallCommand extends Command
{

    protected $name = '';

    protected $exits = false;

    protected $override = false;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'multi-auth:install
                                {name=admin : Name of the guard. Default: \'admin\'}
                                {--f|force : Whether to override existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install multi-auth package';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Initiating...');

        $progress = $this->output->createProgressBar(10);

        $this->name = $this->argument('name');

        $this->override = $this->option('force') ? true : false;

        // Check if guard is already registered
        if (array_key_exists(str_slug($this->name), config('auth.guards'))) {
            // Guard exists
            $this->exits = true;

            if (!$this->option('force')) {
                $this->info("Guard: '" . $this->name . "' is already registered");
                if (!$this->confirm('Force override resources...?')) {
                    throw new \RuntimeException("Halting installation, choose another guard name...");
                }
                // Override resources
                $this->override = true;
            }
        }

        $this->info("Using guard: '" . $this->name . "'");

        $progress->advance();

        // Configurations
        $this->info(PHP_EOL . 'Registering configurations...');

        if ($this->exits && $this->override) {
            $this->info('Configurations registration skipped');
        } else {
            $this->registerConfigurations(__DIR__ . '/../../stubs');
            $this->info('Configurations registered in ' . config_path('auth.php'));
        }

        $progress->advance();

        // Models
        $this->info(PHP_EOL . 'Creating Model...');

        $model_path = $this->loadModel(__DIR__ . '/../../stubs');

        $this->info('Model created at ' . $model_path);

        $progress->advance();

        // Migrations
        $this->info(PHP_EOL . 'Creating Migration...');

        if ($this->exits && $this->override) {
            $this->info('Migration creation skipped');
        } else {
            $model_path = $this->loadMigration(__DIR__ . '/../../stubs');
            $this->info('Migration created at ' . $model_path);
        }

        $progress->advance();

        // Controllers
        $this->info(PHP_EOL . 'Creating Controllers...');

        $controllers_path = $this->loadControllers(__DIR__ . '/../../stubs');

        $this->info('Controllers created at ' . $controllers_path);

        $progress->advance();

        // Views
        $this->info(PHP_EOL . 'Creating Views...');

        $views_path = $this->loadViews(__DIR__ . '/../../stubs');

        $this->info('Views created at ' . $views_path);

        $progress->advance();

        // Routes
        $this->info(PHP_EOL . 'Creating Routes...');

        $routes_path = $this->loadRoutes(__DIR__ . '/../../stubs');

        $this->info('Routes created at ' . $routes_path);

        $progress->advance();

        // Routes Service Provider
        $this->info(PHP_EOL . 'Registering Routes Service Provider...');

        if ($this->exits && $this->override) {
            $this->info('Routes service provider registration skipped');
        } else {
            $routes_sp_path = $this->registerRoutes(__DIR__ . '/../../stubs');
            $this->info('Routes registered in service provider: ' . $routes_sp_path);
        }

        $progress->advance();

        // Middleware
        $this->info(PHP_EOL . 'Creating Middleware...');

        $middleware_path = $this->loadMiddleware(__DIR__ . '/../../stubs');

        $this->info('Middleware created at ' . $middleware_path);

        $progress->advance();

        // Route Middleware
        $this->info(PHP_EOL . 'Registering route middleware...');

        if ($this->exits && $this->override) {
            $this->info('Route middleware registration skipped');
        } else {
            $kernel_path = $this->registerRouteMiddleware(__DIR__ . '/../../stubs');
            $this->info('Route middleware registered in ' . $kernel_path);
        }

        $progress->finish();

        $this->info(PHP_EOL . 'Installation complete.');

//        $this->info('All is set; http://127.0.0.1:8000/' . str_singular(str_slug($this->name)));
    }

    /**
     * Get project namespace
     * Default: App
     * @return string
     */
    protected function getNamespace()
    {
        $namespace = Container::getInstance()->getNamespace();
        return rtrim($namespace, '\\');
    }

    /**
     * Parse guard name
     * Get the guard name in different cases
     * @param string $name
     * @return array
     */
    protected function parseName($name = null)
    {
        if (!$name)
            $name = $this->name;

        return $parsed = array(
            '{{pluralCamel}}' => str_plural(camel_case($name)),
            '{{pluralSlug}}' => str_plural(str_slug($name)),
            '{{pluralSnake}}' => str_plural(snake_case($name)),
            '{{pluralClass}}' => str_plural(studly_case($name)),
            '{{singularCamel}}' => str_singular(camel_case($name)),
            '{{singularSlug}}' => str_singular(str_slug($name)),
            '{{singularSnake}}' => str_singular(snake_case($name)),
            '{{singularClass}}' => str_singular(studly_case($name)),
        );
    }

    /**
     * Register configurations
     * Add guard configurations to config/auth.php
     * @param $stub_path
     */
    protected function registerConfigurations($stub_path)
    {
        try {

            $auth = file_get_contents(config_path('auth.php'));

            $data_map = $this->parseName();

            $data_map['{{namespace}}'] = $this->getNamespace();

            /********** Guards **********/

            $guards = file_get_contents($stub_path . '/config/guards.stub');

            // compile stub...
            $guards = strtr($guards, $data_map);

            $guards_bait = "'guards' => [";

            $auth = str_replace($guards_bait, $guards_bait . $guards, $auth);

            /********** Providers **********/

            $providers = file_get_contents($stub_path . '/config/providers.stub');

            // compile stub...
            $providers = strtr($providers, $data_map);

            $providers_bait = "'providers' => [";

            $auth = str_replace($providers_bait, $providers_bait . $providers, $auth);

            /********** Passwords **********/

            $passwords = file_get_contents($stub_path . '/config/passwords.stub');

            // compile stub...
            $passwords = strtr($passwords, $data_map);

            $passwords_bait = "'passwords' => [";

            $auth = str_replace($passwords_bait, $passwords_bait . $passwords, $auth);

            // Overwrite config file
            file_put_contents(config_path('auth.php'), $auth);

        } catch (Exception $ex) {
            throw new \RuntimeException($ex->getMessage());
        }
    }

    /**
     * Load model
     * @param $stub_path
     * @return string
     */
    protected function loadModel($stub_path)
    {
        try {

            $stub = file_get_contents($stub_path . '/Model.stub');

            $data_map = $this->parseName();

            $data_map['{{namespace}}'] = $this->getNamespace();

            $model = strtr($stub, $data_map);

            $model_path = app_path($data_map['{{singularClass}}'] . '.php');

            file_put_contents($model_path, $model);

            return $model_path;

        } catch (Exception $ex) {
            throw new \RuntimeException($ex->getMessage());
        }
    }

    /**
     * Load migration
     * @param $stub_path
     * @return string
     */
    protected function loadMigration($stub_path)
    {
        try {

            $stub = file_get_contents($stub_path . '/migration.stub');

            $data_map = $this->parseName();

            $data_map['{{namespace}}'] = $this->getNamespace();

            $data_map['{{pluralSlug}}'] = str_plural(str_slug($this->name, '_'));

            $migration = strtr($stub, $data_map);

            $signature = date('Y_m_d_His');

            $migration_path = database_path('migrations/' . $signature . '_create_' . $data_map['{{pluralSnake}}'] . '_table.php');

            file_put_contents($migration_path, $migration);

            return $migration_path;

        } catch (Exception $ex) {
            throw new \RuntimeException($ex->getMessage());
        }
    }

    /**
     * Load controllers
     * @param $stub_path
     * @return string
     */
    protected function loadControllers($stub_path)
    {
        $data_map = $this->parseName();

        $data_map['{{namespace}}'] = $this->getNamespace();

        $data_map['{{pluralSlug}}'] = str_plural(str_slug($this->name, '_'));

        $guard = $data_map['{{singularClass}}'];

        $controllers_path = app_path('/Http/Controllers/' . $guard);

        $controllers = array(
            [
                'stub' => $stub_path . '/Controllers/HomeController.stub',
                'path' => $controllers_path . '/HomeController.php',
            ],
            [
                'stub' => $stub_path . '/Controllers/Auth/ForgotPasswordController.stub',
                'path' => $controllers_path . '/Auth/ForgotPasswordController.php',
            ],
            [
                'stub' => $stub_path . '/Controllers/Auth/LoginController.stub',
                'path' => $controllers_path . '/Auth/LoginController.php',
            ],
            [
                'stub' => $stub_path . '/Controllers/Auth/RegisterController.stub',
                'path' => $controllers_path . '/Auth/RegisterController.php',
            ],
            [
                'stub' => $stub_path . '/Controllers/Auth/ResetPasswordController.stub',
                'path' => $controllers_path . '/Auth/ResetPasswordController.php',
            ]
        );

        foreach ($controllers as $controller) {
            $stub = file_get_contents($controller['stub']);
            $complied = strtr($stub, $data_map);

            $dir = dirname($controller['path']);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($controller['path'], $complied);
        }

        return $controllers_path;
    }

    /**
     * Load views
     * @param $stub_path
     * @return string
     */
    protected function loadViews($stub_path)
    {
        $data_map = $this->parseName();

        $data_map['{{namespace}}'] = $this->getNamespace();

        $guard = $data_map['{{singularSlug}}'];

        $views_path = resource_path('views/' . $guard);

        $views = array(
            [
                'stub' => $stub_path . '/views/home.blade.stub',
                'path' => $views_path . '/home.blade.php',
            ],
            [
                'stub' => $stub_path . '/views/layouts/app.blade.stub',
                'path' => $views_path . '/layouts/app.blade.php',
            ],
            [
                'stub' => $stub_path . '/views/auth/login.blade.stub',
                'path' => $views_path . '/auth/login.blade.php',
            ],
            [
                'stub' => $stub_path . '/views/auth/register.blade.stub',
                'path' => $views_path . '/auth/register.blade.php',
            ],
            [
                'stub' => $stub_path . '/views/auth/passwords/email.blade.stub',
                'path' => $views_path . '/auth/passwords/email.blade.php',
            ],
            [
                'stub' => $stub_path . '/views/auth/passwords/reset.blade.stub',
                'path' => $views_path . '/auth/passwords/reset.blade.php',
            ],
        );

        foreach ($views as $view) {
            $stub = file_get_contents($view['stub']);
            $complied = strtr($stub, $data_map);

            $dir = dirname($view['path']);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($view['path'], $complied);
        }

        return $views_path;
    }

    /**
     * Load routes
     * @param $stub_path
     * @return string
     */
    protected function loadRoutes($stub_path)
    {
        $data_map = $this->parseName();

        $guard = $data_map['{{singularSlug}}'];

        $routes_path = base_path('/routes/' . $guard . '.php');

        $routes = array(
            'stub' => $stub_path . '/routes/routes.stub',
            'path' => $routes_path,
        );

        $stub = file_get_contents($routes['stub']);
        $complied = strtr($stub, $data_map);

        file_put_contents($routes['path'], $complied);

        return $routes_path;
    }

    /**
     * Register routes
     * @param $stub_path
     * @return string
     */
    protected function registerRoutes($stub_path)
    {
        try {

            $provider_path = app_path('Providers/RouteServiceProvider.php');

            $provider = file_get_contents($provider_path);

            $data_map = $this->parseName();

            $data_map['{{namespace}}'] = $this->getNamespace();

            /********** Function **********/

            $stub = $stub_path . '/routes/map.stub';

            // If laravel version 5.3
            if (substr(app()->version(), 0, 3) == "5.3") {
                $stub = $stub_path . '/routes/map5.3.stub';
            }

            $map = file_get_contents($stub);

            $map = strtr($map, $data_map);

            $map_bait = "    /**\n" . '     * Define the "web" routes for the application.';

            $provider = str_replace($map_bait, $map . $map_bait, $provider);

            /********** Function Call **********/

            $map_call = file_get_contents($stub_path . '/routes/map_call.stub');

            $map_call = strtr($map_call, $data_map);

            $map_call_bait = '$this->mapWebRoutes();';

            $provider = str_replace($map_call_bait, $map_call_bait . $map_call, $provider);

            // Overwrite config file
            file_put_contents($provider_path, $provider);

            return $provider_path;

        } catch (Exception $ex) {
            throw new \RuntimeException($ex->getMessage());
        }
    }

    /**
     * Load middleware
     * @param $stub_path
     * @return string
     */
    protected function loadMiddleware($stub_path)
    {
        try {

            $data_map = $this->parseName();

            $data_map['{{namespace}}'] = $this->getNamespace();

            $middleware_path = app_path('Http/Middleware');

            // ...

            $stub = file_get_contents($stub_path . '/Middleware/RedirectIfAuthenticated.stub');

            $guest_middleware = strtr($stub, $data_map);

            file_put_contents($middleware_path . '/RedirectIf' . $data_map['{{singularClass}}'] . '.php', $guest_middleware);

            // ...

            $stub = file_get_contents($stub_path . '/Middleware/RedirectIfNotAuthenticated.stub');

            $middleware = strtr($stub, $data_map);

            file_put_contents($middleware_path . '/RedirectIfNot' . $data_map['{{singularClass}}'] . '.php', $middleware);

            return $middleware_path;

        } catch (Exception $ex) {
            throw new \RuntimeException($ex->getMessage());
        }
    }

    /**
     * Register middleware
     * @param $stub_path
     * @return string
     */
    protected function registerRouteMiddleware($stub_path)
    {
        try {

            $data_map = $this->parseName();

            $kernel_path = app_path('Http/Kernel.php');

            $kernel = file_get_contents($kernel_path);

            /********** Route Middleware **********/

            $route_mw = file_get_contents($stub_path . '/Middleware/Kernel.stub');

            $route_mw = strtr($route_mw, $data_map);

            $route_mw_bait = 'protected $routeMiddleware = [';

            $kernel = str_replace($route_mw_bait, $route_mw_bait . $route_mw, $kernel);

            // Overwrite config file
            file_put_contents($kernel_path, $kernel);

            return $kernel_path;

        } catch (Exception $ex) {
            throw new \RuntimeException($ex->getMessage());
        }
    }

}