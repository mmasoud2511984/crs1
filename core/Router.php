<?php
/**
 * File: Router.php
 * Path: /core/Router.php
 * Purpose: URL routing system
 * Dependencies: Request.php, Response.php
 */

namespace Core;

/**
 * Router Class
 * نظام التوجيه - يدير URLs و Routes
 */
class Router
{
    /** @var array مصفوفة Routes */
    private static array $routes = [];
    
    /** @var array مجموعات Routes */
    private static array $groupStack = [];
    
    /** @var string Prefix حالي */
    private static string $currentPrefix = '';
    
    /** @var array Middleware حالية */
    private static array $currentMiddleware = [];
    
    /**
     * تعريف GET route
     * 
     * @param string $uri
     * @param mixed $action
     * @return void
     */
    public static function get(string $uri, $action): void
    {
        self::addRoute('GET', $uri, $action);
    }
    
    /**
     * تعريف POST route
     * 
     * @param string $uri
     * @param mixed $action
     * @return void
     */
    public static function post(string $uri, $action): void
    {
        self::addRoute('POST', $uri, $action);
    }
    
    /**
     * تعريف PUT route
     * 
     * @param string $uri
     * @param mixed $action
     * @return void
     */
    public static function put(string $uri, $action): void
    {
        self::addRoute('PUT', $uri, $action);
    }
    
    /**
     * تعريف DELETE route
     * 
     * @param string $uri
     * @param mixed $action
     * @return void
     */
    public static function delete(string $uri, $action): void
    {
        self::addRoute('DELETE', $uri, $action);
    }
    
    /**
     * إضافة route
     * 
     * @param string $method
     * @param string $uri
     * @param mixed $action
     * @return void
     */
    private static function addRoute(string $method, string $uri, $action): void
    {
        $uri = self::$currentPrefix . '/' . trim($uri, '/');
        $uri = '/' . trim($uri, '/');
        
        self::$routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middleware' => self::$currentMiddleware
        ];
    }
    
    /**
     * مجموعة routes مع attributes مشتركة
     * 
     * @param array $attributes
     * @param callable $callback
     * @return void
     */
    public static function group(array $attributes, callable $callback): void
    {
        self::$groupStack[] = $attributes;
        
        // حفظ الحالة السابقة
        $previousPrefix = self::$currentPrefix;
        $previousMiddleware = self::$currentMiddleware;
        
        // تطبيق attributes
        if (isset($attributes['prefix'])) {
            self::$currentPrefix .= '/' . trim($attributes['prefix'], '/');
        }
        
        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware']) 
                ? $attributes['middleware'] 
                : [$attributes['middleware']];
            self::$currentMiddleware = array_merge(self::$currentMiddleware, $middleware);
        }
        
        // تنفيذ callback
        $callback();
        
        // استعادة الحالة السابقة
        self::$currentPrefix = $previousPrefix;
        self::$currentMiddleware = $previousMiddleware;
        
        array_pop(self::$groupStack);
    }
    
    /**
     * تعريف resource routes (CRUD)
     * 
     * @param string $uri
     * @param string $controller
     * @return void
     */
    public static function resource(string $uri, string $controller): void
    {
        self::get($uri, [$controller, 'index']);
        self::get($uri . '/create', [$controller, 'create']);
        self::post($uri, [$controller, 'store']);
        self::get($uri . '/{id}', [$controller, 'show']);
        self::get($uri . '/{id}/edit', [$controller, 'edit']);
        self::put($uri . '/{id}', [$controller, 'update']);
        self::delete($uri . '/{id}', [$controller, 'destroy']);
    }
    
    /**
     * توجيه الطلب
     * 
     * @param string $method
     * @param string $uri
     * @return mixed
     */
    public static function dispatch(string $method, string $uri)
    {
        $uri = '/' . trim($uri, '/');
        
        foreach (self::$routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            $pattern = self::convertToRegex($route['uri']);
            
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // إزالة المطابقة الكاملة
                
                // تنفيذ middleware
                if (!empty($route['middleware'])) {
                    foreach ($route['middleware'] as $middleware) {
                        // تنفيذ middleware
                        // يمكن تحسينه لاحقاً
                    }
                }
                
                return self::executeAction($route['action'], $matches);
            }
        }
        
        // 404 Not Found
        http_response_code(404);
        echo "404 - Page Not Found";
        exit;
    }
    
    /**
     * تحويل URI إلى regex pattern
     * 
     * @param string $uri
     * @return string
     */
    private static function convertToRegex(string $uri): string
    {
        $uri = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $uri);
        return '#^' . $uri . '$#';
    }
    
    /**
     * تنفيذ action
     * 
     * @param mixed $action
     * @param array $params
     * @return mixed
     */
    private static function executeAction($action, array $params = [])
    {
        if (is_callable($action)) {
            return call_user_func_array($action, $params);
        }
        
        if (is_array($action)) {
            [$controller, $method] = $action;
            
            if (is_string($controller)) {
                $controller = new $controller();
            }
            
            return call_user_func_array([$controller, $method], $params);
        }
        
        throw new \Exception("Invalid route action");
    }
    
    /**
     * الحصول على جميع routes
     * 
     * @return array
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }
}
