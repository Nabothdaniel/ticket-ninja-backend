<?php

namespace App\Core;

/**
 * Router Class
 * 
 * Handles HTTP routing with middleware support
 */
class Router
{
    private $routes = [];
    
    /**
     * Register a GET route
     */
    public function get($path, $handler, $middleware = [])
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }
    
    /**
     * Register a POST route
     */
    public function post($path, $handler, $middleware = [])
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }
    
    /**
     * Register a PUT route
     */
    public function put($path, $handler, $middleware = [])
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }
    
    /**
     * Register a PATCH route
     */
    public function patch($path, $handler, $middleware = [])
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }
    
    /**
     * Register a DELETE route
     */
    public function delete($path, $handler, $middleware = [])
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }
    
    /**
     * Add a route to the router
     */
    private function addRoute($method, $path, $handler, $middleware = [])
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }
    
    /**
     * Dispatch the request to the appropriate handler
     */
    public function dispatch($method, $uri)
    {
        foreach ($this->routes as $route) {
            $pattern = $this->convertPathToRegex($route['path']);
            
            if ($route['method'] === $method && preg_match($pattern, $uri, $matches)) {
                // Extract path parameters
                array_shift($matches); // Remove full match
                $params = $matches;
                
                // Run middleware
                foreach ($route['middleware'] as $middleware) {
                    $result = $middleware->handle();
                    if ($result !== true) {
                        return; // Middleware stopped execution
                    }
                }
                
                // Execute handler
                $this->executeHandler($route['handler'], $params);
                return;
            }
        }
        
        // No route found
        Response::error('Route not found', 404);
    }
    
    /**
     * Convert route path to regex pattern
     */
    private function convertPathToRegex($path)
    {
        // Convert {param} to named capture groups
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
    
    /**
     * Execute the route handler
     */
    private function executeHandler($handler, $params = [])
    {
        if (is_callable($handler)) {
            // Closure handler
            $result = call_user_func_array($handler, $params);
            if ($result !== null) {
                echo json_encode($result);
            }
        } elseif (is_string($handler)) {
            // Controller@method handler
            list($controller, $method) = explode('@', $handler);
            $controllerClass = "App\\Controllers\\{$controller}";
            
            if (!class_exists($controllerClass)) {
                Response::error("Controller {$controllerClass} not found", 500);
                return;
            }
            
            $controllerInstance = new $controllerClass();
            
            if (!method_exists($controllerInstance, $method)) {
                Response::error("Method {$method} not found in {$controller}", 500);
                return;
            }
            
            $result = call_user_func_array([$controllerInstance, $method], $params);
            if ($result !== null) {
                echo json_encode($result);
            }
        }
    }
}
?>
