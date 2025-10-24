<?php
/**
 * File: create-folders.php
 * Purpose: Create missing folders and fix permissions
 */

echo "<!DOCTYPE html>";
echo "<html lang='ar' dir='rtl'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>إنشاء المجلدات</title>";
echo "<style>
body { font-family: Arial; padding: 40px; background: #f5f5f5; }
.box { background: white; padding: 30px; border-radius: 10px; max-width: 800px; margin: 0 auto; }
.success { color: #27ae60; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0; }
.error { color: #e74c3c; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0; }
.info { color: #2c3e50; padding: 10px; background: #d1ecf1; border-radius: 5px; margin: 10px 0; }
h1 { color: #2c3e50; }
</style>";
echo "</head>";
echo "<body>";
echo "<div class='box'>";
echo "<h1>🔧 إنشاء المجلدات المفقودة</h1>";

// تعريف المسار الأساسي
$rootPath = __DIR__;

// المجلدات المطلوبة
$folders = [
    // Storage
    'storage/cache',
    'storage/backups',
    'storage/temp',
    
    // Public
    'public',
    'public/assets',
    'public/assets/css',
    'public/assets/css/frontend',
    'public/assets/css/backend',
    'public/assets/css/common',
    'public/assets/js',
    'public/assets/js/frontend',
    'public/assets/js/backend',
    'public/assets/js/common',
    'public/assets/images',
    'public/assets/fonts',
    'public/uploads',
    'public/uploads/cars',
    'public/uploads/customers',
    'public/uploads/users',
    'public/uploads/documents',
    
    // App
    'app/controllers',
    'app/controllers/frontend',
    'app/controllers/backend',
    'app/controllers/api',
];

$created = [];
$skipped = [];
$errors = [];

foreach ($folders as $folder) {
    $fullPath = $rootPath . '/' . $folder;
    
    if (is_dir($fullPath)) {
        $skipped[] = $folder;
    } else {
        if (mkdir($fullPath, 0777, true)) {
            chmod($fullPath, 0777);
            $created[] = $folder;
        } else {
            $errors[] = $folder;
        }
    }
}

// عرض النتائج
if (!empty($created)) {
    echo "<div class='success'>";
    echo "<h2>✅ تم إنشاء المجلدات التالية (" . count($created) . "):</h2>";
    echo "<ul>";
    foreach ($created as $folder) {
        echo "<li>✅ $folder</li>";
    }
    echo "</ul>";
    echo "</div>";
}

if (!empty($skipped)) {
    echo "<div class='info'>";
    echo "<h2>ℹ️ المجلدات الموجودة مسبقاً (" . count($skipped) . "):</h2>";
    echo "<ul>";
    foreach ($skipped as $folder) {
        echo "<li>➡️ $folder</li>";
    }
    echo "</ul>";
    echo "</div>";
}

if (!empty($errors)) {
    echo "<div class='error'>";
    echo "<h2>❌ فشل إنشاء المجلدات التالية (" . count($errors) . "):</h2>";
    echo "<ul>";
    foreach ($errors as $folder) {
        echo "<li>❌ $folder</li>";
    }
    echo "</ul>";
    echo "<p><strong>الحل:</strong> أنشئها يدوياً من File Manager</p>";
    echo "</div>";
}

// إنشاء ملفات .gitkeep
echo "<h2>📄 إنشاء ملفات .gitkeep</h2>";

$gitkeepFolders = [
    'storage/logs',
    'storage/cache',
    'storage/backups',
    'storage/temp',
    'public/uploads',
    'public/uploads/cars',
    'public/uploads/customers',
    'public/uploads/users',
    'public/uploads/documents',
];

$gitkeepCreated = 0;
foreach ($gitkeepFolders as $folder) {
    $gitkeepFile = $rootPath . '/' . $folder . '/.gitkeep';
    if (!file_exists($gitkeepFile)) {
        if (file_put_contents($gitkeepFile, '')) {
            $gitkeepCreated++;
        }
    }
}

echo "<div class='success'>✅ تم إنشاء $gitkeepCreated ملف .gitkeep</div>";

// إنشاء ملفات index.php للحماية
echo "<h2>🔒 إنشاء ملفات الحماية</h2>";

$protectionContent = "<?php\n// Access denied\nheader('HTTP/1.0 403 Forbidden');\nexit('Access Denied');\n";

$protectionFolders = [
    'storage',
    'storage/logs',
    'storage/cache',
    'storage/backups',
    'app/controllers',
    'app/models',
    'app/views',
    'core',
    'config',
];

$protectionCreated = 0;
foreach ($protectionFolders as $folder) {
    $indexFile = $rootPath . '/' . $folder . '/index.php';
    if (!file_exists($indexFile)) {
        if (file_put_contents($indexFile, $protectionContent)) {
            $protectionCreated++;
        }
    }
}

echo "<div class='success'>✅ تم إنشاء $protectionCreated ملف حماية</div>";

// فحص الصلاحيات
echo "<h2>🔐 فحص الصلاحيات</h2>";

$checkPermissions = [
    'storage' => 0777,
    'storage/logs' => 0777,
    'storage/cache' => 0777,
    'storage/backups' => 0777,
    'public/uploads' => 0777,
];

foreach ($checkPermissions as $folder => $permission) {
    $fullPath = $rootPath . '/' . $folder;
    if (is_dir($fullPath)) {
        $currentPerm = substr(sprintf('%o', fileperms($fullPath)), -4);
        $requiredPerm = substr(sprintf('%o', $permission), -4);
        
        if ($currentPerm == $requiredPerm) {
            echo "<div class='success'>✅ $folder: $currentPerm (صحيح)</div>";
        } else {
            echo "<div class='error'>⚠️ $folder: $currentPerm (يجب أن يكون $requiredPerm)</div>";
            // محاولة تغيير الصلاحية
            if (chmod($fullPath, $permission)) {
                echo "<div class='success'>✅ تم تصحيح صلاحيات $folder</div>";
            }
        }
    }
}

// الخطوات التالية
echo "<h2>📋 الخطوات التالية</h2>";
echo "<div class='info'>";
echo "<ol>";
echo "<li>✅ تم إنشاء جميع المجلدات المطلوبة</li>";
echo "<li>✅ تم إنشاء ملفات الحماية</li>";
echo "<li>✅ تم ضبط الصلاحيات</li>";
echo "<li>➡️ الآن قم بإنشاء ملف .env (انسخ من .env.example)</li>";
echo "<li>➡️ عدّل معلومات قاعدة البيانات في .env</li>";
echo "<li>➡️ افتح الموقع: <a href='/crs/'>افتح الموقع</a></li>";
echo "</ol>";
echo "</div>";

echo "<div class='success'>";
echo "<h3>✅ العملية اكتملت بنجاح!</h3>";
echo "<p>يمكنك الآن حذف هذا الملف (create-folders.php) من السيرفر.</p>";
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
