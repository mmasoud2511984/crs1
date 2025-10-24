<?php
/**
 * File: Language.php
 * Path: /app/models/Language.php
 * Purpose: نموذج إدارة اللغات في النظام
 * Dependencies: Core\Model, Core\Database
 * Phase: Phase 3 - Settings & Administration
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

/**
 * Class Language
 * 
 * نموذج إدارة اللغات
 * - إدارة اللغات المتاحة في النظام
 * - تفعيل/تعطيل اللغات
 * - تحديد اللغة الافتراضية
 * - إدارة إعدادات اللغة (التاريخ، العملة، الاتجاه)
 * 
 * @package App\Models
 */
class Language extends Model
{
    /**
     * اسم الجدول في قاعدة البيانات
     */
    protected string $table = 'languages';
    
    /**
     * الحقول المسموح بها للإدخال الجماعي
     */
    protected array $fillable = [
        'name',
        'code',
        'direction',
        'currency_symbol',
        'currency_code',
        'date_format',
        'time_format',
        'is_default',
        'is_active',
        'flag_icon'
    ];
    
    /**
     * مسار ملف cache اللغات
     */
    private const CACHE_FILE = __DIR__ . '/../../storage/cache/languages.json';
    
    /**
     * Cache في الذاكرة
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
     * الحصول على جميع اللغات
     * 
     * @param bool $activeOnly اللغات النشطة فقط
     * @return array
     */
    public function getAll(bool $activeOnly = false): array
    {
        $query = "SELECT * FROM {$this->table}";
        
        if ($activeOnly) {
            $query .= " WHERE is_active = 1";
        }
        
        $query .= " ORDER BY is_default DESC, name ASC";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * الحصول على لغة حسب الكود
     * 
     * @param string $code كود اللغة (مثل: ar, en)
     * @return array|null
     */
    public function getByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE code = ?
        ");
        $stmt->execute([$code]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * الحصول على اللغة الافتراضية
     * 
     * @return array|null
     */
    public function getDefault(): ?array
    {
        $stmt = $this->db->query("
            SELECT * FROM {$this->table} 
            WHERE is_default = 1 
            LIMIT 1
        ");
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * إنشاء لغة جديدة
     * 
     * @param array $data بيانات اللغة
     * @return int|false معرف اللغة الجديدة أو false
     */
    public function create(array $data)
    {
        // التحقق من عدم وجود لغة بنفس الكود
        if ($this->getByCode($data['code'])) {
            return false;
        }
        
        $result = parent::create($data);
        
        if ($result) {
            $this->clearCache();
        }
        
        return $result;
    }

    /**
     * تحديث لغة
     * 
     * @param int $id معرف اللغة
     * @param array $data البيانات الجديدة
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        // إذا تم تعيين اللغة كافتراضية، إزالة الافتراضية من الباقي
        if (isset($data['is_default']) && $data['is_default']) {
            $this->removeDefaultFromAll();
        }
        
        $result = parent::update($id, $data);
        
        if ($result) {
            $this->clearCache();
        }
        
        return $result;
    }

    /**
     * حذف لغة
     * 
     * @param int $id معرف اللغة
     * @return bool
     */
    public function delete(int $id): bool
    {
        // التحقق من أن اللغة ليست افتراضية
        $language = $this->findById($id);
        
        if (!$language) {
            return false;
        }
        
        if ($language['is_default']) {
            error_log("Cannot delete default language");
            return false;
        }
        
        $result = parent::delete($id);
        
        if ($result) {
            $this->clearCache();
        }
        
        return $result;
    }

    // ========================================
    // Language Management - إدارة اللغات
    // ========================================

    /**
     * تفعيل لغة
     * 
     * @param int $id معرف اللغة
     * @return bool
     */
    public function activate(int $id): bool
    {
        return $this->update($id, ['is_active' => 1]);
    }

    /**
     * تعطيل لغة
     * 
     * @param int $id معرف اللغة
     * @return bool
     */
    public function deactivate(int $id): bool
    {
        // التحقق من أن اللغة ليست افتراضية
        $language = $this->findById($id);
        
        if (!$language) {
            return false;
        }
        
        if ($language['is_default']) {
            error_log("Cannot deactivate default language");
            return false;
        }
        
        return $this->update($id, ['is_active' => 0]);
    }

    /**
     * تعيين لغة كافتراضية
     * 
     * @param int $id معرف اللغة
     * @return bool
     */
    public function setAsDefault(int $id): bool
    {
        try {
            $this->db->beginTransaction();
            
            // إزالة الافتراضية من جميع اللغات
            $this->removeDefaultFromAll();
            
            // تعيين اللغة الجديدة كافتراضية وتفعيلها
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET is_default = 1, is_active = 1 
                WHERE id = ?
            ");
            $result = $stmt->execute([$id]);
            
            $this->db->commit();
            $this->clearCache();
            
            return $result;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            error_log("Set default language error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إزالة الافتراضية من جميع اللغات
     * 
     * @return bool
     */
    private function removeDefaultFromAll(): bool
    {
        try {
            $stmt = $this->db->query("UPDATE {$this->table} SET is_default = 0");
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Remove default error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على اللغات النشطة فقط
     * 
     * @return array
     */
    public function getActive(): array
    {
        return $this->getAll(true);
    }

    /**
     * الحصول على عدد اللغات النشطة
     * 
     * @return int
     */
    public function countActive(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*) 
            FROM {$this->table} 
            WHERE is_active = 1
        ");
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * التحقق من وجود لغة بكود معين
     * 
     * @param string $code كود اللغة
     * @return bool
     */
    public function exists(string $code): bool
    {
        return $this->getByCode($code) !== null;
    }

    // ========================================
    // Helper Methods - الطرق المساعدة
    // ========================================

    /**
     * الحصول على جميع اللغات كخيارات للقوائم المنسدلة
     * 
     * @param bool $activeOnly اللغات النشطة فقط
     * @return array [code => name]
     */
    public function getAsOptions(bool $activeOnly = true): array
    {
        $languages = $this->getAll($activeOnly);
        $options = [];
        
        foreach ($languages as $language) {
            $options[$language['code']] = $language['name'];
        }
        
        return $options;
    }

    /**
     * الحصول على اتجاه لغة معينة
     * 
     * @param string $code كود اللغة
     * @return string rtl أو ltr
     */
    public function getDirection(string $code): string
    {
        $language = $this->getByCode($code);
        return $language ? $language['direction'] : 'ltr';
    }

    /**
     * الحصول على رمز العملة للغة معينة
     * 
     * @param string $code كود اللغة
     * @return string
     */
    public function getCurrencySymbol(string $code): string
    {
        $language = $this->getByCode($code);
        return $language ? ($language['currency_symbol'] ?? '$') : '$';
    }

    /**
     * الحصول على كود العملة للغة معينة
     * 
     * @param string $code كود اللغة
     * @return string
     */
    public function getCurrencyCode(string $code): string
    {
        $language = $this->getByCode($code);
        return $language ? ($language['currency_code'] ?? 'USD') : 'USD';
    }

    /**
     * التحقق من أن اللغة عربية
     * 
     * @param string $code كود اللغة
     * @return bool
     */
    public function isRTL(string $code): bool
    {
        return $this->getDirection($code) === 'rtl';
    }

    // ========================================
    // Cache Management - إدارة الـ Cache
    // ========================================

    /**
     * الحصول على جميع اللغات من الـ cache
     * 
     * @param bool $activeOnly اللغات النشطة فقط
     * @return array
     */
    public function getAllCached(bool $activeOnly = false): array
    {
        // محاولة الحصول من runtime cache
        if (self::$runtimeCache !== null) {
            $languages = self::$runtimeCache;
        } 
        // محاولة الحصول من file cache
        elseif (file_exists(self::CACHE_FILE)) {
            $content = file_get_contents(self::CACHE_FILE);
            $languages = json_decode($content, true) ?? [];
            self::$runtimeCache = $languages;
        } 
        // إعادة بناء الـ cache
        else {
            $languages = $this->rebuildCache();
        }
        
        // تصفية اللغات النشطة إذا طلب ذلك
        if ($activeOnly) {
            return array_filter($languages, function($lang) {
                return $lang['is_active'];
            });
        }
        
        return $languages;
    }

    /**
     * إعادة بناء الـ cache
     * 
     * @return array
     */
    private function rebuildCache(): array
    {
        $languages = $this->getAll();
        
        // حفظ في ملف
        $cacheDir = dirname(self::CACHE_FILE);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        file_put_contents(
            self::CACHE_FILE, 
            json_encode($languages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        // حفظ في runtime cache
        self::$runtimeCache = $languages;
        
        return $languages;
    }

    /**
     * مسح الـ cache
     * 
     * @return void
     */
    public function clearCache(): void
    {
        self::$runtimeCache = null;
        
        if (file_exists(self::CACHE_FILE)) {
            unlink(self::CACHE_FILE);
        }
    }

    // ========================================
    // Validation - التحقق من الصحة
    // ========================================

    /**
     * التحقق من صحة بيانات اللغة
     * 
     * @param array $data البيانات
     * @param bool $isUpdate هل هو تحديث؟
     * @return array أخطاء التحقق
     */
    public function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];
        
        // التحقق من الاسم
        if (empty($data['name'])) {
            $errors['name'] = 'اسم اللغة مطلوب';
        }
        
        // التحقق من الكود
        if (!$isUpdate) {
            if (empty($data['code'])) {
                $errors['code'] = 'كود اللغة مطلوب';
            } elseif (!preg_match('/^[a-z]{2}$/', $data['code'])) {
                $errors['code'] = 'كود اللغة يجب أن يكون حرفين صغيرين (مثل: ar, en)';
            } elseif ($this->exists($data['code'])) {
                $errors['code'] = 'كود اللغة موجود مسبقاً';
            }
        }
        
        // التحقق من الاتجاه
        if (!empty($data['direction']) && !in_array($data['direction'], ['ltr', 'rtl'])) {
            $errors['direction'] = 'الاتجاه يجب أن يكون ltr أو rtl';
        }
        
        // التحقق من كود العملة
        if (!empty($data['currency_code']) && strlen($data['currency_code']) !== 3) {
            $errors['currency_code'] = 'كود العملة يجب أن يكون 3 أحرف (مثل: SAR, USD)';
        }
        
        return $errors;
    }

    // ========================================
    // Statistics - الإحصائيات
    // ========================================

    /**
     * الحصول على إحصائيات اللغات
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_default = 1 THEN 1 ELSE 0 END) as default_count,
                SUM(CASE WHEN direction = 'rtl' THEN 1 ELSE 0 END) as rtl_count,
                SUM(CASE WHEN direction = 'ltr' THEN 1 ELSE 0 END) as ltr_count
            FROM {$this->table}
        ");
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * الحصول على اللغات مع عدد الترجمات لكل لغة
     * 
     * @return array
     */
    public function getWithTranslationCount(): array
    {
        $stmt = $this->db->query("
            SELECT 
                l.*,
                COUNT(t.id) as translation_count
            FROM {$this->table} l
            LEFT JOIN translations t ON l.code = t.lang_code
            GROUP BY l.id
            ORDER BY l.is_default DESC, l.name ASC
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
