<?php
/**
 * File: Translation.php
 * Path: /app/models/Translation.php
 * Purpose: نموذج إدارة الترجمات في النظام
 * Dependencies: Core\Model, Core\Database
 * Phase: Phase 3 - Settings & Administration
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

/**
 * Class Translation
 * 
 * نموذج إدارة الترجمات
 * - إدارة مفاتيح الترجمة وقيمها
 * - دعم تصنيف الترجمات
 * - استيراد وتصدير الترجمات
 * - نظام cache متقدم للأداء
 * - البحث والتصفية
 * 
 * @package App\Models
 */
class Translation extends Model
{
    /**
     * اسم الجدول في قاعدة البيانات
     */
    protected string $table = 'translations';
    
    /**
     * الحقول المسموح بها للإدخال الجماعي
     */
    protected array $fillable = [
        'lang_code',
        'translation_key',
        'translation_value',
        'category'
    ];
    
    /**
     * مسار مجلد cache الترجمات
     */
    private const CACHE_DIR = __DIR__ . '/../../storage/cache/translations/';
    
    /**
     * مدة الـ cache بالثواني (24 ساعة)
     */
    private const CACHE_DURATION = 86400;
    
    /**
     * Cache في الذاكرة لكل لغة
     */
    private static array $runtimeCache = [];

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
     * الحصول على جميع الترجمات
     * 
     * @param string|null $langCode لغة معينة
     * @param string|null $category فئة معينة
     * @return array
     */
    public function getAll(?string $langCode = null, ?string $category = null): array
    {
        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if ($langCode) {
            $query .= " AND lang_code = ?";
            $params[] = $langCode;
        }
        
        if ($category) {
            $query .= " AND category = ?";
            $params[] = $category;
        }
        
        $query .= " ORDER BY category, translation_key";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * الحصول على ترجمة محددة
     * 
     * @param string $langCode كود اللغة
     * @param string $key المفتاح
     * @return array|null
     */
    public function getByKey(string $langCode, string $key): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE lang_code = ? AND translation_key = ?
        ");
        $stmt->execute([$langCode, $key]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * إنشاء ترجمة جديدة أو تحديثها
     * 
     * @param array $data بيانات الترجمة
     * @return bool
     */
    public function createOrUpdate(array $data): bool
    {
        $existing = $this->getByKey($data['lang_code'], $data['translation_key']);
        
        if ($existing) {
            return $this->updateTranslation(
                $data['lang_code'],
                $data['translation_key'],
                $data['translation_value'],
                $data['category'] ?? 'general'
            );
        } else {
            return (bool)$this->create($data);
        }
    }

    /**
     * تحديث ترجمة محددة
     * 
     * @param string $langCode كود اللغة
     * @param string $key المفتاح
     * @param string $value القيمة الجديدة
     * @param string|null $category الفئة
     * @return bool
     */
    public function updateTranslation(string $langCode, string $key, string $value, ?string $category = null): bool
    {
        try {
            $query = "UPDATE {$this->table} 
                     SET translation_value = ?, 
                         updated_at = CURRENT_TIMESTAMP";
            $params = [$value];
            
            if ($category !== null) {
                $query .= ", category = ?";
                $params[] = $category;
            }
            
            $query .= " WHERE lang_code = ? AND translation_key = ?";
            $params[] = $langCode;
            $params[] = $key;
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                $this->clearCache($langCode);
            }
            
            return $result;
        } catch (\PDOException $e) {
            error_log("Translation update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف ترجمة
     * 
     * @param string $langCode كود اللغة
     * @param string $key المفتاح
     * @return bool
     */
    public function deleteTranslation(string $langCode, string $key): bool
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM {$this->table} 
                WHERE lang_code = ? AND translation_key = ?
            ");
            $result = $stmt->execute([$langCode, $key]);
            
            if ($result) {
                $this->clearCache($langCode);
            }
            
            return $result;
        } catch (\PDOException $e) {
            error_log("Translation delete error: " . $e->getMessage());
            return false;
        }
    }

    // ========================================
    // Translation Management - إدارة الترجمات
    // ========================================

    /**
     * الحصول على جميع ترجمات لغة معينة كمصفوفة [key => value]
     * 
     * @param string $langCode كود اللغة
     * @return array
     */
    public function getLanguageTranslations(string $langCode): array
    {
        // محاولة الحصول من الـ cache
        $cached = $this->getFromCache($langCode);
        if ($cached !== null) {
            return $cached;
        }
        
        // الحصول من قاعدة البيانات
        $stmt = $this->db->prepare("
            SELECT translation_key, translation_value 
            FROM {$this->table} 
            WHERE lang_code = ?
        ");
        $stmt->execute([$langCode]);
        
        $translations = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $translations[$row['translation_key']] = $row['translation_value'];
        }
        
        // حفظ في الـ cache
        $this->saveToCache($langCode, $translations);
        
        return $translations;
    }

    /**
     * نسخ ترجمات من لغة إلى أخرى
     * 
     * @param string $fromLang اللغة المصدر
     * @param string $toLang اللغة الهدف
     * @param bool $overwrite الكتابة فوق الترجمات الموجودة
     * @return int عدد الترجمات المنسوخة
     */
    public function copyTranslations(string $fromLang, string $toLang, bool $overwrite = false): int
    {
        try {
            $this->db->beginTransaction();
            
            $sourceTranslations = $this->getAll($fromLang);
            $copiedCount = 0;
            
            foreach ($sourceTranslations as $translation) {
                $exists = $this->getByKey($toLang, $translation['translation_key']);
                
                if (!$exists || $overwrite) {
                    $result = $this->createOrUpdate([
                        'lang_code' => $toLang,
                        'translation_key' => $translation['translation_key'],
                        'translation_value' => $translation['translation_value'],
                        'category' => $translation['category']
                    ]);
                    
                    if ($result) {
                        $copiedCount++;
                    }
                }
            }
            
            $this->db->commit();
            $this->clearCache($toLang);
            
            return $copiedCount;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Copy translations error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * البحث في الترجمات
     * 
     * @param string $search نص البحث
     * @param string|null $langCode لغة معينة
     * @param string|null $category فئة معينة
     * @return array
     */
    public function search(string $search, ?string $langCode = null, ?string $category = null): array
    {
        $query = "SELECT * FROM {$this->table} 
                 WHERE (translation_key LIKE ? OR translation_value LIKE ?)";
        $params = ["%$search%", "%$search%"];
        
        if ($langCode) {
            $query .= " AND lang_code = ?";
            $params[] = $langCode;
        }
        
        if ($category) {
            $query .= " AND category = ?";
            $params[] = $category;
        }
        
        $query .= " ORDER BY category, translation_key LIMIT 100";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * الحصول على المفاتيح المفقودة (موجودة في لغة وغير موجودة في أخرى)
     * 
     * @param string $referenceLang اللغة المرجعية
     * @param string $targetLang اللغة المستهدفة
     * @return array
     */
    public function getMissingKeys(string $referenceLang, string $targetLang): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT t1.translation_key, t1.category
            FROM {$this->table} t1
            LEFT JOIN {$this->table} t2 
                ON t1.translation_key = t2.translation_key 
                AND t2.lang_code = ?
            WHERE t1.lang_code = ? 
                AND t2.id IS NULL
            ORDER BY t1.category, t1.translation_key
        ");
        $stmt->execute([$targetLang, $referenceLang]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * الحصول على الترجمات الفارغة
     * 
     * @param string $langCode كود اللغة
     * @return array
     */
    public function getEmpty(string $langCode): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE lang_code = ? 
                AND (translation_value = '' OR translation_value IS NULL)
            ORDER BY category, translation_key
        ");
        $stmt->execute([$langCode]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ========================================
    // Categories - الفئات
    // ========================================

    /**
     * الحصول على جميع الفئات
     * 
     * @param string|null $langCode لغة معينة
     * @return array
     */
    public function getCategories(?string $langCode = null): array
    {
        $query = "SELECT DISTINCT category FROM {$this->table}";
        $params = [];
        
        if ($langCode) {
            $query .= " WHERE lang_code = ?";
            $params[] = $langCode;
        }
        
        $query .= " ORDER BY category";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * عد الترجمات في فئة معينة
     * 
     * @param string $category الفئة
     * @param string|null $langCode لغة معينة
     * @return int
     */
    public function countByCategory(string $category, ?string $langCode = null): int
    {
        $query = "SELECT COUNT(*) FROM {$this->table} WHERE category = ?";
        $params = [$category];
        
        if ($langCode) {
            $query .= " AND lang_code = ?";
            $params[] = $langCode;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn();
    }

    // ========================================
    // Import/Export - الاستيراد والتصدير
    // ========================================

    /**
     * تصدير ترجمات لغة إلى JSON
     * 
     * @param string $langCode كود اللغة
     * @param bool $grouped تجميع حسب الفئة
     * @return string
     */
    public function exportToJson(string $langCode, bool $grouped = false): string
    {
        $translations = $this->getAll($langCode);
        
        if (!$grouped) {
            // تصدير بسيط [key => value]
            $export = [];
            foreach ($translations as $translation) {
                $export[$translation['translation_key']] = $translation['translation_value'];
            }
        } else {
            // تصدير مجمع حسب الفئة
            $export = [];
            foreach ($translations as $translation) {
                $category = $translation['category'] ?? 'general';
                if (!isset($export[$category])) {
                    $export[$category] = [];
                }
                $export[$category][$translation['translation_key']] = $translation['translation_value'];
            }
        }
        
        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * تصدير ترجمات لغة إلى PHP Array
     * 
     * @param string $langCode كود اللغة
     * @return string
     */
    public function exportToPhp(string $langCode): string
    {
        $translations = $this->getLanguageTranslations($langCode);
        
        $php = "<?php\n\n";
        $php .= "/**\n";
        $php .= " * Translations for language: {$langCode}\n";
        $php .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $php .= " */\n\n";
        $php .= "return [\n";
        
        foreach ($translations as $key => $value) {
            $key = addslashes($key);
            $value = addslashes($value);
            $php .= "    '{$key}' => '{$value}',\n";
        }
        
        $php .= "];\n";
        
        return $php;
    }

    /**
     * استيراد ترجمات من JSON
     * 
     * @param string $langCode كود اللغة
     * @param string $json بيانات JSON
     * @param bool $overwrite الكتابة فوق الموجود
     * @return array [imported, updated, errors]
     */
    public function importFromJson(string $langCode, string $json, bool $overwrite = false): array
    {
        $result = ['imported' => 0, 'updated' => 0, 'errors' => []];
        
        try {
            $data = json_decode($json, true);
            
            if (!is_array($data)) {
                $result['errors'][] = 'Invalid JSON format';
                return $result;
            }
            
            $this->db->beginTransaction();
            
            // إذا كانت البيانات مجمعة حسب الفئة
            if (isset($data['general']) || isset($data['admin']) || isset($data['frontend'])) {
                foreach ($data as $category => $translations) {
                    if (!is_array($translations)) continue;
                    
                    foreach ($translations as $key => $value) {
                        $this->importSingleTranslation(
                            $langCode, $key, $value, $category, $overwrite, $result
                        );
                    }
                }
            } else {
                // بيانات بسيطة [key => value]
                foreach ($data as $key => $value) {
                    $this->importSingleTranslation(
                        $langCode, $key, $value, 'general', $overwrite, $result
                    );
                }
            }
            
            $this->db->commit();
            $this->clearCache($langCode);
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }

    /**
     * استيراد ترجمة واحدة (دالة مساعدة)
     * 
     * @param string $langCode كود اللغة
     * @param string $key المفتاح
     * @param string $value القيمة
     * @param string $category الفئة
     * @param bool $overwrite الكتابة فوق الموجود
     * @param array &$result نتيجة الاستيراد
     * @return bool
     */
    private function importSingleTranslation(
        string $langCode, 
        string $key, 
        string $value, 
        string $category, 
        bool $overwrite,
        array &$result
    ): bool {
        $existing = $this->getByKey($langCode, $key);
        
        if (!$existing) {
            if ($this->create([
                'lang_code' => $langCode,
                'translation_key' => $key,
                'translation_value' => $value,
                'category' => $category
            ])) {
                $result['imported']++;
                return true;
            }
        } elseif ($overwrite) {
            if ($this->updateTranslation($langCode, $key, $value, $category)) {
                $result['updated']++;
                return true;
            }
        }
        
        return false;
    }

    // ========================================
    // Cache Management - إدارة الـ Cache
    // ========================================

    /**
     * الحصول من الـ cache
     * 
     * @param string $langCode كود اللغة
     * @return array|null
     */
    private function getFromCache(string $langCode): ?array
    {
        // التحقق من runtime cache
        if (isset(self::$runtimeCache[$langCode])) {
            return self::$runtimeCache[$langCode];
        }
        
        // التحقق من file cache
        $cacheFile = self::CACHE_DIR . $langCode . '.json';
        
        if (file_exists($cacheFile)) {
            $cacheAge = time() - filemtime($cacheFile);
            
            if ($cacheAge < self::CACHE_DURATION) {
                $content = file_get_contents($cacheFile);
                $translations = json_decode($content, true);
                
                if ($translations) {
                    self::$runtimeCache[$langCode] = $translations;
                    return $translations;
                }
            }
        }
        
        return null;
    }

    /**
     * حفظ في الـ cache
     * 
     * @param string $langCode كود اللغة
     * @param array $translations الترجمات
     * @return void
     */
    private function saveToCache(string $langCode, array $translations): void
    {
        // حفظ في runtime cache
        self::$runtimeCache[$langCode] = $translations;
        
        // حفظ في file cache
        $cacheDir = self::CACHE_DIR;
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheFile = $cacheDir . $langCode . '.json';
        file_put_contents(
            $cacheFile,
            json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * مسح الـ cache
     * 
     * @param string|null $langCode لغة معينة أو الكل
     * @return void
     */
    public function clearCache(?string $langCode = null): void
    {
        if ($langCode) {
            // مسح لغة محددة
            unset(self::$runtimeCache[$langCode]);
            
            $cacheFile = self::CACHE_DIR . $langCode . '.json';
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        } else {
            // مسح جميع اللغات
            self::$runtimeCache = [];
            
            if (is_dir(self::CACHE_DIR)) {
                $files = glob(self::CACHE_DIR . '*.json');
                foreach ($files as $file) {
                    unlink($file);
                }
            }
        }
    }

    // ========================================
    // Statistics - الإحصائيات
    // ========================================

    /**
     * الحصول على إحصائيات الترجمات
     * 
     * @param string|null $langCode لغة معينة
     * @return array
     */
    public function getStatistics(?string $langCode = null): array
    {
        $query = "SELECT 
                    COUNT(*) as total,
                    COUNT(DISTINCT category) as categories,
                    COUNT(CASE WHEN translation_value = '' OR translation_value IS NULL THEN 1 END) as empty,
                    COUNT(DISTINCT lang_code) as languages
                  FROM {$this->table}";
        
        $params = [];
        if ($langCode) {
            $query .= " WHERE lang_code = ?";
            $params[] = $langCode;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * الحصول على إحصائيات حسب اللغة
     * 
     * @return array
     */
    public function getStatisticsByLanguage(): array
    {
        $stmt = $this->db->query("
            SELECT 
                lang_code,
                COUNT(*) as total,
                COUNT(CASE WHEN translation_value = '' OR translation_value IS NULL THEN 1 END) as empty,
                COUNT(DISTINCT category) as categories
            FROM {$this->table}
            GROUP BY lang_code
            ORDER BY total DESC
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * حساب نسبة اكتمال الترجمة
     * 
     * @param string $referenceLang اللغة المرجعية
     * @param string $targetLang اللغة المستهدفة
     * @return float نسبة الاكتمال (0-100)
     */
    public function getCompletionPercentage(string $referenceLang, string $targetLang): float
    {
        $stmt = $this->db->prepare("
            SELECT 
                (SELECT COUNT(DISTINCT translation_key) FROM {$this->table} WHERE lang_code = ?) as reference_count,
                (SELECT COUNT(DISTINCT translation_key) FROM {$this->table} WHERE lang_code = ?) as target_count
        ");
        $stmt->execute([$referenceLang, $targetLang]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result['reference_count'] == 0) {
            return 0;
        }
        
        return round(($result['target_count'] / $result['reference_count']) * 100, 2);
    }
}
