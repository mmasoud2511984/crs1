<?php
/**
 * File: View.php
 * Path: /core/View.php
 * Purpose: View rendering system
 * Dependencies: None
 */

namespace Core;

/**
 * View Class
 * نظام عرض Views
 */
class View
{
    /** @var string مسار Views */
    private static string $viewPath = __DIR__ . '/../app/views/';
    
    /** @var string مسار الكاش */
    private static string $cachePath = __DIR__ . '/../storage/cache/views/';
    
    /** @var array البيانات المشتركة */
    private static array $sharedData = [];
    
    /** @var bool استخدام الكاش */
    private static bool $cacheEnabled = false;
    
    /**
     * عرض View
     * 
     * @param string $view
     * @param array $data
     * @return void
     */
    public static function render(string $view, array $data = []): void
    {
        echo self::make($view, $data);
    }
    
    /**
     * إنشاء View
     * 
     * @param string $view
     * @param array $data
     * @return string
     */
    public static function make(string $view, array $data = []): string
    {
        $viewFile = self::getViewPath($view);
        
        if (!file_exists($viewFile)) {
            throw new \Exception("View not found: {$view}");
        }
        
        // دمج البيانات المشتركة
        $data = array_merge(self::$sharedData, $data);
        
        // استخراج المتغيرات
        extract($data);
        
        // بدء buffer
        ob_start();
        
        // تضمين الملف
        include $viewFile;
        
        // الحصول على المحتوى
        $content = ob_get_clean();
        
        return $content;
    }
    
    /**
     * مشاركة بيانات مع جميع Views
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function share(string $key, $value): void
    {
        self::$sharedData[$key] = $value;
    }
    
    /**
     * الحصول على مسار View
     * 
     * @param string $view
     * @return string
     */
    private static function getViewPath(string $view): string
    {
        // تحويل النقاط إلى مسارات
        $view = str_replace('.', '/', $view);
        
        // إضافة امتداد .php إذا لم يكن موجوداً
        if (!str_ends_with($view, '.php')) {
            $view .= '.php';
        }
        
        return self::$viewPath . $view;
    }
    
    /**
     * التحقق من وجود View
     * 
     * @param string $view
     * @return bool
     */
    public static function exists(string $view): bool
    {
        return file_exists(self::getViewPath($view));
    }
    
    /**
     * تعيين مسار Views
     * 
     * @param string $path
     * @return void
     */
    public static function setViewPath(string $path): void
    {
        self::$viewPath = rtrim($path, '/') . '/';
    }
    
    /**
     * تفعيل الكاش
     * 
     * @param bool $enabled
     * @return void
     */
    public static function enableCache(bool $enabled = true): void
    {
        self::$cacheEnabled = $enabled;
        
        if ($enabled && !is_dir(self::$cachePath)) {
            mkdir(self::$cachePath, 0755, true);
        }
    }
}
