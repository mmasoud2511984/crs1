<?php
/**
 * File: routes.php
 * Path: /routes.php
 * Purpose: Application routes definition
 * Dependencies: Router.php
 */

use Core\Router;

// ============================================================================
// Frontend Routes
// ============================================================================

// الصفحة الرئيسية
Router::get('/', function() {
    echo '<h1>مرحباً في نظام تأجير السيارات</h1>';
    echo '<p>النظام جاهز للعمل!</p>';
});

// صفحة About
Router::get('/about', function() {
    echo '<h1>من نحن</h1>';
});

// صفحة Contact
Router::get('/contact', function() {
    echo '<h1>اتصل بنا</h1>';
});

// ============================================================================
// API Routes
// ============================================================================

Router::group(['prefix' => 'api'], function() {
    // API Health Check
    Router::get('/health', function() {
        Core\Response::json([
            'status' => 'ok',
            'timestamp' => time(),
            'version' => '1.0.0'
        ])->send();
    });
});

// ============================================================================
// Admin Routes (محمية)
// ============================================================================

Router::group(['prefix' => 'admin'], function() {
    // صفحة تسجيل الدخول
    Router::get('/login', function() {
        echo '<h1>Admin Login</h1>';
    });
    
    // Dashboard
    Router::get('/dashboard', function() {
        echo '<h1>Admin Dashboard</h1>';
    });
});
