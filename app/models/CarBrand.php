<?php
/**
 * File: CarBrand.php
 * Path: /app/models/CarBrand.php
 * Purpose: نموذج العلامات التجارية للسيارات (تويوتا، بي ام دبليو، الخ)
 * Dependencies: Model.php, Database.php, FileTracker.php
 * Phase: Phase 4 - Car Management
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

/**
 * Class CarBrand
 * يدير العلامات التجارية للسيارات
 * 
 * @package App\Models
 */
class CarBrand extends Model
{
    protected string $table = 'car_brands';
    protected array $fillable = [
        'name',
        'logo',
        'is_active',
        'display_order'
    ];

    /**
     * الحصول على جميع العلامات التجارية النشطة
     * 
     * @return array
     */
    public function getActiveBrands(): array
    {
        try {
            $query = "SELECT * FROM {$this->table} 
                     WHERE is_active = 1 
                     ORDER BY display_order ASC, name ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getActiveBrands: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على جميع العلامات التجارية مع عدد الموديلات
     * 
     * @return array
     */
    public function getAllWithModelsCount(): array
    {
        try {
            $query = "SELECT b.*, 
                     COUNT(DISTINCT m.id) as models_count,
                     COUNT(DISTINCT c.id) as cars_count
                     FROM {$this->table} b
                     LEFT JOIN car_models m ON b.id = m.brand_id
                     LEFT JOIN cars c ON b.id = c.brand_id AND c.deleted_at IS NULL
                     GROUP BY b.id
                     ORDER BY b.display_order ASC, b.name ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getAllWithModelsCount: " . $e->getMessage());
            return [];
        }
    }

    /**
     * التحقق من وجود العلامة التجارية بنفس الاسم
     * 
     * @param string $name اسم العلامة
     * @param int|null $excludeId معرف العلامة المراد استثناؤها
     * @return bool
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        try {
            $query = "SELECT COUNT(*) FROM {$this->table} WHERE name = ?";
            $params = [$name];
            
            if ($excludeId) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log("خطأ في nameExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إنشاء علامة تجارية جديدة
     * 
     * @param array $data بيانات العلامة
     * @return int|false معرف العلامة أو false
     */
    public function create(array $data): int|false
    {
        try {
            // التحقق من عدم وجود نفس الاسم
            if ($this->nameExists($data['name'])) {
                return false;
            }

            Database::beginTransaction();

            // تعيين ترتيب العرض إذا لم يكن محددًا
            if (!isset($data['display_order'])) {
                $data['display_order'] = $this->getNextDisplayOrder();
            }

            $query = "INSERT INTO {$this->table} 
                     (name, logo, is_active, display_order) 
                     VALUES (?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['name'],
                $data['logo'] ?? null,
                $data['is_active'] ?? 1,
                $data['display_order']
            ]);

            $brandId = (int) $this->db->lastInsertId();

            // تسجيل في audit log
            $this->logAudit('create', 'car_brands', $brandId, null, $data);

            Database::commit();
            
            return $brandId;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في create brand: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث علامة تجارية
     * 
     * @param int $id معرف العلامة
     * @param array $data البيانات الجديدة
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        try {
            // التحقق من عدم وجود نفس الاسم
            if (isset($data['name']) && $this->nameExists($data['name'], $id)) {
                return false;
            }

            $oldData = $this->find($id);
            if (!$oldData) {
                return false;
            }

            Database::beginTransaction();

            $query = "UPDATE {$this->table} 
                     SET name = ?, 
                         logo = ?, 
                         is_active = ?, 
                         display_order = ?,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $data['name'] ?? $oldData['name'],
                $data['logo'] ?? $oldData['logo'],
                $data['is_active'] ?? $oldData['is_active'],
                $data['display_order'] ?? $oldData['display_order'],
                $id
            ]);

            // تسجيل في audit log
            $this->logAudit('update', 'car_brands', $id, $oldData, $data);

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في update brand: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف علامة تجارية
     * 
     * @param int $id معرف العلامة
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            // التحقق من عدم وجود موديلات أو سيارات مرتبطة
            if ($this->hasRelatedRecords($id)) {
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

            // حذف الشعار إذا كان موجودًا
            if ($oldData['logo'] && file_exists($oldData['logo'])) {
                @unlink($oldData['logo']);
            }

            // تسجيل في audit log
            $this->logAudit('delete', 'car_brands', $id, $oldData, null);

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في delete brand: " . $e->getMessage());
            return false;
        }
    }

    /**
     * التحقق من وجود سجلات مرتبطة
     * 
     * @param int $brandId معرف العلامة
     * @return bool
     */
    public function hasRelatedRecords(int $brandId): bool
    {
        try {
            // التحقق من الموديلات
            $query = "SELECT COUNT(*) FROM car_models WHERE brand_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$brandId]);
            
            if ($stmt->fetchColumn() > 0) {
                return true;
            }

            // التحقق من السيارات
            $query = "SELECT COUNT(*) FROM cars WHERE brand_id = ? AND deleted_at IS NULL";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$brandId]);
            
            return $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log("خطأ في hasRelatedRecords: " . $e->getMessage());
            return true; // في حالة الخطأ نمنع الحذف
        }
    }

    /**
     * تغيير حالة التفعيل
     * 
     * @param int $id معرف العلامة
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
     * إعادة ترتيب العلامات التجارية
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
     * الحصول على إحصائيات العلامة التجارية
     * 
     * @param int $brandId معرف العلامة
     * @return array
     */
    public function getStatistics(int $brandId): array
    {
        try {
            $query = "SELECT 
                     COUNT(DISTINCT m.id) as models_count,
                     COUNT(DISTINCT c.id) as cars_count,
                     COUNT(DISTINCT CASE WHEN c.status = 'available' THEN c.id END) as available_cars,
                     COUNT(DISTINCT CASE WHEN c.status = 'rented' THEN c.id END) as rented_cars,
                     COUNT(DISTINCT CASE WHEN c.status = 'maintenance' THEN c.id END) as maintenance_cars
                     FROM {$this->table} b
                     LEFT JOIN car_models m ON b.id = m.brand_id
                     LEFT JOIN cars c ON b.id = c.brand_id AND c.deleted_at IS NULL
                     WHERE b.id = ?
                     GROUP BY b.id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$brandId]);
            
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log("خطأ في getStatistics: " . $e->getMessage());
            return [];
        }
    }
}

// تسجيل الملف في FileTracker
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 4');
