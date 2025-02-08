<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Lumen\Routing\Router;

class RouteListCommand extends Command
{
    protected $signature = 'route:list';
    protected $description = 'List all registered routes in Lumen';

    public function handle()
    {
        $router = app(Router::class);
        $routes = $router->getRoutes();

        foreach ($routes as $route) {
            $methods = isset($route['method']) ? (array) $route['method'] : [];
            $uri = isset($route['uri']) ? $route['uri'] : 'N/A';
            $action = isset($route['action']) ? json_encode($route['action']) : 'N/A';

            $this->info(sprintf(
                "%s %s -> %s",
                implode('|', $methods),
                $uri,
                $action
            ));
        }
    }
}
