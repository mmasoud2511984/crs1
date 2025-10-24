<?php
/**
 * File: CarMaintenance.php
 * Path: /app/models/CarMaintenance.php
 * Purpose: نموذج سجل الصيانة - إدارة كاملة لسجلات صيانة السيارات
 * Dependencies: Model.php, Database.php, FileTracker.php
 * Phase: Phase 5 - Maintenance System
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

/**
 * Class CarMaintenance
 * النموذج الرئيسي لإدارة سجلات الصيانة
 * 
 * @package App\Models
 */
class CarMaintenance extends Model
{
    protected string $table = 'car_maintenance';
    protected array $fillable = [
        'car_id', 'maintenance_type', 'description', 'odometer_reading',
        'cost', 'service_center', 'technician_name', 'maintenance_date',
        'next_maintenance_date', 'parts_replaced', 'notes', 'receipt_path',
        'created_by'
    ];

    // أنواع الصيانة
    const TYPE_PERIODIC = 'periodic';
    const TYPE_REPAIR = 'repair';
    const TYPE_ACCIDENT = 'accident';
    const TYPE_INSPECTION = 'inspection';
    const TYPE_OTHER = 'other';

    /**
     * الحصول على جميع سجلات الصيانة مع التفاصيل
     * 
     * @param array $filters مصفيات البحث
     * @param int $page رقم الصفحة
     * @param int $perPage عدد العناصر بالصفحة
     * @return array
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        try {
            $offset = ($page - 1) * $perPage;
            
            $query = "SELECT m.*, 
                     c.plate_number, c.nickname, c.current_odometer,
                     br.name as brand_name, 
                     mo.name as model_name,
                     u.full_name as created_by_name
                     FROM {$this->table} m
                     INNER JOIN cars c ON m.car_id = c.id
                     INNER JOIN car_brands br ON c.brand_id = br.id
                     INNER JOIN car_models mo ON c.model_id = mo.id
                     LEFT JOIN users u ON m.created_by = u.id
                     WHERE 1=1";
            
            $params = [];

            // تطبيق المصفيات
            if (!empty($filters['car_id'])) {
                $query .= " AND m.car_id = ?";
                $params[] = $filters['car_id'];
            }

            if (!empty($filters['maintenance_type'])) {
                $query .= " AND m.maintenance_type = ?";
                $params[] = $filters['maintenance_type'];
            }

            if (!empty($filters['date_from'])) {
                $query .= " AND m.maintenance_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $query .= " AND m.maintenance_date <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['search'])) {
                $query .= " AND (c.plate_number LIKE ? OR c.nickname LIKE ? OR m.description LIKE ? OR m.service_center LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }

            // الترتيب والصفحات
            $query .= " ORDER BY m.maintenance_date DESC, m.created_at DESC
                       LIMIT ? OFFSET ?";
            
            $params[] = $perPage;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getAll maintenance: " . $e->getMessage());
            return [];
        }
    }

    /**
     * عد جميع السجلات مع المصفيات
     * 
     * @param array $filters مصفيات البحث
     * @return int
     */
    public function count(array $filters = []): int
    {
        try {
            $query = "SELECT COUNT(*) 
                     FROM {$this->table} m
                     INNER JOIN cars c ON m.car_id = c.id
                     WHERE 1=1";
            
            $params = [];

            if (!empty($filters['car_id'])) {
                $query .= " AND m.car_id = ?";
                $params[] = $filters['car_id'];
            }

            if (!empty($filters['maintenance_type'])) {
                $query .= " AND m.maintenance_type = ?";
                $params[] = $filters['maintenance_type'];
            }

            if (!empty($filters['date_from'])) {
                $query .= " AND m.maintenance_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $query .= " AND m.maintenance_date <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['search'])) {
                $query .= " AND (c.plate_number LIKE ? OR c.nickname LIKE ? OR m.description LIKE ? OR m.service_center LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log("خطأ في count maintenance: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * الحصول على سجل صيانة بالمعرف مع التفاصيل
     * 
     * @param int $id معرف السجل
     * @return array|null
     */
    public function find(int $id): ?array
    {
        try {
            $query = "SELECT m.*, 
                     c.id as car_id, c.plate_number, c.nickname, c.current_odometer,
                     c.last_maintenance_odometer, c.maintenance_interval,
                     br.name as brand_name, 
                     mo.name as model_name,
                     u.full_name as created_by_name
                     FROM {$this->table} m
                     INNER JOIN cars c ON m.car_id = c.id
                     INNER JOIN car_brands br ON c.brand_id = br.id
                     INNER JOIN car_models mo ON c.model_id = mo.id
                     LEFT JOIN users u ON m.created_by = u.id
                     WHERE m.id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\PDOException $e) {
            error_log("خطأ في find maintenance: " . $e->getMessage());
            return null;
        }
    }

    /**
     * الحصول على سجلات صيانة سيارة معينة
     * 
     * @param int $carId معرف السيارة
     * @param int $limit الحد الأقصى للنتائج
     * @return array
     */
    public function getByCarId(int $carId, int $limit = 50): array
    {
        try {
            $query = "SELECT m.*, 
                     u.full_name as created_by_name
                     FROM {$this->table} m
                     LEFT JOIN users u ON m.created_by = u.id
                     WHERE m.car_id = ?
                     ORDER BY m.maintenance_date DESC, m.created_at DESC
                     LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(1, $carId, \PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getByCarId: " . $e->getMessage());
            return [];
        }
    }

    /**
     * إنشاء سجل صيانة جديد
     * 
     * @param array $data بيانات السجل
     * @return int|false معرف السجل أو false
     */
    public function create(array $data): int|false
    {
        try {
            Database::beginTransaction();

            $columns = [];
            $values = [];
            $params = [];

            foreach ($this->fillable as $field) {
                if (isset($data[$field])) {
                    $columns[] = $field;
                    $values[] = '?';
                    $params[] = $data[$field];
                }
            }

            $query = "INSERT INTO {$this->table} 
                     (" . implode(', ', $columns) . ") 
                     VALUES (" . implode(', ', $values) . ")";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            $maintenanceId = (int) $this->db->lastInsertId();

            // تحديث معلومات الصيانة في جدول السيارات
            $this->updateCarMaintenanceInfo($data['car_id'], $data);

            // تسجيل في audit log
            $this->logAudit('create', 'car_maintenance', $maintenanceId, null, $data);

            Database::commit();
            
            // تسجيل في FileTracker
            FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 5');
            
            return $maintenanceId;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في create maintenance: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث سجل صيانة
     * 
     * @param int $id معرف السجل
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

            Database::beginTransaction();

            $updates = [];
            $params = [];

            foreach ($this->fillable as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                Database::rollBack();
                return false;
            }

            $query = "UPDATE {$this->table} 
                     SET " . implode(', ', $updates) . ",
                     updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?";
            
            $params[] = $id;
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($params);

            // تحديث معلومات الصيانة في جدول السيارات إذا تغير التاريخ
            if (isset($data['maintenance_date']) || isset($data['odometer_reading'])) {
                $this->updateCarMaintenanceInfo($oldData['car_id'], $data);
            }

            // تسجيل في audit log
            $this->logAudit('update', 'car_maintenance', $id, $oldData, $data);

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في update maintenance: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف سجل صيانة
     * 
     * @param int $id معرف السجل
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

            $query = "DELETE FROM {$this->table} WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$id]);

            // حذف الإيصال إن وجد
            if (!empty($oldData['receipt_path']) && file_exists($oldData['receipt_path'])) {
                unlink($oldData['receipt_path']);
            }

            // تسجيل في audit log
            $this->logAudit('delete', 'car_maintenance', $id, $oldData, null);

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في delete maintenance: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث معلومات الصيانة في جدول السيارات
     * 
     * @param int $carId معرف السيارة
     * @param array $maintenanceData بيانات الصيانة
     * @return bool
     */
    private function updateCarMaintenanceInfo(int $carId, array $maintenanceData): bool
    {
        try {
            $updates = [];
            $params = [];

            // تحديث تاريخ آخر صيانة
            if (!empty($maintenanceData['maintenance_date'])) {
                $updates[] = "last_maintenance_date = ?";
                $params[] = $maintenanceData['maintenance_date'];
            }

            // تحديث عداد الكيلومترات عند الصيانة
            if (!empty($maintenanceData['odometer_reading'])) {
                $updates[] = "last_maintenance_odometer = ?";
                $params[] = $maintenanceData['odometer_reading'];
                
                // حساب الصيانة القادمة
                $carInfo = $this->db->prepare("SELECT maintenance_interval FROM cars WHERE id = ?");
                $carInfo->execute([$carId]);
                $car = $carInfo->fetch(\PDO::FETCH_ASSOC);
                
                if ($car && $car['maintenance_interval']) {
                    $updates[] = "next_maintenance_due = ?";
                    $params[] = $maintenanceData['odometer_reading'] + $car['maintenance_interval'];
                }
            }

            // تحديث الحالة إلى الصيانة إذا كانت الصيانة اليوم
            if (!empty($maintenanceData['maintenance_date']) && 
                $maintenanceData['maintenance_date'] === date('Y-m-d')) {
                $updates[] = "status = 'maintenance'";
            }

            if (empty($updates)) {
                return true;
            }

            $params[] = $carId;

            $query = "UPDATE cars 
                     SET " . implode(', ', $updates) . "
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("خطأ في updateCarMaintenanceInfo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على إحصائيات الصيانة
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        try {
            $query = "SELECT 
                     COUNT(*) as total_records,
                     COUNT(CASE WHEN maintenance_type = 'periodic' THEN 1 END) as periodic,
                     COUNT(CASE WHEN maintenance_type = 'repair' THEN 1 END) as repair,
                     COUNT(CASE WHEN maintenance_type = 'accident' THEN 1 END) as accident,
                     COUNT(CASE WHEN maintenance_type = 'inspection' THEN 1 END) as inspection,
                     SUM(cost) as total_cost,
                     AVG(cost) as avg_cost,
                     COUNT(DISTINCT car_id) as cars_with_maintenance,
                     COUNT(CASE WHEN YEAR(maintenance_date) = YEAR(CURDATE()) THEN 1 END) as this_year,
                     COUNT(CASE WHEN MONTH(maintenance_date) = MONTH(CURDATE()) 
                                 AND YEAR(maintenance_date) = YEAR(CURDATE()) THEN 1 END) as this_month
                     FROM {$this->table}";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log("خطأ في getStatistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على السيارات التي تحتاج صيانة
     * 
     * @param int $limit عدد النتائج
     * @return array
     */
    public function getCarsNeedingMaintenance(int $limit = 50): array
    {
        try {
            $query = "SELECT c.id, c.plate_number, c.nickname, 
                     c.current_odometer, c.last_maintenance_odometer, 
                     c.next_maintenance_due, c.maintenance_interval,
                     br.name as brand_name, 
                     mo.name as model_name,
                     (c.current_odometer - c.last_maintenance_odometer) as km_since_maintenance,
                     (c.current_odometer - c.next_maintenance_due) as overdue_km
                     FROM cars c
                     INNER JOIN car_brands br ON c.brand_id = br.id
                     INNER JOIN car_models mo ON c.model_id = mo.id
                     WHERE c.deleted_at IS NULL 
                     AND c.status NOT IN ('sold', 'retired')
                     AND c.current_odometer >= c.next_maintenance_due
                     ORDER BY (c.current_odometer - c.next_maintenance_due) DESC
                     LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getCarsNeedingMaintenance: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على السيارات القريبة من موعد الصيانة
     * 
     * @param int $threshold عدد الكيلومترات قبل الصيانة
     * @param int $limit عدد النتائج
     * @return array
     */
    public function getCarsNearingMaintenance(int $threshold = 500, int $limit = 50): array
    {
        try {
            $query = "SELECT c.id, c.plate_number, c.nickname, 
                     c.current_odometer, c.last_maintenance_odometer, 
                     c.next_maintenance_due, c.maintenance_interval,
                     br.name as brand_name, 
                     mo.name as model_name,
                     (c.next_maintenance_due - c.current_odometer) as km_until_maintenance
                     FROM cars c
                     INNER JOIN car_brands br ON c.brand_id = br.id
                     INNER JOIN car_models mo ON c.model_id = mo.id
                     WHERE c.deleted_at IS NULL 
                     AND c.status NOT IN ('sold', 'retired')
                     AND c.current_odometer < c.next_maintenance_due
                     AND (c.next_maintenance_due - c.current_odometer) <= ?
                     ORDER BY (c.next_maintenance_due - c.current_odometer) ASC
                     LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(1, $threshold, \PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getCarsNearingMaintenance: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على إجمالي تكلفة الصيانة لسيارة معينة
     * 
     * @param int $carId معرف السيارة
     * @return float
     */
    public function getTotalCostForCar(int $carId): float
    {
        try {
            $query = "SELECT SUM(cost) as total_cost 
                     FROM {$this->table} 
                     WHERE car_id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$carId]);
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (float) ($result['total_cost'] ?? 0);
        } catch (\PDOException $e) {
            error_log("خطأ في getTotalCostForCar: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * الحصول على سجلات الصيانة لفترة معينة
     * 
     * @param string $dateFrom تاريخ البداية
     * @param string $dateTo تاريخ النهاية
     * @return array
     */
    public function getByDateRange(string $dateFrom, string $dateTo): array
    {
        try {
            $query = "SELECT m.*, 
                     c.plate_number, c.nickname,
                     br.name as brand_name, 
                     mo.name as model_name
                     FROM {$this->table} m
                     INNER JOIN cars c ON m.car_id = c.id
                     INNER JOIN car_brands br ON c.brand_id = br.id
                     INNER JOIN car_models mo ON c.model_id = mo.id
                     WHERE m.maintenance_date BETWEEN ? AND ?
                     ORDER BY m.maintenance_date DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$dateFrom, $dateTo]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getByDateRange: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على سجلات الصيانة حسب النوع
     * 
     * @param string $type نوع الصيانة
     * @param int $limit عدد النتائج
     * @return array
     */
    public function getByType(string $type, int $limit = 50): array
    {
        try {
            $query = "SELECT m.*, 
                     c.plate_number, c.nickname,
                     br.name as brand_name, 
                     mo.name as model_name
                     FROM {$this->table} m
                     INNER JOIN cars c ON m.car_id = c.id
                     INNER JOIN car_brands br ON c.brand_id = br.id
                     INNER JOIN car_models mo ON c.model_id = mo.id
                     WHERE m.maintenance_type = ?
                     ORDER BY m.maintenance_date DESC
                     LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(1, $type, \PDO::PARAM_STR);
            $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getByType: " . $e->getMessage());
            return [];
        }
    }

    /**
     * التحقق من صلاحية أنواع الصيانة
     * 
     * @param string $type نوع الصيانة
     * @return bool
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, [
            self::TYPE_PERIODIC,
            self::TYPE_REPAIR,
            self::TYPE_ACCIDENT,
            self::TYPE_INSPECTION,
            self::TYPE_OTHER
        ]);
    }

    /**
     * الحصول على جميع أنواع الصيانة
     * 
     * @return array
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_PERIODIC => 'maintenance.type.periodic',
            self::TYPE_REPAIR => 'maintenance.type.repair',
            self::TYPE_ACCIDENT => 'maintenance.type.accident',
            self::TYPE_INSPECTION => 'maintenance.type.inspection',
            self::TYPE_OTHER => 'maintenance.type.other'
        ];
    }
}

// تسجيل الملف في FileTracker
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 5');
