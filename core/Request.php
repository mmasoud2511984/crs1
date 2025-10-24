<?php
/**
 * File: Request.php
 * Path: /core/Request.php
 * Purpose: HTTP Request handler
 * Dependencies: None
 */

namespace Core;

/**
 * Request Class
 * معالجة طلبات HTTP
 */
class Request
{
    /** @var array بيانات GET */
    private array $get;
    
    /** @var array بيانات POST */
    private array $post;
    
    /** @var array بيانات SERVER */
    private array $server;
    
    /** @var array الملفات المرفوعة */
    private array $files;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->files = $_FILES;
    }
    
    /**
     * الحصول على جميع البيانات
     * 
     * @return array
     */
    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }
    
    /**
     * الحصول على قيمة من GET
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->get[$key] ?? $default;
    }
    
    /**
     * الحصول على قيمة من POST
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function post(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }
    
    /**
     * التحقق من وجود مفتاح
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->get[$key]) || isset($this->post[$key]);
    }
    
    /**
     * الحصول على قيمة من GET أو POST
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }
    
    /**
     * الحصول على ملف مرفوع
     * 
     * @param string $key
     * @return array|null
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }
    
    /**
     * الحصول على جميع الملفات
     * 
     * @return array
     */
    public function files(): array
    {
        return $this->files;
    }
    
    /**
     * الحصول على HTTP method
     * 
     * @return string
     */
    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }
    
    /**
     * الحصول على URI
     * 
     * @return string
     */
    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        
        // إزالة query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        
        return $uri;
    }
    
    /**
     * الحصول على IP address
     * 
     * @return string
     */
    public function ip(): string
    {
        if (!empty($this->server['HTTP_CLIENT_IP'])) {
            return $this->server['HTTP_CLIENT_IP'];
        }
        
        if (!empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            return $this->server['HTTP_X_FORWARDED_FOR'];
        }
        
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * الحصول على header
     * 
     * @param string $key
     * @return string|null
     */
    public function header(string $key): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$key] ?? null;
    }
    
    /**
     * التحقق من طلب AJAX
     * 
     * @return bool
     */
    public function isAjax(): bool
    {
        return ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
    
    /**
     * التحقق من HTTPS
     * 
     * @return bool
     */
    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || ($this->server['SERVER_PORT'] ?? 80) == 443;
    }
    
    /**
     * الحصول على user agent
     * 
     * @return string
     */
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }
}
