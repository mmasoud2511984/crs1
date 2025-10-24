<?php
/**
 * File: CarFeature.php
 * Path: /app/models/CarFeature.php
 * Purpose: نموذج مميزات السيارات (تكييف، بلوتوث، فتحة سقف، الخ)
 * Dependencies: Model.php, Database.php, FileTracker.php
 * Phase: Phase 4 - Car Management
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

/**
 * Class CarFeature
 * يدير المميزات القابلة لإعادة الاستخدام للسيارات
 * 
 * @package App\Models
 */
class CarFeature extends Model
{
    protected string $table = 'car_features';
    protected array $fillable = [
        'feature_key',
        'icon',
        'display_order',
        'is_active'
    ];

    /**
     * الحصول على جميع المميزات النشطة
     * 
     * @return array
     */
    public function getActive(): array
    {
        try {
            $query = "SELECT * FROM {$this->table} 
                     WHERE is_active = 1 
                     ORDER BY display_order ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getActive features: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على جميع المميزات مع عدد الاستخدام
     * 
     * @return array
     */
    public function getAllWithUsageCount(): array
    {
        try {
            $query = "SELECT f.*, 
                     COUNT(DISTINCT cfv.car_id) as usage_count
                     FROM {$this->table} f
                     LEFT JOIN car_feature_values cfv ON f.id = cfv.feature_id 
                     AND cfv.has_feature = 1
                     GROUP BY f.id
                     ORDER BY f.display_order ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getAllWithUsageCount: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على مميزات سيارة محددة
     * 
     * @param int $carId معرف السيارة
     * @param bool $showInListingOnly عرض في القائمة فقط
     * @return array
     */
    public function getCarFeatures(int $carId, bool $showInListingOnly = false): array
    {
        try {
            $query = "SELECT f.*, cfv.has_feature, cfv.show_in_listing
                     FROM {$this->table} f
                     INNER JOIN car_feature_values cfv ON f.id = cfv.feature_id
                     WHERE cfv.car_id = ? AND cfv.has_feature = 1";
            
            if ($showInListingOnly) {
                $query .= " AND cfv.show_in_listing = 1";
            }
            
            $query .= " ORDER BY f.display_order ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$carId]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getCarFeatures: " . $e->getMessage());
            return [];
        }
    }

    /**
     * التحقق من وجود feature_key
     * 
     * @param string $key مفتاح الميزة
     * @param int|null $excludeId معرف الميزة المراد استثناؤها
     * @return bool
     */
    public function keyExists(string $key, ?int $excludeId = null): bool
    {
        try {
            $query = "SELECT COUNT(*) FROM {$this->table} WHERE feature_key = ?";
            $params = [$key];
            
            if ($excludeId) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log("خطأ في keyExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إنشاء ميزة جديدة
     * 
     * @param array $data بيانات الميزة
     * @return int|false معرف الميزة أو false
     */
    public function create(array $data): int|false
    {
        try {
            // التحقق من عدم وجود نفس المفتاح
            if ($this->keyExists($data['feature_key'])) {
                return false;
            }

            Database::beginTransaction();

            // تعيين ترتيب العرض إذا لم يكن محددًا
            if (!isset($data['display_order'])) {
                $data['display_order'] = $this->getNextDisplayOrder();
            }

            $query = "INSERT INTO {$this->table} 
                     (feature_key, icon, display_order, is_active) 
                     VALUES (?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['feature_key'],
                $data['icon'] ?? null,
                $data['display_order'],
                $data['is_active'] ?? 1
            ]);

            $featureId = (int) $this->db->lastInsertId();

            // تسجيل في audit log
            $this->logAudit('create', 'car_features', $featureId, null, $data);

            Database::commit();
            
            return $featureId;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في create feature: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث ميزة
     * 
     * @param int $id معرف الميزة
     * @param array $data البيانات الجديدة
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        try {
            // التحقق من عدم وجود نفس المفتاح
            if (isset($data['feature_key']) && $this->keyExists($data['feature_key'], $id)) {
                return false;
            }

            $oldData = $this->find($id);
            if (!$oldData) {
                return false;
            }

            Database::beginTransaction();

            $query = "UPDATE {$this->table} 
                     SET feature_key = ?, 
                         icon = ?, 
                         display_order = ?,
                         is_active = ?
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $data['feature_key'] ?? $oldData['feature_key'],
                $data['icon'] ?? $oldData['icon'],
                $data['display_order'] ?? $oldData['display_order'],
                $data['is_active'] ?? $oldData['is_active'],
                $id
            ]);

            // تسجيل في audit log
            $this->logAudit('update', 'car_features', $id, $oldData, $data);

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في update feature: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف ميزة
     * 
     * @param int $id معرف الميزة
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $oldData = $this->find($id);
            if (!$oldData) {
                return false;
            }

            Database::beginTransaction();

            // حذف جميع الربطات مع السيارات
            $query = "DELETE FROM car_feature_values WHERE feature_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);

            // حذف الميزة
            $query = "DELETE FROM {$this->table} WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$id]);

            // تسجيل في audit log
            $this->logAudit('delete', 'car_features', $id, $oldData, null);

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في delete feature: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تغيير حالة التفعيل
     * 
     * @param int $id معرف الميزة
     * @param bool $status الحالة الجديدة
     * @return bool
     */
    public function toggleStatus(int $id, bool $status): bool
    {
        try {
            $query = "UPDATE {$this->table} 
                     SET is_active = ?
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$status ? 1 : 0, $id]);
        } catch (\PDOException $e) {
            error_log("خطأ في toggleStatus: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إعادة ترتيب المميزات
     * 
     * @param array $order مصفوفة [id => display_order]
     * @return bool
     */
    public function reorder(array $order): bool
    {
        try {
            Database::beginTransaction();

            $query = "UPDATE {$this->table} SET display_order = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);

            foreach ($order as $id => $displayOrder) {
                $stmt->execute([$displayOrder, $id]);
            }

            Database::commit();
            return true;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في reorder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على الترتيب التالي
     * 
     * @return int
     */
    private function getNextDisplayOrder(): int
    {
        try {
            $query = "SELECT MAX(display_order) FROM {$this->table}";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            $max = $stmt->fetchColumn();
            return ($max ?? 0) + 1;
        } catch (\PDOException $e) {
            error_log("خطأ في getNextDisplayOrder: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ربط مميزات بسيارة
     * 
     * @param int $carId معرف السيارة
     * @param array $features مصفوفة [feature_id => ['has_feature' => bool, 'show_in_listing' => bool]]
     * @return bool
     */
    public function attachToCar(int $carId, array $features): bool
    {
        try {
            Database::beginTransaction();

            // حذف الربطات القديمة
            $query = "DELETE FROM car_feature_values WHERE car_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$carId]);

            // إضافة الربطات الجديدة
            $query = "INSERT INTO car_feature_values 
                     (car_id, feature_id, has_feature, show_in_listing) 
                     VALUES (?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);

            foreach ($features as $featureId => $data) {
                $stmt->execute([
                    $carId,
                    $featureId,
                    $data['has_feature'] ?? 1,
                    $data['show_in_listing'] ?? 1
                ]);
            }

            Database::commit();
            return true;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في attachToCar: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث ميزة واحدة لسيارة
     * 
     * @param int $carId معرف السيارة
     * @param int $featureId معرف الميزة
     * @param bool $hasFeature هل تملك الميزة
     * @param bool $showInListing عرض في القائمة
     * @return bool
     */
    public function updateCarFeature(int $carId, int $featureId, bool $hasFeature, bool $showInListing = true): bool
    {
        try {
            $query = "INSERT INTO car_feature_values 
                     (car_id, feature_id, has_feature, show_in_listing) 
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE 
                     has_feature = VALUES(has_feature),
                     show_in_listing = VALUES(show_in_listing)";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                $carId,
                $featureId,
                $hasFeature ? 1 : 0,
                $showInListing ? 1 : 0
            ]);
        } catch (\PDOException $e) {
            error_log("خطأ في updateCarFeature: " . $e->getMessage());
            return false;
        }
    }

    /**
     * فصل ميزة عن سيارة
     * 
     * @param int $carId معرف السيارة
     * @param int $featureId معرف الميزة
     * @return bool
     */
    public function detachFromCar(int $carId, int $featureId): bool
    {
        try {
            $query = "DELETE FROM car_feature_values 
                     WHERE car_id = ? AND feature_id = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$carId, $featureId]);
        } catch (\PDOException $e) {
            error_log("خطأ في detachFromCar: " . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على المميزات الأكثر شيوعًا
     * 
     * @param int $limit عدد النتائج
     * @return array
     */
    public function getMostCommon(int $limit = 10): array
    {
        try {
            $query = "SELECT f.*, 
                     COUNT(DISTINCT cfv.car_id) as usage_count,
                     (COUNT(DISTINCT cfv.car_id) * 100.0 / 
                      (SELECT COUNT(DISTINCT id) FROM cars WHERE deleted_at IS NULL)) as usage_percentage
                     FROM {$this->table} f
                     LEFT JOIN car_feature_values cfv ON f.id = cfv.feature_id 
                     AND cfv.has_feature = 1
                     WHERE f.is_active = 1
                     GROUP BY f.id
                     ORDER BY usage_count DESC
                     LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getMostCommon: " . $e->getMessage());
            return [];
        }
    }

    /**
     * البحث في المميزات
     * 
     * @param string $search نص البحث
     * @return array
     */
    public function search(string $search): array
    {
        try {
            $query = "SELECT f.*, 
                     COUNT(DISTINCT cfv.car_id) as usage_count
                     FROM {$this->table} f
                     LEFT JOIN car_feature_values cfv ON f.id = cfv.feature_id 
                     AND cfv.has_feature = 1
                     WHERE f.feature_key LIKE ?
                     GROUP BY f.id
                     ORDER BY f.display_order ASC
                     LIMIT 50";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['%' . $search . '%']);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في search: " . $e->getMessage());
            return [];
        }
    }

    /**
     * نسخ مميزات من سيارة إلى أخرى
     * 
     * @param int $fromCarId معرف السيارة المصدر
     * @param int $toCarId معرف السيارة الهدف
     * @return bool
     */
    public function copyCarFeatures(int $fromCarId, int $toCarId): bool
    {
        try {
            Database::beginTransaction();

            // حذف الربطات القديمة للسيارة الهدف
            $query = "DELETE FROM car_feature_values WHERE car_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$toCarId]);

            // نسخ المميزات
            $query = "INSERT INTO car_feature_values (car_id, feature_id, has_feature, show_in_listing)
                     SELECT ?, feature_id, has_feature, show_in_listing
                     FROM car_feature_values
                     WHERE car_id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$toCarId, $fromCarId]);

            Database::commit();
            return true;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في copyCarFeatures: " . $e->getMessage());
            return false;
        }
    }
}

// تسجيل الملف في FileTracker
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 4');
