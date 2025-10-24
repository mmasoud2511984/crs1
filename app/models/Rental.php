<?php
/**
 * File: Rental.php
 * Path: /app/models/Rental.php
 * Purpose: Rental contract model - manages rental contracts, status, payments
 * Dependencies: Model.php, Car.php, Customer.php
 * Phase: Phase 7 - Rental System
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

class Rental extends Model
{
    protected string $table = 'rentals';
    protected array $fillable = [
        'rental_number', 'car_id', 'customer_id', 'branch_id',
        'pickup_type', 'pickup_location', 'pickup_lat', 'pickup_lng',
        'delivery_type', 'delivery_location', 'delivery_lat', 'delivery_lng',
        'start_date', 'end_date', 'actual_return_date', 'rental_duration_days',
        'with_driver', 'driver_name', 'driver_phone',
        'daily_rate', 'driver_daily_rate', 'total_amount',
        'paid_amount', 'remaining_amount', 'deposit_amount', 'deposit_returned',
        'payment_status', 'odometer_start', 'odometer_end',
        'fuel_level_start', 'fuel_level_end',
        'car_condition_start', 'car_condition_end',
        'status', 'cancellation_reason', 'reminder_sent', 'reminder_sent_at',
        'notes', 'contract_pdf_path',
        'created_by', 'confirmed_by', 'completed_by'
    ];

    /**
     * الحصول على جميع الإيجارات مع التفاصيل
     * 
     * @param array $filters الفلاتر (status, branch_id, customer_id, car_id, payment_status, date_from, date_to)
     * @param int $page رقم الصفحة
     * @param int $perPage عدد السجلات في الصفحة
     * @return array النتائج
     */
    public function getAllWithDetails(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT r.*,
                c.name as customer_name,
                c.phone as customer_phone,
                c.email as customer_email,
                car.brand_name,
                car.model_name,
                car.year,
                car.plate_number,
                b.name as branch_name,
                u.full_name as created_by_name
                FROM {$this->table} r
                INNER JOIN customers c ON r.customer_id = c.id
                INNER JOIN (
                    SELECT cars.*, 
                           cb.name as brand_name,
                           cm.name as model_name
                    FROM cars
                    LEFT JOIN car_brands cb ON cars.brand_id = cb.id
                    LEFT JOIN car_models cm ON cars.model_id = cm.id
                ) car ON r.car_id = car.id
                LEFT JOIN branches b ON r.branch_id = b.id
                LEFT JOIN users u ON r.created_by = u.id
                WHERE 1=1";
        
        $params = [];
        
        // تطبيق الفلاتر
        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['branch_id'])) {
            $sql .= " AND r.branch_id = :branch_id";
            $params['branch_id'] = $filters['branch_id'];
        }
        
        if (!empty($filters['customer_id'])) {
            $sql .= " AND r.customer_id = :customer_id";
            $params['customer_id'] = $filters['customer_id'];
        }
        
        if (!empty($filters['car_id'])) {
            $sql .= " AND r.car_id = :car_id";
            $params['car_id'] = $filters['car_id'];
        }
        
        if (!empty($filters['payment_status'])) {
            $sql .= " AND r.payment_status = :payment_status";
            $params['payment_status'] = $filters['payment_status'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(r.start_date) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(r.end_date) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (r.rental_number LIKE :search 
                      OR c.name LIKE :search 
                      OR c.phone LIKE :search
                      OR car.plate_number LIKE :search)";
            $params['search'] = "%{$filters['search']}%";
        }
        
        // إجمالي العدد
        $countSql = "SELECT COUNT(*) as total FROM ({$sql}) as count_table";
        $total = Database::query($countSql, $params)[0]['total'] ?? 0;
        
        // ترتيب وصفحات
        $sql .= " ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $perPage;
        $params['offset'] = $offset;
        
        $rentals = Database::query($sql, $params);
        
        return [
            'data' => $rentals,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    /**
     * الحصول على إيجار محدد مع جميع التفاصيل
     * 
     * @param int $id معرف الإيجار
     * @return array|null بيانات الإيجار
     */
    public function getWithFullDetails(int $id): ?array
    {
        $sql = "SELECT r.*,
                c.name as customer_name,
                c.phone as customer_phone,
                c.email as customer_email,
                c.id_number as customer_id_number,
                c.license_number as customer_license_number,
                car.id as car_id,
                car.brand_name,
                car.model_name,
                car.year,
                car.plate_number,
                car.color,
                car.vin_number,
                b.name as branch_name,
                b.phone as branch_phone,
                b.address as branch_address,
                u.full_name as created_by_name
                FROM {$this->table} r
                INNER JOIN customers c ON r.customer_id = c.id
                INNER JOIN (
                    SELECT cars.*, 
                           cb.name as brand_name,
                           cm.name as model_name
                    FROM cars
                    LEFT JOIN car_brands cb ON cars.brand_id = cb.id
                    LEFT JOIN car_models cm ON cars.model_id = cm.id
                ) car ON r.car_id = car.id
                LEFT JOIN branches b ON r.branch_id = b.id
                LEFT JOIN users u ON r.created_by = u.id
                WHERE r.id = :id";
        
        $result = Database::query($sql, ['id' => $id]);
        return $result[0] ?? null;
    }

    /**
     * إنشاء عقد إيجار جديد
     * 
     * @param array $data بيانات العقد
     * @return int|null معرف العقد
     */
    public function createRental(array $data): ?int
    {
        try {
            Database::beginTransaction();
            
            // توليد رقم العقد
            $data['rental_number'] = $this->generateRentalNumber();
            
            // حساب المبالغ
            $calculations = $this->calculateRentalAmounts($data);
            $data = array_merge($data, $calculations);
            
            // إنشاء العقد
            $rentalId = $this->create($data);
            
            if (!$rentalId) {
                throw new \Exception('Failed to create rental');
            }
            
            // تحديث حالة السيارة
            Database::query(
                "UPDATE cars SET status = 'rented', current_rental_id = :rental_id WHERE id = :car_id",
                ['rental_id' => $rentalId, 'car_id' => $data['car_id']]
            );
            
            Database::commit();
            return $rentalId;
            
        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Create rental error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * تحديث عقد إيجار
     * 
     * @param int $id معرف العقد
     * @param array $data البيانات الجديدة
     * @return bool نجاح العملية
     */
    public function updateRental(int $id, array $data): bool
    {
        try {
            Database::beginTransaction();
            
            // إعادة حساب المبالغ إذا تغيرت التواريخ أو الأسعار
            if (isset($data['start_date']) || isset($data['end_date']) || 
                isset($data['daily_rate']) || isset($data['with_driver'])) {
                
                $rental = $this->find($id);
                $mergedData = array_merge($rental, $data);
                $calculations = $this->calculateRentalAmounts($mergedData);
                $data = array_merge($data, $calculations);
            }
            
            $updated = $this->update($id, $data);
            
            if (!$updated) {
                throw new \Exception('Failed to update rental');
            }
            
            Database::commit();
            return true;
            
        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Update rental error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تأكيد الإيجار
     * 
     * @param int $id معرف الإيجار
     * @param int $userId معرف المستخدم المؤكد
     * @return bool نجاح العملية
     */
    public function confirmRental(int $id, int $userId): bool
    {
        return $this->update($id, [
            'status' => 'confirmed',
            'confirmed_by' => $userId
        ]);
    }

    /**
     * تنشيط الإيجار (بدء الإيجار)
     * 
     * @param int $id معرف الإيجار
     * @param array $data بيانات البداية (odometer_start, fuel_level_start, car_condition_start)
     * @return bool نجاح العملية
     */
    public function activateRental(int $id, array $data): bool
    {
        $data['status'] = 'active';
        return $this->update($id, $data);
    }

    /**
     * إنهاء الإيجار (استلام السيارة)
     * 
     * @param int $id معرف الإيجار
     * @param array $data بيانات النهاية (actual_return_date, odometer_end, fuel_level_end, car_condition_end)
     * @param int $userId معرف المستخدم
     * @return bool نجاح العملية
     */
    public function completeRental(int $id, array $data, int $userId): bool
    {
        try {
            Database::beginTransaction();
            
            $data['status'] = 'completed';
            $data['completed_by'] = $userId;
            
            $updated = $this->update($id, $data);
            
            if (!$updated) {
                throw new \Exception('Failed to complete rental');
            }
            
            // تحديث حالة السيارة
            $rental = $this->find($id);
            Database::query(
                "UPDATE cars SET status = 'available', current_rental_id = NULL WHERE id = :car_id",
                ['car_id' => $rental['car_id']]
            );
            
            Database::commit();
            return true;
            
        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Complete rental error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إلغاء الإيجار
     * 
     * @param int $id معرف الإيجار
     * @param string $reason سبب الإلغاء
     * @return bool نجاح العملية
     */
    public function cancelRental(int $id, string $reason): bool
    {
        try {
            Database::beginTransaction();
            
            $updated = $this->update($id, [
                'status' => 'cancelled',
                'cancellation_reason' => $reason
            ]);
            
            if (!$updated) {
                throw new \Exception('Failed to cancel rental');
            }
            
            // تحديث حالة السيارة
            $rental = $this->find($id);
            Database::query(
                "UPDATE cars SET status = 'available', current_rental_id = NULL WHERE id = :car_id",
                ['car_id' => $rental['car_id']]
            );
            
            Database::commit();
            return true;
            
        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Cancel rental error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * توليد رقم عقد إيجار فريد
     * 
     * @return string رقم العقد
     */
    private function generateRentalNumber(): string
    {
        $prefix = 'RNT';
        $date = date('Ymd');
        
        // الحصول على آخر رقم في اليوم
        $sql = "SELECT rental_number FROM {$this->table} 
                WHERE rental_number LIKE :pattern 
                ORDER BY id DESC LIMIT 1";
        
        $result = Database::query($sql, ['pattern' => "{$prefix}{$date}%"]);
        
        if (!empty($result)) {
            $lastNumber = intval(substr($result[0]['rental_number'], -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return $prefix . $date . $newNumber;
    }

    /**
     * حساب مبالغ الإيجار
     * 
     * @param array $data بيانات الإيجار
     * @return array المبالغ المحسوبة
     */
    private function calculateRentalAmounts(array $data): array
    {
        $startDate = new \DateTime($data['start_date']);
        $endDate = new \DateTime($data['end_date']);
        $days = $startDate->diff($endDate)->days + 1;
        
        $dailyRate = floatval($data['daily_rate']);
        $driverRate = isset($data['with_driver']) && $data['with_driver'] ? 
                      floatval($data['driver_daily_rate'] ?? 0) : 0;
        
        $totalAmount = ($dailyRate + $driverRate) * $days;
        
        // حساب العربون
        $depositPercentage = floatval(setting('rental_deposit_percentage', 20));
        $depositAmount = $totalAmount * ($depositPercentage / 100);
        
        // حساب المبلغ المتبقي
        $paidAmount = floatval($data['paid_amount'] ?? 0);
        $remainingAmount = $totalAmount - $paidAmount;
        
        return [
            'rental_duration_days' => $days,
            'total_amount' => $totalAmount,
            'deposit_amount' => $depositAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'payment_status' => $this->getPaymentStatus($paidAmount, $totalAmount)
        ];
    }

    /**
     * تحديد حالة الدفع
     * 
     * @param float $paidAmount المبلغ المدفوع
     * @param float $totalAmount المبلغ الإجمالي
     * @return string حالة الدفع
     */
    private function getPaymentStatus(float $paidAmount, float $totalAmount): string
    {
        if ($paidAmount <= 0) {
            return 'pending';
        } elseif ($paidAmount >= $totalAmount) {
            return 'paid';
        } else {
            return 'partial';
        }
    }

    /**
     * الحصول على الإيجارات النشطة
     * 
     * @return array الإيجارات
     */
    public function getActiveRentals(): array
    {
        $sql = "SELECT r.*, c.name as customer_name, car.brand_name, car.model_name, car.plate_number
                FROM {$this->table} r
                INNER JOIN customers c ON r.customer_id = c.id
                INNER JOIN (
                    SELECT cars.*, cb.name as brand_name, cm.name as model_name
                    FROM cars
                    LEFT JOIN car_brands cb ON cars.brand_id = cb.id
                    LEFT JOIN car_models cm ON cars.model_id = cm.id
                ) car ON r.car_id = car.id
                WHERE r.status IN ('confirmed', 'active')
                ORDER BY r.start_date ASC";
        
        return Database::query($sql);
    }

    /**
     * الحصول على الإيجارات المتأخرة
     * 
     * @return array الإيجارات
     */
    public function getOverdueRentals(): array
    {
        $sql = "SELECT r.*, c.name as customer_name, c.phone as customer_phone,
                car.brand_name, car.model_name, car.plate_number
                FROM {$this->table} r
                INNER JOIN customers c ON r.customer_id = c.id
                INNER JOIN (
                    SELECT cars.*, cb.name as brand_name, cm.name as model_name
                    FROM cars
                    LEFT JOIN car_brands cb ON cars.brand_id = cb.id
                    LEFT JOIN car_models cm ON cars.model_id = cm.id
                ) car ON r.car_id = car.id
                WHERE r.status = 'active' 
                AND r.end_date < NOW()
                ORDER BY r.end_date ASC";
        
        return Database::query($sql);
    }

    /**
     * الحصول على الإيجارات للتقويم
     * 
     * @param string $start تاريخ البداية
     * @param string $end تاريخ النهاية
     * @return array الإيجارات
     */
    public function getRentalsForCalendar(string $start, string $end): array
    {
        $sql = "SELECT r.id, r.rental_number, r.start_date, r.end_date, r.status,
                c.name as customer_name,
                CONCAT(car.brand_name, ' ', car.model_name, ' (', car.plate_number, ')') as car_info
                FROM {$this->table} r
                INNER JOIN customers c ON r.customer_id = c.id
                INNER JOIN (
                    SELECT cars.*, cb.name as brand_name, cm.name as model_name
                    FROM cars
                    LEFT JOIN car_brands cb ON cars.brand_id = cb.id
                    LEFT JOIN car_models cm ON cars.model_id = cm.id
                ) car ON r.car_id = car.id
                WHERE r.status NOT IN ('cancelled')
                AND (
                    (r.start_date BETWEEN :start AND :end) OR
                    (r.end_date BETWEEN :start AND :end) OR
                    (r.start_date <= :start AND r.end_date >= :end)
                )
                ORDER BY r.start_date ASC";
        
        return Database::query($sql, ['start' => $start, 'end' => $end]);
    }

    /**
     * الحصول على إحصائيات الإيجارات
     * 
     * @param array $filters الفلاتر
     * @return array الإحصائيات
     */
    public function getRentalStats(array $filters = []): array
    {
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $where .= " AND DATE(created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where .= " AND DATE(created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['branch_id'])) {
            $where .= " AND branch_id = :branch_id";
            $params['branch_id'] = $filters['branch_id'];
        }
        
        $sql = "SELECT 
                COUNT(*) as total_rentals,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(total_amount) as total_revenue,
                SUM(paid_amount) as total_paid,
                SUM(remaining_amount) as total_remaining
                FROM {$this->table} {$where}";
        
        $result = Database::query($sql, $params);
        return $result[0] ?? [];
    }
}

// تسجيل الملف
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 7');
