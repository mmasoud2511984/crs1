<?php
/**
 * File: Car.php
 * Path: /app/models/Car.php
 * Purpose: نموذج السيارات الرئيسي - إدارة كاملة لبيانات السيارات
 * Dependencies: Model.php, Database.php, FileTracker.php, CarFeature.php
 * Phase: Phase 4 - Car Management
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

/**
 * Class Car
 * النموذج الرئيسي لإدارة السيارات بكل تفاصيلها
 * 
 * @package App\Models
 */
class Car extends Model
{
    protected string $table = 'cars';
    protected array $fillable = [
        'branch_id', 'brand_id', 'model_id', 'nickname', 'vin_number', 
        'plate_number', 'color', 'manufacturing_year', 'purchase_date',
        'purchase_price', 'purchase_odometer', 'current_odometer', 'odometer_unit',
        'previous_owner', 'fuel_type', 'vehicle_type', 'transmission',
        'engine_capacity', 'cylinders', 'seats', 'doors',
        'tire_production_date', 'tire_front_size', 'tire_rear_size',
        'daily_rate', 'weekly_rate', 'monthly_rate', 'driver_daily_rate',
        'insurance_company', 'insurance_policy_number', 'insurance_expiry_date',
        'registration_expiry_date', 'last_maintenance_date', 'last_maintenance_odometer',
        'maintenance_interval', 'next_maintenance_due', 'status',
        'is_featured', 'is_with_driver', 'notes'
    ];

    // حالات السيارة
    const STATUS_AVAILABLE = 'available';
    const STATUS_RENTED = 'rented';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_SOLD = 'sold';
    const STATUS_RETIRED = 'retired';

    /**
     * الحصول على جميع السيارات مع البيانات الكاملة
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
            
            $query = "SELECT c.*, 
                     br.name as brand_name, br.logo as brand_logo,
                     m.name as model_name,
                     b.name as branch_name,
                     (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as primary_image
                     FROM {$this->table} c
                     INNER JOIN car_brands br ON c.brand_id = br.id
                     INNER JOIN car_models m ON c.model_id = m.id
                     LEFT JOIN branches b ON c.branch_id = b.id
                     WHERE c.deleted_at IS NULL";
            
            $params = [];

            // تطبيق المصفيات
            if (!empty($filters['status'])) {
                $query .= " AND c.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['brand_id'])) {
                $query .= " AND c.brand_id = ?";
                $params[] = $filters['brand_id'];
            }

            if (!empty($filters['model_id'])) {
                $query .= " AND c.model_id = ?";
                $params[] = $filters['model_id'];
            }

            if (!empty($filters['branch_id'])) {
                $query .= " AND c.branch_id = ?";
                $params[] = $filters['branch_id'];
            }

            if (!empty($filters['is_featured'])) {
                $query .= " AND c.is_featured = 1";
            }

            if (!empty($filters['is_with_driver'])) {
                $query .= " AND c.is_with_driver = 1";
            }

            if (!empty($filters['fuel_type'])) {
                $query .= " AND c.fuel_type = ?";
                $params[] = $filters['fuel_type'];
            }

            if (!empty($filters['transmission'])) {
                $query .= " AND c.transmission = ?";
                $params[] = $filters['transmission'];
            }

            if (!empty($filters['search'])) {
                $query .= " AND (c.plate_number LIKE ? OR c.nickname LIKE ? 
                           OR c.vin_number LIKE ? OR br.name LIKE ? OR m.name LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params = array_merge($params, array_fill(0, 5, $searchTerm));
            }

            // الترتيب
            $orderBy = $filters['order_by'] ?? 'c.id';
            $orderDir = $filters['order_dir'] ?? 'DESC';
            $query .= " ORDER BY {$orderBy} {$orderDir}";

            // الحد والإزاحة
            $query .= " LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($query);
            
            // ربط المعاملات
            foreach ($params as $key => $value) {
                $stmt->bindValue($key + 1, $value);
            }
            $stmt->bindValue(count($params) + 1, $perPage, \PDO::PARAM_INT);
            $stmt->bindValue(count($params) + 2, $offset, \PDO::PARAM_INT);
            
            $stmt->execute();
            $cars = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // الحصول على العدد الكلي
            $totalQuery = "SELECT COUNT(*) FROM {$this->table} c 
                          WHERE c.deleted_at IS NULL";
            
            if (!empty($filters)) {
                // تطبيق نفس المصفيات لحساب العدد الكلي
                // (نفس الشروط أعلاه ولكن بدون LIMIT و OFFSET)
                $countParams = array_slice($params, 0, -2); // إزالة limit و offset
                $stmt = $this->db->prepare($totalQuery);
                foreach ($countParams as $key => $value) {
                    $stmt->bindValue($key + 1, $value);
                }
                $stmt->execute();
            } else {
                $stmt = $this->db->prepare($totalQuery);
                $stmt->execute();
            }
            
            $total = $stmt->fetchColumn();

            return [
                'data' => $cars,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total)
                ]
            ];
        } catch (\PDOException $e) {
            error_log("خطأ في getAll cars: " . $e->getMessage());
            return ['data' => [], 'pagination' => []];
        }
    }

    /**
     * الحصول على سيارة بكل تفاصيلها
     * 
     * @param int $id معرف السيارة
     * @return array|null
     */
    public function findWithDetails(int $id): ?array
    {
        try {
            $query = "SELECT c.*, 
                     br.name as brand_name, br.logo as brand_logo,
                     m.name as model_name, m.year_start, m.year_end,
                     b.name as branch_name, b.address as branch_address
                     FROM {$this->table} c
                     INNER JOIN car_brands br ON c.brand_id = br.id
                     INNER JOIN car_models m ON c.model_id = m.id
                     LEFT JOIN branches b ON c.branch_id = b.id
                     WHERE c.id = ? AND c.deleted_at IS NULL";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            
            $car = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$car) {
                return null;
            }

            // الحصول على الصور
            $car['images'] = $this->getCarImages($id);

            // الحصول على المميزات
            $featureModel = new CarFeature();
            $car['features'] = $featureModel->getCarFeatures($id);

            return $car;
        } catch (\PDOException $e) {
            error_log("خطأ في findWithDetails: " . $e->getMessage());
            return null;
        }
    }

    /**
     * التحقق من توفر السيارة للإيجار
     * 
     * @param int $carId معرف السيارة
     * @param string $startDate تاريخ البداية
     * @param string $endDate تاريخ النهاية
     * @param int|null $excludeRentalId استثناء عقد معين (للتعديل)
     * @return bool
     */
    public function isAvailable(int $carId, string $startDate, string $endDate, ?int $excludeRentalId = null): bool
    {
        try {
            // التحقق من حالة السيارة
            $car = $this->find($carId);
            if (!$car || $car['status'] !== self::STATUS_AVAILABLE) {
                return false;
            }

            // التحقق من عدم وجود حجوزات متداخلة
            $query = "SELECT COUNT(*) FROM rentals 
                     WHERE car_id = ? 
                     AND status NOT IN ('cancelled', 'completed')
                     AND (
                         (start_date <= ? AND end_date >= ?) OR
                         (start_date <= ? AND end_date >= ?) OR
                         (start_date >= ? AND end_date <= ?)
                     )";
            
            $params = [
                $carId,
                $endDate, $startDate,    // التداخل من اليسار
                $startDate, $startDate,  // التداخل من اليمين
                $startDate, $endDate     // الاحتواء الكامل
            ];
            
            if ($excludeRentalId) {
                $query .= " AND id != ?";
                $params[] = $excludeRentalId;
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchColumn() == 0;
        } catch (\PDOException $e) {
            error_log("خطأ في isAvailable: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إنشاء سيارة جديدة
     * 
     * @param array $data بيانات السيارة
     * @return int|false معرف السيارة أو false
     */
    public function create(array $data): int|false
    {
        try {
            // التحقق من رقم اللوحة
            if ($this->plateNumberExists($data['plate_number'])) {
                return false;
            }

            // التحقق من رقم الشاسيه
            if (!empty($data['vin_number']) && $this->vinNumberExists($data['vin_number'])) {
                return false;
            }

            Database::beginTransaction();

            // حساب الصيانة القادمة
            if (isset($data['last_maintenance_odometer']) && isset($data['maintenance_interval'])) {
                $data['next_maintenance_due'] = $data['last_maintenance_odometer'] + $data['maintenance_interval'];
            }

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

            $carId = (int) $this->db->lastInsertId();

            // تسجيل في audit log
            $this->logAudit('create', 'cars', $carId, null, $data);

            Database::commit();
            
            return $carId;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في create car: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث سيارة
     * 
     * @param int $id معرف السيارة
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

            // التحقق من رقم اللوحة
            if (isset($data['plate_number']) && $this->plateNumberExists($data['plate_number'], $id)) {
                return false;
            }

            // التحقق من رقم الشاسيه
            if (!empty($data['vin_number']) && $this->vinNumberExists($data['vin_number'], $id)) {
                return false;
            }

            Database::beginTransaction();

            // حساب الصيانة القادمة
            if (isset($data['last_maintenance_odometer']) || isset($data['maintenance_interval'])) {
                $lastMaintenance = $data['last_maintenance_odometer'] ?? $oldData['last_maintenance_odometer'];
                $interval = $data['maintenance_interval'] ?? $oldData['maintenance_interval'];
                
                if ($lastMaintenance && $interval) {
                    $data['next_maintenance_due'] = $lastMaintenance + $interval;
                }
            }

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

            // تسجيل في audit log
            $this->logAudit('update', 'cars', $id, $oldData, $data);

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في update car: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف سيارة (حذف ناعم)
     * 
     * @param int $id معرف السيارة
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            // التحقق من عدم وجود حجوزات نشطة
            if ($this->hasActiveRentals($id)) {
                return false;
            }

            $oldData = $this->find($id);
            if (!$oldData) {
                return false;
            }

            Database::beginTransaction();

            $query = "UPDATE {$this->table} 
                     SET deleted_at = CURRENT_TIMESTAMP,
                         status = 'retired'
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$id]);

            // تسجيل في audit log
            $this->logAudit('delete', 'cars', $id, $oldData, null);

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في delete car: " . $e->getMessage());
            return false;
        }
    }

    /**
     * استعادة سيارة محذوفة
     * 
     * @param int $id معرف السيارة
     * @return bool
     */
    public function restore(int $id): bool
    {
        try {
            $query = "UPDATE {$this->table} 
                     SET deleted_at = NULL,
                         status = 'available'
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$id]);
        } catch (\PDOException $e) {
            error_log("خطأ في restore car: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تغيير حالة السيارة
     * 
     * @param int $id معرف السيارة
     * @param string $status الحالة الجديدة
     * @return bool
     */
    public function changeStatus(int $id, string $status): bool
    {
        try {
            $validStatuses = [
                self::STATUS_AVAILABLE,
                self::STATUS_RENTED,
                self::STATUS_MAINTENANCE,
                self::STATUS_SOLD,
                self::STATUS_RETIRED
            ];

            if (!in_array($status, $validStatuses)) {
                return false;
            }

            $query = "UPDATE {$this->table} 
                     SET status = ?,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$status, $id]);
        } catch (\PDOException $e) {
            error_log("خطأ في changeStatus: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث عداد السيارة
     * 
     * @param int $id معرف السيارة
     * @param int $odometer القراءة الجديدة
     * @return bool
     */
    public function updateOdometer(int $id, int $odometer): bool
    {
        try {
            $car = $this->find($id);
            if (!$car) {
                return false;
            }

            // التحقق من أن القراءة الجديدة أكبر من السابقة
            if ($odometer < $car['current_odometer']) {
                return false;
            }

            $query = "UPDATE {$this->table} 
                     SET current_odometer = ?,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$odometer, $id]);
        } catch (\PDOException $e) {
            error_log("خطأ في updateOdometer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * التحقق من وجود رقم لوحة
     * 
     * @param string $plateNumber رقم اللوحة
     * @param int|null $excludeId معرف السيارة المراد استثناؤها
     * @return bool
     */
    public function plateNumberExists(string $plateNumber, ?int $excludeId = null): bool
    {
        try {
            $query = "SELECT COUNT(*) FROM {$this->table} 
                     WHERE plate_number = ? AND deleted_at IS NULL";
            $params = [$plateNumber];
            
            if ($excludeId) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log("خطأ في plateNumberExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * التحقق من وجود رقم شاسيه
     * 
     * @param string $vinNumber رقم الشاسيه
     * @param int|null $excludeId معرف السيارة المراد استثناؤها
     * @return bool
     */
    public function vinNumberExists(string $vinNumber, ?int $excludeId = null): bool
    {
        try {
            $query = "SELECT COUNT(*) FROM {$this->table} 
                     WHERE vin_number = ? AND deleted_at IS NULL";
            $params = [$vinNumber];
            
            if ($excludeId) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log("خطأ في vinNumberExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * التحقق من وجود حجوزات نشطة
     * 
     * @param int $carId معرف السيارة
     * @return bool
     */
    public function hasActiveRentals(int $carId): bool
    {
        try {
            $query = "SELECT COUNT(*) FROM rentals 
                     WHERE car_id = ? 
                     AND status NOT IN ('cancelled', 'completed')";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$carId]);
            
            return $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log("خطأ في hasActiveRentals: " . $e->getMessage());
            return true;
        }
    }

    /**
     * الحصول على صور السيارة
     * 
     * @param int $carId معرف السيارة
     * @return array
     */
    public function getCarImages(int $carId): array
    {
        try {
            $query = "SELECT * FROM car_images 
                     WHERE car_id = ? 
                     ORDER BY is_primary DESC, display_order ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$carId]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getCarImages: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على السيارات المتاحة للإيجار
     * 
     * @param array $filters المصفيات
     * @return array
     */
    public function getAvailableCars(array $filters = []): array
    {
        $filters['status'] = self::STATUS_AVAILABLE;
        return $this->getAll($filters);
    }

    /**
     * الحصول على السيارات المميزة
     * 
     * @param int $limit عدد النتائج
     * @return array
     */
    public function getFeaturedCars(int $limit = 8): array
    {
        try {
            $query = "SELECT c.*, 
                     br.name as brand_name, br.logo as brand_logo,
                     m.name as model_name,
                     (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as primary_image
                     FROM {$this->table} c
                     INNER JOIN car_brands br ON c.brand_id = br.id
                     INNER JOIN car_models m ON c.model_id = m.id
                     WHERE c.deleted_at IS NULL 
                     AND c.is_featured = 1 
                     AND c.status = ?
                     ORDER BY c.updated_at DESC
                     LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(1, self::STATUS_AVAILABLE);
            $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getFeaturedCars: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تبديل حالة التمييز
     * 
     * @param int $id معرف السيارة
     * @return bool
     */
    public function toggleFeatured(int $id): bool
    {
        try {
            $query = "UPDATE {$this->table} 
                     SET is_featured = NOT is_featured,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$id]);
        } catch (\PDOException $e) {
            error_log("خطأ في toggleFeatured: " . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على إحصائيات السيارات
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        try {
            $query = "SELECT 
                     COUNT(*) as total_cars,
                     COUNT(CASE WHEN status = 'available' THEN 1 END) as available,
                     COUNT(CASE WHEN status = 'rented' THEN 1 END) as rented,
                     COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance,
                     COUNT(CASE WHEN is_featured = 1 THEN 1 END) as featured,
                     COUNT(CASE WHEN is_with_driver = 1 THEN 1 END) as with_driver,
                     AVG(daily_rate) as avg_daily_rate,
                     AVG(current_odometer) as avg_odometer
                     FROM {$this->table}
                     WHERE deleted_at IS NULL";
            
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
            $query = "SELECT c.*, 
                     br.name as brand_name,
                     m.name as model_name,
                     (c.current_odometer - c.last_maintenance_odometer) as km_since_maintenance
                     FROM {$this->table} c
                     INNER JOIN car_brands br ON c.brand_id = br.id
                     INNER JOIN car_models m ON c.model_id = m.id
                     WHERE c.deleted_at IS NULL 
                     AND c.status != 'sold'
                     AND c.status != 'retired'
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
     * الحصول على السيارات بوثائق منتهية الصلاحية
     * 
     * @param int $daysWarning عدد أيام التحذير المسبق
     * @return array
     */
    public function getCarsWithExpiringDocuments(int $daysWarning = 30): array
    {
        try {
            $warningDate = date('Y-m-d', strtotime("+{$daysWarning} days"));
            
            $query = "SELECT c.*, 
                     br.name as brand_name,
                     m.name as model_name,
                     CASE 
                         WHEN c.insurance_expiry_date <= CURRENT_DATE THEN 'expired'
                         WHEN c.insurance_expiry_date <= ? THEN 'expiring_soon'
                         ELSE 'valid'
                     END as insurance_status,
                     CASE 
                         WHEN c.registration_expiry_date <= CURRENT_DATE THEN 'expired'
                         WHEN c.registration_expiry_date <= ? THEN 'expiring_soon'
                         ELSE 'valid'
                     END as registration_status
                     FROM {$this->table} c
                     INNER JOIN car_brands br ON c.brand_id = br.id
                     INNER JOIN car_models m ON c.model_id = m.id
                     WHERE c.deleted_at IS NULL 
                     AND (c.insurance_expiry_date <= ? 
                          OR c.registration_expiry_date <= ?)
                     ORDER BY LEAST(c.insurance_expiry_date, c.registration_expiry_date) ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$warningDate, $warningDate, $warningDate, $warningDate]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getCarsWithExpiringDocuments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * البحث المتقدم في السيارات
     * 
     * @param array $criteria معايير البحث
     * @return array
     */
    public function advancedSearch(array $criteria): array
    {
        try {
            $query = "SELECT c.*, 
                     br.name as brand_name,
                     m.name as model_name,
                     b.name as branch_name,
                     (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as primary_image
                     FROM {$this->table} c
                     INNER JOIN car_brands br ON c.brand_id = br.id
                     INNER JOIN car_models m ON c.model_id = m.id
                     LEFT JOIN branches b ON c.branch_id = b.id
                     WHERE c.deleted_at IS NULL";
            
            $params = [];

            // معايير البحث المختلفة
            if (!empty($criteria['keyword'])) {
                $query .= " AND (c.plate_number LIKE ? OR c.nickname LIKE ? 
                           OR c.vin_number LIKE ? OR br.name LIKE ? OR m.name LIKE ?)";
                $keyword = '%' . $criteria['keyword'] . '%';
                $params = array_merge($params, array_fill(0, 5, $keyword));
            }

            if (!empty($criteria['price_min'])) {
                $query .= " AND c.daily_rate >= ?";
                $params[] = $criteria['price_min'];
            }

            if (!empty($criteria['price_max'])) {
                $query .= " AND c.daily_rate <= ?";
                $params[] = $criteria['price_max'];
            }

            if (!empty($criteria['year_min'])) {
                $query .= " AND c.manufacturing_year >= ?";
                $params[] = $criteria['year_min'];
            }

            if (!empty($criteria['year_max'])) {
                $query .= " AND c.manufacturing_year <= ?";
                $params[] = $criteria['year_max'];
            }

            if (!empty($criteria['seats'])) {
                $query .= " AND c.seats >= ?";
                $params[] = $criteria['seats'];
            }

            if (!empty($criteria['features'])) {
                $featurePlaceholders = implode(',', array_fill(0, count($criteria['features']), '?'));
                $query .= " AND c.id IN (
                    SELECT cfv.car_id 
                    FROM car_feature_values cfv 
                    WHERE cfv.feature_id IN ({$featurePlaceholders})
                    AND cfv.has_feature = 1
                    GROUP BY cfv.car_id
                    HAVING COUNT(DISTINCT cfv.feature_id) = ?
                )";
                $params = array_merge($params, $criteria['features']);
                $params[] = count($criteria['features']);
            }

            $query .= " ORDER BY c.id DESC LIMIT 100";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في advancedSearch: " . $e->getMessage());
            return [];
        }
    }
}

// تسجيل الملف في FileTracker
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 4');
