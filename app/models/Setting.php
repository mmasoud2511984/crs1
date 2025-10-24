<?php
/**
 * File: Setting.php
 * Path: /app/models/Setting.php
 * Purpose: نموذج إدارة إعدادات النظام مع نظام cache متقدم
 * Dependencies: Core\Model, Core\Database
 * Phase: Phase 3 - Settings & Administration
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

/**
 * Class Setting
 * 
 * نموذج إدارة إعدادات النظام
 * - يدعم أنواع مختلفة من الإعدادات (text, number, boolean, json)
 * - نظام cache متقدم لتحسين الأداء
 * - CRUD operations كاملة
 * - Helper methods للوصول السريع للإعدادات
 * 
 * @package App\Models
 */
class Setting extends Model
{
    /**
     * اسم الجدول في قاعدة البيانات
     */
    protected string $table = 'settings';
    
    /**
     * الحقول المسموح بها للإدخال الجماعي
     */
    protected array $fillable = [
        'setting_key',
        'setting_value',
        'setting_type',
        'category',
        'description',
        'is_public',
        'updated_by'
    ];
    
    /**
     * مسار ملف الـ cache
     */
    private const CACHE_FILE = __DIR__ . '/../../storage/cache/settings.json';
    
    /**
     * مدة الـ cache بالثواني (1 ساعة)
     */
    private const CACHE_DURATION = 3600;
    
    /**
     * Cache في الذاكرة أثناء تشغيل السكريبت
     */
    private static ?array $runtimeCache = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        // تسجيل الملف في FileTracker
        FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 3');
    }

    // ========================================
    // CRUD Operations
    // ========================================

    /**
     * الحصول على جميع الإعدادات
     * 
     * @param string|null $category فئة معينة (اختياري)
     * @param bool $publicOnly عرض الإعدادات العامة فقط
     * @return array
     */
    public function getAll(?string $category = null, bool $publicOnly = false): array
    {
        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if ($category) {
            $query .= " AND category = ?";
            $params[] = $category;
        }
        
        if ($publicOnly) {
            $query .= " AND is_public = 1";
        }
        
        $query .= " ORDER BY category, setting_key";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * الحصول على إعداد واحد حسب المفتاح
     * 
     * @param string $key مفتاح الإعداد
     * @return array|null
     */
    public function getByKey(string $key): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE setting_key = ?
        ");
        $stmt->execute([$key]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * إنشاء إعداد جديد أو تحديثه إذا كان موجوداً
     * 
     * @param array $data بيانات الإعداد
     * @return bool
     */
    public function createOrUpdate(array $data): bool
    {
        // التحقق من وجود الإعداد
        $existing = $this->getByKey($data['setting_key']);
        
        if ($existing) {
            // تحديث الإعداد الموجود
            return $this->updateByKey($data['setting_key'], $data);
        } else {
            // إنشاء إعداد جديد
            return $this->create($data);
        }
    }

    /**
     * تحديث إعداد حسب المفتاح
     * 
     * @param string $key مفتاح الإعداد
     * @param array $data البيانات الجديدة
     * @return bool
     */
    public function updateByKey(string $key, array $data): bool
    {
        $fields = [];
        $params = [];
        
        // بناء جملة UPDATE
        foreach ($data as $field => $value) {
            if (in_array($field, $this->fillable) && $field !== 'setting_key') {
                $fields[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        // إضافة updated_at
        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        
        // إضافة المفتاح في النهاية للـ WHERE
        $params[] = $key;
        
        $query = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE setting_key = ?";
        
        try {
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($params);
            
            // مسح الـ cache بعد التحديث
            if ($result) {
                $this->clearCache();
            }
            
            return $result;
        } catch (\PDOException $e) {
            error_log("Setting update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف إعداد حسب المفتاح
     * 
     * @param string $key مفتاح الإعداد
     * @return bool
     */
    public function deleteByKey(string $key): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE setting_key = ?");
            $result = $stmt->execute([$key]);
            
            // مسح الـ cache بعد الحذف
            if ($result) {
                $this->clearCache();
            }
            
            return $result;
        } catch (\PDOException $e) {
            error_log("Setting delete error: " . $e->getMessage());
            return false;
        }
    }

    // ========================================
    // Helper Methods - الطرق المساعدة
    // ========================================

    /**
     * الحصول على قيمة إعداد محدد مع دعم القيمة الافتراضية
     * 
     * @param string $key مفتاح الإعداد
     * @param mixed $default القيمة الافتراضية
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        // محاولة الحصول من الـ cache أولاً
        $allSettings = $this->getAllCached();
        
        if (isset($allSettings[$key])) {
            return $this->castValue(
                $allSettings[$key]['value'],
                $allSettings[$key]['type']
            );
        }
        
        return $default;
    }

    /**
     * تعيين قيمة إعداد محدد
     * 
     * @param string $key مفتاح الإعداد
     * @param mixed $value القيمة
     * @param string $type نوع البيانات
     * @param string $category الفئة
     * @return bool
     */
    public function set(string $key, $value, string $type = 'text', string $category = 'general'): bool
    {
        // تحويل القيمة حسب النوع
        $processedValue = $this->processValue($value, $type);
        
        $data = [
            'setting_key' => $key,
            'setting_value' => $processedValue,
            'setting_type' => $type,
            'category' => $category
        ];
        
        return $this->createOrUpdate($data);
    }

    /**
     * الحصول على جميع الإعدادات حسب الفئة
     * 
     * @param string $category الفئة
     * @return array
     */
    public function getByCategory(string $category): array
    {
        $allSettings = $this->getAllCached();
        $result = [];
        
        foreach ($allSettings as $key => $setting) {
            if ($setting['category'] === $category) {
                $result[$key] = $this->castValue($setting['value'], $setting['type']);
            }
        }
        
        return $result;
    }

    /**
     * التحقق من وجود إعداد محدد
     * 
     * @param string $key مفتاح الإعداد
     * @return bool
     */
    public function has(string $key): bool
    {
        $allSettings = $this->getAllCached();
        return isset($allSettings[$key]);
    }

    /**
     * حذف إعداد (alias لـ deleteByKey)
     * 
     * @param string $key مفتاح الإعداد
     * @return bool
     */
    public function remove(string $key): bool
    {
        return $this->deleteByKey($key);
    }

    /**
     * تحديث عدة إعدادات دفعة واحدة
     * 
     * @param array $settings مصفوفة من الإعدادات [key => value]
     * @param string $category الفئة (اختياري)
     * @return bool
     */
    public function setMultiple(array $settings, string $category = 'general'): bool
    {
        try {
            $this->db->beginTransaction();
            
            foreach ($settings as $key => $value) {
                // تحديد نوع البيانات تلقائياً
                $type = $this->guessType($value);
                
                if (!$this->set($key, $value, $type, $category)) {
                    throw new \Exception("Failed to set {$key}");
                }
            }
            
            $this->db->commit();
            $this->clearCache();
            
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Setting batch update error: " . $e->getMessage());
            return false;
        }
    }

    // ========================================
    // Cache Management - إدارة الـ Cache
    // ========================================

    /**
     * الحصول على جميع الإعدادات من الـ cache
     * 
     * @return array
     */
    private function getAllCached(): array
    {
        // التحقق من الـ runtime cache أولاً
        if (self::$runtimeCache !== null) {
            return self::$runtimeCache;
        }
        
        // التحقق من صلاحية الـ cache في الملف
        if ($this->isCacheValid()) {
            self::$runtimeCache = $this->loadCache();
            return self::$runtimeCache;
        }
        
        // إعادة بناء الـ cache
        return $this->rebuildCache();
    }

    /**
     * التحقق من صلاحية الـ cache
     * 
     * @return bool
     */
    private function isCacheValid(): bool
    {
        if (!file_exists(self::CACHE_FILE)) {
            return false;
        }
        
        $cacheAge = time() - filemtime(self::CACHE_FILE);
        return $cacheAge < self::CACHE_DURATION;
    }

    /**
     * تحميل الـ cache من الملف
     * 
     * @return array
     */
    private function loadCache(): array
    {
        $content = file_get_contents(self::CACHE_FILE);
        return json_decode($content, true) ?? [];
    }

    /**
     * إعادة بناء الـ cache
     * 
     * @return array
     */
    private function rebuildCache(): array
    {
        $stmt = $this->db->query("SELECT setting_key, setting_value, setting_type, category FROM {$this->table}");
        $settings = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $cache = [];
        foreach ($settings as $setting) {
            $cache[$setting['setting_key']] = [
                'value' => $setting['setting_value'],
                'type' => $setting['setting_type'],
                'category' => $setting['category']
            ];
        }
        
        // حفظ الـ cache في الملف
        $this->saveCache($cache);
        
        // حفظ في الـ runtime cache
        self::$runtimeCache = $cache;
        
        return $cache;
    }

    /**
     * حفظ الـ cache في الملف
     * 
     * @param array $data البيانات المراد حفظها
     * @return void
     */
    private function saveCache(array $data): void
    {
        $cacheDir = dirname(self::CACHE_FILE);
        
        // إنشاء المجلد إذا لم يكن موجوداً
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        file_put_contents(self::CACHE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * مسح الـ cache
     * 
     * @return void
     */
    public function clearCache(): void
    {
        // مسح runtime cache
        self::$runtimeCache = null;
        
        // حذف ملف الـ cache
        if (file_exists(self::CACHE_FILE)) {
            unlink(self::CACHE_FILE);
        }
    }

    // ========================================
    // Type Handling - معالجة الأنواع
    // ========================================

    /**
     * تحويل القيمة حسب نوعها قبل الحفظ
     * 
     * @param mixed $value القيمة
     * @param string $type النوع
     * @return string
     */
    private function processValue($value, string $type): string
    {
        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
                
            case 'integer':
            case 'number':
                return (string)intval($value);
                
            case 'float':
            case 'decimal':
                return (string)floatval($value);
                
            case 'json':
            case 'array':
                return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                
            case 'text':
            case 'string':
            default:
                return (string)$value;
        }
    }

    /**
     * تحويل القيمة المخزنة إلى نوعها الأصلي
     * 
     * @param string $value القيمة المخزنة
     * @param string $type النوع
     * @return mixed
     */
    private function castValue(string $value, string $type)
    {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            case 'integer':
            case 'number':
                return intval($value);
                
            case 'float':
            case 'decimal':
                return floatval($value);
                
            case 'json':
            case 'array':
                return json_decode($value, true);
                
            case 'text':
            case 'string':
            default:
                return $value;
        }
    }

    /**
     * تخمين نوع البيانات تلقائياً
     * 
     * @param mixed $value القيمة
     * @return string
     */
    private function guessType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (is_array($value)) {
            return 'json';
        } else {
            return 'text';
        }
    }

    // ========================================
    // Category Management - إدارة الفئات
    // ========================================

    /**
     * الحصول على جميع الفئات المتاحة
     * 
     * @return array
     */
    public function getCategories(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT category 
            FROM {$this->table} 
            ORDER BY category
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * عد الإعدادات في فئة معينة
     * 
     * @param string $category الفئة
     * @return int
     */
    public function countByCategory(string $category): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM {$this->table} 
            WHERE category = ?
        ");
        $stmt->execute([$category]);
        
        return (int)$stmt->fetchColumn();
    }

    // ========================================
    // Audit & History - التدقيق والسجل
    // ========================================

    /**
     * تسجيل تغيير في الإعدادات للـ Audit Log
     * 
     * @param string $key مفتاح الإعداد
     * @param mixed $oldValue القيمة القديمة
     * @param mixed $newValue القيمة الجديدة
     * @param int|null $userId معرف المستخدم
     * @return bool
     */
    public function logChange(string $key, $oldValue, $newValue, ?int $userId = null): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_log (
                    user_id, action, table_name, record_id,
                    old_values, new_values, ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $userId,
                'setting_update',
                $this->table,
                null,
                json_encode(['key' => $key, 'value' => $oldValue], JSON_UNESCAPED_UNICODE),
                json_encode(['key' => $key, 'value' => $newValue], JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (\PDOException $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تصدير جميع الإعدادات إلى JSON
     * 
     * @param string|null $category فئة معينة (اختياري)
     * @return string
     */
    public function exportToJson(?string $category = null): string
    {
        $settings = $this->getAll($category);
        
        $export = [];
        foreach ($settings as $setting) {
            $export[$setting['setting_key']] = [
                'value' => $setting['setting_value'],
                'type' => $setting['setting_type'],
                'category' => $setting['category'],
                'description' => $setting['description']
            ];
        }
        
        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * استيراد إعدادات من JSON
     * 
     * @param string $json البيانات بصيغة JSON
     * @return bool
     */
    public function importFromJson(string $json): bool
    {
        try {
            $data = json_decode($json, true);
            
            if (!is_array($data)) {
                return false;
            }
            
            $this->db->beginTransaction();
            
            foreach ($data as $key => $setting) {
                $this->createOrUpdate([
                    'setting_key' => $key,
                    'setting_value' => $setting['value'],
                    'setting_type' => $setting['type'] ?? 'text',
                    'category' => $setting['category'] ?? 'general',
                    'description' => $setting['description'] ?? null
                ]);
            }
            
            $this->db->commit();
            $this->clearCache();
            
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Settings import error: " . $e->getMessage());
            return false;
        }
    }
}
