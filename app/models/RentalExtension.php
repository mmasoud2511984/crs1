<?php
/**
 * File: RentalExtension.php
 * Path: /app/models/RentalExtension.php
 * Purpose: Rental extension model - manages contract extensions
 * Dependencies: Model.php, Rental.php
 * Phase: Phase 7 - Rental System
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

class RentalExtension extends Model
{
    protected string $table = 'rental_extensions';
    protected array $fillable = [
        'rental_id', 'original_end_date', 'new_end_date', 
        'extension_days', 'extension_amount', 'payment_status', 'approved_by'
    ];

    /**
     * الحصول على جميع التمديدات لإيجار محدد
     * 
     * @param int $rentalId معرف الإيجار
     * @return array التمديدات
     */
    public function getByRentalId(int $rentalId): array
    {
        $sql = "SELECT re.*,
                u.full_name as approved_by_name
                FROM {$this->table} re
                LEFT JOIN users u ON re.approved_by = u.id
                WHERE re.rental_id = :rental_id
                ORDER BY re.created_at DESC";
        
        return Database::query($sql, ['rental_id' => $rentalId]);
    }

    /**
     * إنشاء تمديد جديد
     * 
     * @param array $data بيانات التمديد
     * @return int|null معرف التمديد
     */
    public function createExtension(array $data): ?int
    {
        try {
            Database::beginTransaction();
            
            // حساب الأيام والمبلغ
            $originalDate = new \DateTime($data['original_end_date']);
            $newDate = new \DateTime($data['new_end_date']);
            $days = $originalDate->diff($newDate)->days;
            
            // الحصول على السعر اليومي من الإيجار
            $rental = Database::query(
                "SELECT daily_rate, driver_daily_rate, with_driver FROM rentals WHERE id = :id",
                ['id' => $data['rental_id']]
            )[0] ?? null;
            
            if (!$rental) {
                throw new \Exception('Rental not found');
            }
            
            $dailyRate = floatval($rental['daily_rate']);
            $driverRate = $rental['with_driver'] ? floatval($rental['driver_daily_rate']) : 0;
            $extensionAmount = ($dailyRate + $driverRate) * $days;
            
            $data['extension_days'] = $days;
            $data['extension_amount'] = $extensionAmount;
            
            // إنشاء التمديد
            $extensionId = $this->create($data);
            
            if (!$extensionId) {
                throw new \Exception('Failed to create extension');
            }
            
            // تحديث الإيجار
            $this->updateRentalForExtension($data['rental_id'], $data['new_end_date'], $extensionAmount);
            
            Database::commit();
            return $extensionId;
            
        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Create extension error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * تحديث الإيجار عند التمديد
     * 
     * @param int $rentalId معرف الإيجار
     * @param string $newEndDate التاريخ الجديد
     * @param float $extensionAmount مبلغ التمديد
     * @return bool نجاح العملية
     */
    private function updateRentalForExtension(int $rentalId, string $newEndDate, float $extensionAmount): bool
    {
        // الحصول على الإيجار الحالي
        $rental = Database::query(
            "SELECT total_amount, remaining_amount, start_date FROM rentals WHERE id = :id",
            ['id' => $rentalId]
        )[0] ?? null;
        
        if (!$rental) {
            return false;
        }
        
        // حساب الأيام الجديدة
        $startDate = new \DateTime($rental['start_date']);
        $endDate = new \DateTime($newEndDate);
        $totalDays = $startDate->diff($endDate)->days + 1;
        
        // تحديث الإيجار
        $newTotalAmount = floatval($rental['total_amount']) + $extensionAmount;
        $newRemainingAmount = floatval($rental['remaining_amount']) + $extensionAmount;
        
        return Database::query(
            "UPDATE rentals SET 
             end_date = :end_date,
             rental_duration_days = :duration_days,
             total_amount = :total_amount,
             remaining_amount = :remaining_amount,
             status = 'extended'
             WHERE id = :id",
            [
                'end_date' => $newEndDate,
                'duration_days' => $totalDays,
                'total_amount' => $newTotalAmount,
                'remaining_amount' => $newRemainingAmount,
                'id' => $rentalId
            ]
        );
    }

    /**
     * تأكيد دفع التمديد
     * 
     * @param int $id معرف التمديد
     * @return bool نجاح العملية
     */
    public function markAsPaid(int $id): bool
    {
        return $this->update($id, ['payment_status' => 'paid']);
    }

    /**
     * الحصول على جميع التمديدات مع التفاصيل
     * 
     * @param array $filters الفلاتر
     * @param int $page رقم الصفحة
     * @param int $perPage عدد السجلات في الصفحة
     * @return array النتائج
     */
    public function getAllWithDetails(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT re.*,
                r.rental_number,
                c.name as customer_name,
                car.brand_name,
                car.model_name,
                car.plate_number,
                u.full_name as approved_by_name
                FROM {$this->table} re
                INNER JOIN rentals r ON re.rental_id = r.id
                INNER JOIN customers c ON r.customer_id = c.id
                INNER JOIN (
                    SELECT cars.*, cb.name as brand_name, cm.name as model_name
                    FROM cars
                    LEFT JOIN car_brands cb ON cars.brand_id = cb.id
                    LEFT JOIN car_models cm ON cars.model_id = cm.id
                ) car ON r.car_id = car.id
                LEFT JOIN users u ON re.approved_by = u.id
                WHERE 1=1";
        
        $params = [];
        
        // تطبيق الفلاتر
        if (!empty($filters['payment_status'])) {
            $sql .= " AND re.payment_status = :payment_status";
            $params['payment_status'] = $filters['payment_status'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(re.created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(re.created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        // إجمالي العدد
        $countSql = "SELECT COUNT(*) as total FROM ({$sql}) as count_table";
        $total = Database::query($countSql, $params)[0]['total'] ?? 0;
        
        // ترتيب وصفحات
        $sql .= " ORDER BY re.created_at DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $perPage;
        $params['offset'] = $offset;
        
        $extensions = Database::query($sql, $params);
        
        return [
            'data' => $extensions,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    /**
     * الحصول على إحصائيات التمديدات
     * 
     * @param array $filters الفلاتر
     * @return array الإحصائيات
     */
    public function getExtensionStats(array $filters = []): array
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
        
        $sql = "SELECT 
                COUNT(*) as total_extensions,
                SUM(extension_days) as total_days,
                SUM(extension_amount) as total_amount,
                SUM(CASE WHEN payment_status = 'paid' THEN extension_amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN payment_status = 'pending' THEN extension_amount ELSE 0 END) as pending_amount
                FROM {$this->table} {$where}";
        
        $result = Database::query($sql, $params);
        return $result[0] ?? [];
    }

    /**
     * التحقق من إمكانية التمديد
     * 
     * @param int $rentalId معرف الإيجار
     * @param string $newEndDate التاريخ الجديد
     * @return array النتيجة
     */
    public function canExtend(int $rentalId, string $newEndDate): array
    {
        // الحصول على الإيجار
        $rental = Database::query(
            "SELECT end_date, status, car_id FROM rentals WHERE id = :id",
            ['id' => $rentalId]
        )[0] ?? null;
        
        if (!$rental) {
            return [
                'can_extend' => false,
                'reason' => 'Rental not found'
            ];
        }
        
        // التحقق من الحالة
        if (!in_array($rental['status'], ['active', 'extended'])) {
            return [
                'can_extend' => false,
                'reason' => 'Rental status does not allow extension'
            ];
        }
        
        // التحقق من التاريخ
        $currentEndDate = new \DateTime($rental['end_date']);
        $requestedEndDate = new \DateTime($newEndDate);
        
        if ($requestedEndDate <= $currentEndDate) {
            return [
                'can_extend' => false,
                'reason' => 'New end date must be after current end date'
            ];
        }
        
        // التحقق من توفر السيارة
        $conflicts = Database::query(
            "SELECT id FROM rentals 
             WHERE car_id = :car_id 
             AND id != :rental_id
             AND status IN ('confirmed', 'active')
             AND start_date <= :new_end_date
             AND end_date >= :current_end_date",
            [
                'car_id' => $rental['car_id'],
                'rental_id' => $rentalId,
                'new_end_date' => $newEndDate,
                'current_end_date' => $rental['end_date']
            ]
        );
        
        if (!empty($conflicts)) {
            return [
                'can_extend' => false,
                'reason' => 'Car is not available for the requested extension period'
            ];
        }
        
        return [
            'can_extend' => true,
            'reason' => null
        ];
    }
}

// تسجيل الملف
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 7');
