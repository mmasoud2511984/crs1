<?php
/**
 * File: app.php
 * Path: /config/app.php
 * Purpose: Application configuration
 * Dependencies: None
 */

return [
    /**
     * اسم التطبيق
     */
    'name' => getenv('APP_NAME') ?: 'Car Rental System',
    
    /**
     * بيئة التطبيق
     * Options: local, production, staging
     */
    'environment' => getenv('APP_ENV') ?: 'local',
    
    /**
     * وضع التطوير (Debug Mode)
     * تحذير: اضبطه على false في الإنتاج
     */
    'debug' => getenv('APP_DEBUG') === 'true',
    
    /**
     * رابط التطبيق
     */
    'url' => getenv('APP_URL') ?: 'http://localhost',
    
    /**
     * المنطقة الزمنية
     */
    'timezone' => 'Asia/Riyadh',
    
    /**
     * اللغة الافتراضية
     */
    'locale' => 'ar',
    
    /**
     * اللغة الاحتياطية
     */
    'fallback_locale' => 'en',
    
    /**
     * اللغات المتاحة
     */
    'available_locales' => ['ar', 'en'],
    
    /**
     * مفتاح التشفير
     * تحذير: يجب تغييره في الإنتاج
     */
    'encryption_key' => getenv('APP_KEY') ?: 'base64:' . base64_encode(random_bytes(32)),
    
    /**
     * إعدادات الجلسة
     */
    'session' => [
        'name' => 'CAR_RENTAL_SESSION',
        'lifetime' => 120, // بالدقائق
        'path' => '/',
        'domain' => null,
        'secure' => getenv('SESSION_SECURE') === 'true',
        'httponly' => true,
        'samesite' => 'lax',
    ],
    
    /**
     * إعدادات الكوكيز
     */
    'cookie' => [
        'lifetime' => 2628000, // 1 شهر
        'path' => '/',
        'domain' => null,
        'secure' => getenv('COOKIE_SECURE') === 'true',
        'httponly' => true,
    ],
    
    /**
     * عدد العناصر في كل صفحة
     */
    'pagination' => [
        'per_page' => 15,
        'admin_per_page' => 25,
    ],
    
    /**
     * إعدادات الملفات المرفوعة
     */
    'upload' => [
        'max_size' => 5242880, // 5MB
        'allowed_types' => [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
        ],
        'paths' => [
            'cars' => 'public/uploads/cars',
            'customers' => 'public/uploads/customers',
            'users' => 'public/uploads/users',
            'documents' => 'public/uploads/documents',
        ],
    ],
    
    /**
     * إعدادات التخزين المؤقت
     */
    'cache' => [
        'enabled' => getenv('CACHE_ENABLED') === 'true',
        'driver' => 'file', // file, redis, memcached
        'ttl' => 3600, // 1 ساعة
    ],
    
    /**
     * إعدادات السجلات
     */
    'logging' => [
        'enabled' => true,
        'path' => 'storage/logs',
        'level' => 'debug', // debug, info, warning, error
        'max_files' => 30,
    ],
    
    /**
     * إعدادات النسخ الاحتياطي
     */
    'backup' => [
        'enabled' => true,
        'path' => 'storage/backups',
        'auto_delete_old' => true,
        'keep_days' => 30,
    ],
];
