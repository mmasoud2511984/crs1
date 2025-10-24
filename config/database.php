<?php
/**
 * File: database.php
 * Path: /config/database.php
 * Purpose: Database configuration
 * Dependencies: None
 */

return [
    /**
     * نوع قاعدة البيانات
     * Supported: mysql, pgsql, sqlite
     */
    'driver' => getenv('DB_DRIVER') ?: 'mysql',
    
    /**
     * عنوان الخادم
     */
    'host' => getenv('DB_HOST') ?: 'localhost',
    
    /**
     * منفذ الاتصال
     */
    'port' => getenv('DB_PORT') ?: 3306,
    
    /**
     * اسم قاعدة البيانات
     */
    'database' => getenv('DB_DATABASE') ?: 'u995861180_carrent_03',
    
    /**
     * اسم المستخدم
     */
    'username' => getenv('DB_USERNAME') ?: 'u995861180_user_03',
    
    /**
     * كلمة المرور
     */
    'password' => getenv('DB_PASSWORD') ?: 'Z%J@DO5N3t',
    
    /**
     * ترميز الأحرف
     */
    'charset' => 'utf8mb4',
    
    /**
     * Collation
     */
    'collation' => 'utf8mb4_unicode_ci',
    
    /**
     * بادئة الجداول (اختياري)
     */
    'prefix' => '',
    
    /**
     * تفعيل تسجيل الاستعلامات
     * تحذير: قم بتعطيله في بيئة الإنتاج
     */
    'log_queries' => getenv('DB_LOG_QUERIES') === 'true',
    
    /**
     * إعدادات PDO الإضافية
     */
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
    ]
];
