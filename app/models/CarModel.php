<?php
/**
 * File: CarModel.php
 * Path: /app/models/CarModel.php
 * Purpose: نموذج موديلات السيارات (كامري، كورولا، الخ)
 * Dependencies: Model.php, Database.php, FileTracker.php
 * Phase: Phase 4 - Car Management
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

/**
 * Class CarModel
 * يدير موديلات السيارات لكل علامة تجارية
 * 
 * @package App\Models
 */
class CarModel extends Model
{
    protected string $table = 'car_models';
    protected array $fillable = [
        'brand_id',
        'name',
        'year_start',
        'year_end',
        'is_active'
    ];

    /**
     * الحصول على جميع الموديلات مع بيانات العلامة التجارية
     * 
     * @param int|null $brandId تصفية حسب العلامة التجارية
     * @return array
     */
    public function getAllWithBrand(?int $brandId = null): array
    {
        try {
            $query = "SELECT m.*, b.name as brand_name, b.logo as brand_logo,
                     COUNT(DISTINCT c.id) as cars_count
                     FROM {$this->table} m
                     INNER JOIN car_brands b ON m.brand_id = b.id";
            
            if ($brandId) {
                $query .= " WHERE m.brand_id = ?";
            }
            
            $query .= " LEFT JOIN cars c ON m.id = c.model_id AND c.deleted_at IS NULL
                       GROUP BY m.id
                       ORDER BY b.name ASC, m.name ASC";
            
            $stmt = $this->db->prepare($query);
            
            if ($brandId) {
                $stmt->execute([$brandId]);
            } else {
                $stmt->execute();
            }
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getAllWithBrand: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على الموديلات النشطة لعلامة تجارية محددة
     * 
     * @param int $brandId معرف العلامة التجارية
     * @return array
     */
    public function getActiveByBrand(int $brandId): array
    {
        try {
            $query = "SELECT * FROM {$this->table} 
                     WHERE brand_id = ? AND is_active = 1 
                     ORDER BY name ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$brandId]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getActiveByBrand: " . $e->getMessage());
            return [];
        }
    }

    /**
     * التحقق من وجود الموديل بنفس الاسم لنفس العلامة التجارية
     * 
     * @param int $brandId معرف العلامة التجارية
     * @param string $name اسم الموديل
     * @param int|null $excludeId معرف الموديل المراد استثناؤه
     * @return bool
     */
    public function nameExistsForBrand(int $brandId, string $name, ?int $excludeId = null): bool
    {
        try {
            $query = "SELECT COUNT(*) FROM {$this->table} 
                     WHERE brand_id = ? AND name = ?";
            $params = [$brandId, $name];
            
            if ($excludeId) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log("خطأ في nameExistsForBrand: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إنشاء موديل جديد
     * 
     * @param array $data بيانات الموديل
     * @return int|false معرف الموديل أو false
     */
    public function create(array $data): int|false
    {
        try {
            // التحقق من عدم وجود نفس الاسم لنفس العلامة
            if ($this->nameExistsForBrand($data['brand_id'], $data['name'])) {
                return false;
            }

            Database::beginTransaction();

            $query = "INSERT INTO {$this->table} 
                     (brand_id, name, year_start, year_end, is_active) 
                     VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['brand_id'],
                $data['name'],
                $data['year_start'] ?? null,
                $data['year_end'] ?? null,
                $data['is_active'] ?? 1
            ]);

            $modelId = (int) $this->db->lastInsertId();

            // تسجيل في audit log
            $this->logAudit('create', 'car_models', $modelId, null, $data);

            Database::commit();
            
            return $modelId;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في create model: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث موديل
     * 
     * @param int $id معرف الموديل
     * @param array $data البيانات الجديدة
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        try {
            $oldData = $this->find($id);
            if (!$oldData) {
                return false;
            }

            // التحقق من عدم وجود نفس الاسم
            $brandId = $data['brand_id'] ?? $oldData['brand_id'];
            $name = $data['name'] ?? $oldData['name'];
            
            if ($this->nameExistsForBrand($brandId, $name, $id)) {
                return false;
            }

            Database::beginTransaction();

            $query = "UPDATE {$this->table} 
                     SET brand_id = ?, 
                         name = ?, 
                         year_start = ?,
                         year_end = ?,
                         is_active = ?,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $brandId,
                $name,
                $data['year_start'] ?? $oldData['year_start'],
                $data['year_end'] ?? $oldData['year_end'],
                $data['is_active'] ?? $oldData['is_active'],
                $id
            ]);

            // تسجيل في audit log
            $this->logAudit('update', 'car_models', $id, $oldData, $data);

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في update model: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف موديل
     * 
     * @param int $id معرف الموديل
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            // التحقق من عدم وجود سيارات مرتبطة
            if ($this->hasRelatedCars($id)) {
                return false;
            }

            $oldData = $this->find($id);
            if (!$oldData) {
                return false;
            }

            Database::beginTransaction();

            $query = "DELETE FROM {$this->table} WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$id]);

            // تسجيل في audit log
            $this->logAudit('delete', 'car_models', $id, $oldData, null);

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في delete model: " . $e->getMessage());
            return false;
        }
    }

    /**
     * التحقق من وجود سيارات مرتبطة
     * 
     * @param int $modelId معرف الموديل
     * @return bool
     */
    public function hasRelatedCars(int $modelId): bool
    {
        try {
            $query = "SELECT COUNT(*) FROM cars 
                     WHERE model_id = ? AND deleted_at IS NULL";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$modelId]);
            
            return $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log("خطأ في hasRelatedCars: " . $e->getMessage());
            return true;
        }
    }

    /**
     * تغيير حالة التفعيل
     * 
     * @param int $id معرف الموديل
     * @param bool $status الحالة الجديدة
     * @return bool
     */
    public function toggleStatus(int $id, bool $status): bool
    {
        try {
            $query = "UPDATE {$this->table} 
                     SET is_active = ?,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$status ? 1 : 0, $id]);
        } catch (\PDOException $e) {
            error_log("خطأ في toggleStatus: " . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على الموديل مع بيانات العلامة التجارية
     * 
     * @param int $id معرف الموديل
     * @return array|null
     */
    public function findWithBrand(int $id): ?array
    {
        try {
            $query = "SELECT m.*, b.name as brand_name, b.logo as brand_logo,
                     COUNT(DISTINCT c.id) as cars_count
                     FROM {$this->table} m
                     INNER JOIN car_brands b ON m.brand_id = b.id
                     LEFT JOIN cars c ON m.id = c.model_id AND c.deleted_at IS NULL
                     WHERE m.id = ?
                     GROUP BY m.id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\PDOException $e) {
            error_log("خطأ في findWithBrand: " . $e->getMessage());
            return null;
        }
    }

    /**
     * البحث في الموديلات
     * 
     * @param string $search نص البحث
     * @param int|null $brandId تصفية حسب العلامة
     * @return array
     */
    public function search(string $search, ?int $brandId = null): array
    {
        try {
            $query = "SELECT m.*, b.name as brand_name, b.logo as brand_logo
                     FROM {$this->table} m
                     INNER JOIN car_brands b ON m.brand_id = b.id
                     WHERE m.name LIKE ?";
            
            $params = ['%' . $search . '%'];
            
            if ($brandId) {
                $query .= " AND m.brand_id = ?";
                $params[] = $brandId;
            }
            
            $query .= " ORDER BY b.name ASC, m.name ASC LIMIT 50";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في search: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على إحصائيات الموديل
     * 
     * @param int $modelId معرف الموديل
     * @return array
     */
    public function getStatistics(int $modelId): array
    {
        try {
            $query = "SELECT 
                     COUNT(DISTINCT c.id) as cars_count,
                     COUNT(DISTINCT CASE WHEN c.status = 'available' THEN c.id END) as available_cars,
                     COUNT(DISTINCT CASE WHEN c.status = 'rented' THEN c.id END) as rented_cars,
                     COUNT(DISTINCT CASE WHEN c.status = 'maintenance' THEN c.id END) as maintenance_cars,
                     AVG(c.daily_rate) as avg_daily_rate,
                     MIN(c.daily_rate) as min_daily_rate,
                     MAX(c.daily_rate) as max_daily_rate
                     FROM {$this->table} m
                     LEFT JOIN cars c ON m.id = c.model_id AND c.deleted_at IS NULL
                     WHERE m.id = ?
                     GROUP BY m.id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$modelId]);
            
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log("خطأ في getStatistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على الموديلات الأكثر استخدامًا
     * 
     * @param int $limit عدد النتائج
     * @return array
     */
    public function getMostPopular(int $limit = 10): array
    {
        try {
            $query = "SELECT m.*, b.name as brand_name, b.logo as brand_logo,
                     COUNT(DISTINCT c.id) as cars_count,
                     COUNT(DISTINCT r.id) as rentals_count
                     FROM {$this->table} m
                     INNER JOIN car_brands b ON m.brand_id = b.id
                     LEFT JOIN cars c ON m.id = c.model_id AND c.deleted_at IS NULL
                     LEFT JOIN rentals r ON c.id = r.car_id
                     WHERE m.is_active = 1
                     GROUP BY m.id
                     HAVING cars_count > 0
                     ORDER BY rentals_count DESC, cars_count DESC
                     LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getMostPopular: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على قائمة الموديلات لـ Select Dropdown
     * 
     * @param int|null $brandId تصفية حسب العلامة
     * @param bool $activeOnly النشطة فقط
     * @return array
     */
    public function getForDropdown(?int $brandId = null, bool $activeOnly = true): array
    {
        try {
            $query = "SELECT m.id, m.name, b.name as brand_name
                     FROM {$this->table} m
                     INNER JOIN car_brands b ON m.brand_id = b.id
                     WHERE 1=1";
            
            $params = [];
            
            if ($activeOnly) {
                $query .= " AND m.is_active = 1";
            }
            
            if ($brandId) {
                $query .= " AND m.brand_id = ?";
                $params[] = $brandId;
            }
            
            $query .= " ORDER BY b.name ASC, m.name ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getForDropdown: " . $e->getMessage());
            return [];
        }
    }
}

// تسجيل الملف في FileTracker
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 4');
