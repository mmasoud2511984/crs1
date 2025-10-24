<?php
/**
 * File: Session.php
 * Path: /core/Session.php
 * Purpose: Session management with security features
 * Dependencies: None
 */

namespace Core;

/**
 * Session Class
 * إدارة الجلسات بشكل آمن
 */
class Session
{
    /** @var bool حالة بدء الجلسة */
    private static bool $started = false;
    
    /**
     * بدء الجلسة
     * 
     * @return bool
     */
    public static function start(): bool
    {
        if (self::$started) {
            return true;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            // إعدادات الأمان
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', self::isSecure() ? 1 : 0);
            ini_set('session.use_strict_mode', 1);
            
            session_start();
            self::$started = true;
            
            // التحقق من IP
            if (!self::validateSession()) {
                self::destroy();
                session_start();
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * تعيين قيمة
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    /**
     * الحصول على قيمة
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * التحقق من وجود مفتاح
     * 
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    /**
     * حذف قيمة
     * 
     * @param string $key
     * @return void
     */
    public static function delete(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }
    
    /**
     * إضافة flash message
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function flash(string $key, $value): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }
    
    /**
     * الحصول على flash message
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getFlash(string $key, $default = null)
    {
        self::start();
        
        if (isset($_SESSION['_flash'][$key])) {
            $value = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $value;
        }
        
        return $default;
    }
    
    /**
     * تجديد معرف الجلسة
     * 
     * @return bool
     */
    public static function regenerate(): bool
    {
        self::start();
        return session_regenerate_id(true);
    }
    
    /**
     * تدمير الجلسة
     * 
     * @return bool
     */
    public static function destroy(): bool
    {
        self::start();
        
        $_SESSION = [];
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        self::$started = false;
        return session_destroy();
    }
    
    /**
     * الحصول على معرف الجلسة
     * 
     * @return string
     */
    public static function getId(): string
    {
        self::start();
        return session_id();
    }
    
    /**
     * التحقق من صحة الجلسة
     * 
     * @return bool
     */
    private static function validateSession(): bool
    {
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if (!isset($_SESSION['_ip'])) {
            $_SESSION['_ip'] = $currentIp;
            $_SESSION['_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            return true;
        }
        
        return $_SESSION['_ip'] === $currentIp;
    }
    
    /**
     * التحقق من HTTPS
     * 
     * @return bool
     */
    private static function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    }
}
