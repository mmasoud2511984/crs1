<?php
/**
 * File: Violation.php
 * Path: /app/models/Violation.php
 * Purpose: نموذج إدارة مخالفات السيارات
 * Dependencies: Core/Model.php, Database.php
 * Phase: Phase 8 - Violations & Reviews
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

class Violation extends Model
{
    protected $table = 'violations';
    protected $fillable = [
        'car_id', 'rental_id', 'customer_id', 'violation_number',
        'violation_date', 'violation_type', 'violation_location',
        'fine_amount', 'paid_by', 'payment_date', 'payment_reference',
        'status', 'document_path', 'notes', 'created_by'
    ];

    /**
     * الحصول على جميع المخالفات مع الفلترة
     */
    public static function getAll($filters = [])
    {
        $query = "SELECT v.*, 
                         c.plate_number, c.nickname as car_name,
                         CONCAT(cb.name, ' ', cm.name) as car_full_name,
                         cu.full_name as customer_name,
                         r.rental_number,
                         u.full_name as created_by_name
                  FROM violations v
                  INNER JOIN cars c ON v.car_id = c.id
                  INNER JOIN car_brands cb ON c.brand_id = cb.id
                  INNER JOIN car_models cm ON c.model_id = cm.id
                  LEFT JOIN customers cu ON v.customer_id = cu.id
                  LEFT JOIN rentals r ON v.rental_id = r.id
                  LEFT JOIN users u ON v.created_by = u.id
                  WHERE 1=1";

        $params = [];

        // فلتر حسب الحالة
        if (!empty($filters['status'])) {
            $query .= " AND v.status = :status";
            $params[':status'] = $filters['status'];
        }

        // فلتر حسب السيارة
        if (!empty($filters['car_id'])) {
            $query .= " AND v.car_id = :car_id";
            $params[':car_id'] = $filters['car_id'];
        }

        // فلتر حسب العميل
        if (!empty($filters['customer_id'])) {
            $query .= " AND v.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }

        // فلتر حسب من يدفع
        if (!empty($filters['paid_by'])) {
            $query .= " AND v.paid_by = :paid_by";
            $params[':paid_by'] = $filters['paid_by'];
        }

        // فلتر حسب التاريخ من
        if (!empty($filters['date_from'])) {
            $query .= " AND DATE(v.violation_date) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        // فلتر حسب التاريخ إلى
        if (!empty($filters['date_to'])) {
            $query .= " AND DATE(v.violation_date) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        // البحث
        if (!empty($filters['search'])) {
            $query .= " AND (v.violation_number LIKE :search 
                        OR c.plate_number LIKE :search 
                        OR cu.full_name LIKE :search
                        OR v.violation_type LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $query .= " ORDER BY v.violation_date DESC, v.created_at DESC";

        // Pagination
        if (isset($filters['limit']) && isset($filters['offset'])) {
            $query .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = (int)$filters['limit'];
            $params[':offset'] = (int)$filters['offset'];
        }

        return Database::query($query, $params);
    }

    /**
     * عدد المخالفات حسب الفلاتر
     */
    public static function count($filters = [])
    {
        $query = "SELECT COUNT(*) as total
                  FROM violations v
                  INNER JOIN cars c ON v.car_id = c.id
                  LEFT JOIN customers cu ON v.customer_id = cu.id
                  WHERE 1=1";

        $params = [];

        if (!empty($filters['status'])) {
            $query .= " AND v.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['car_id'])) {
            $query .= " AND v.car_id = :car_id";
            $params[':car_id'] = $filters['car_id'];
        }

        if (!empty($filters['customer_id'])) {
            $query .= " AND v.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }

        if (!empty($filters['paid_by'])) {
            $query .= " AND v.paid_by = :paid_by";
            $params[':paid_by'] = $filters['paid_by'];
        }

        if (!empty($filters['date_from'])) {
            $query .= " AND DATE(v.violation_date) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND DATE(v.violation_date) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $query .= " AND (v.violation_number LIKE :search 
                        OR c.plate_number LIKE :search 
                        OR cu.full_name LIKE :search
                        OR v.violation_type LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $result = Database::queryOne($query, $params);
        return $result['total'] ?? 0;
    }

    /**
     * الحصول على مخالفة واحدة مع التفاصيل
     */
    public static function getById($id)
    {
        $query = "SELECT v.*, 
                         c.plate_number, c.nickname as car_name, c.vin_number,
                         CONCAT(cb.name, ' ', cm.name, ' ', c.manufacturing_year) as car_full_name,
                         cu.full_name as customer_name, cu.phone as customer_phone, cu.email as customer_email,
                         r.rental_number, r.start_date as rental_start, r.end_date as rental_end,
                         u.full_name as created_by_name, u.email as created_by_email
                  FROM violations v
                  INNER JOIN cars c ON v.car_id = c.id
                  INNER JOIN car_brands cb ON c.brand_id = cb.id
                  INNER JOIN car_models cm ON c.model_id = cm.id
                  LEFT JOIN customers cu ON v.customer_id = cu.id
                  LEFT JOIN rentals r ON v.rental_id = r.id
                  LEFT JOIN users u ON v.created_by = u.id
                  WHERE v.id = :id";

        return Database::queryOne($query, [':id' => $id]);
    }

    /**
     * إنشاء مخالفة جديدة
     */
    public static function create($data)
    {
        try {
            Database::beginTransaction();

            // إذا كان هناك rental_id، نجلب customer_id تلقائياً
            if (!empty($data['rental_id']) && empty($data['customer_id'])) {
                $rental = Database::queryOne(
                    "SELECT customer_id FROM rentals WHERE id = :id",
                    [':id' => $data['rental_id']]
                );
                if ($rental) {
                    $data['customer_id'] = $rental['customer_id'];
                }
            }

            // إنشاء المخالفة
            $violationId = parent::insert($data);

            // Audit Log
            if (function_exists('logAudit')) {
                logAudit('create', 'violations', $violationId, null, $data);
            }

            Database::commit();

            FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 8');

            return $violationId;

        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Error creating violation: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * تحديث مخالفة
     */
    public static function updateById($id, $data)
    {
        try {
            Database::beginTransaction();

            // الحصول على البيانات القديمة للـ Audit Log
            $oldData = self::find($id);

            // تحديث المخالفة
            $updated = parent::update($id, $data);

            // Audit Log
            if (function_exists('logAudit')) {
                logAudit('update', 'violations', $id, $oldData, $data);
            }

            Database::commit();

            FileTracker::logModify(__FILE__, 350, FileTracker::countLines(__FILE__), 'Phase 8');

            return $updated;

        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Error updating violation: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * تحديث حالة المخالفة
     */
    public static function updateStatus($id, $status, $additionalData = [])
    {
        $data = array_merge(['status' => $status], $additionalData);
        return self::updateById($id, $data);
    }

    /**
     * تسجيل دفع مخالفة
     */
    public static function markAsPaid($id, $paidBy, $paymentDate, $paymentReference = null)
    {
        $data = [
            'status' => 'paid',
            'paid_by' => $paidBy,
            'payment_date' => $paymentDate,
            'payment_reference' => $paymentReference
        ];

        return self::updateById($id, $data);
    }

    /**
     * حذف مخالفة
     */
    public static function deleteById($id)
    {
        try {
            Database::beginTransaction();

            // الحصول على بيانات المخالفة للـ Audit Log
            $violation = self::find($id);

            // حذف ملف المستند إن وجد
            if (!empty($violation['document_path']) && file_exists($violation['document_path'])) {
                unlink($violation['document_path']);
            }

            // حذف المخالفة
            $deleted = parent::delete($id);

            // Audit Log
            if (function_exists('logAudit')) {
                logAudit('delete', 'violations', $id, $violation, null);
            }

            Database::commit();

            return $deleted;

        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Error deleting violation: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * الحصول على مخالفات سيارة معينة
     */
    public static function getByCarId($carId, $limit = null)
    {
        $query = "SELECT v.*, 
                         cu.full_name as customer_name,
                         r.rental_number
                  FROM violations v
                  LEFT JOIN customers cu ON v.customer_id = cu.id
                  LEFT JOIN rentals r ON v.rental_id = r.id
                  WHERE v.car_id = :car_id
                  ORDER BY v.violation_date DESC";

        if ($limit) {
            $query .= " LIMIT :limit";
            return Database::query($query, [':car_id' => $carId, ':limit' => $limit]);
        }

        return Database::query($query, [':car_id' => $carId]);
    }

    /**
     * الحصول على مخالفات عميل معين
     */
    public static function getByCustomerId($customerId, $limit = null)
    {
        $query = "SELECT v.*, 
                         c.plate_number, c.nickname as car_name,
                         CONCAT(cb.name, ' ', cm.name) as car_full_name,
                         r.rental_number
                  FROM violations v
                  INNER JOIN cars c ON v.car_id = c.id
                  INNER JOIN car_brands cb ON c.brand_id = cb.id
                  INNER JOIN car_models cm ON c.model_id = cm.id
                  LEFT JOIN rentals r ON v.rental_id = r.id
                  WHERE v.customer_id = :customer_id
                  ORDER BY v.violation_date DESC";

        if ($limit) {
            $query .= " LIMIT :limit";
            return Database::query($query, [':customer_id' => $customerId, ':limit' => $limit]);
        }

        return Database::query($query, [':customer_id' => $customerId]);
    }

    /**
     * الحصول على مخالفات عقد إيجار معين
     */
    public static function getByRentalId($rentalId)
    {
        $query = "SELECT v.*
                  FROM violations v
                  WHERE v.rental_id = :rental_id
                  ORDER BY v.violation_date DESC";

        return Database::query($query, [':rental_id' => $rentalId]);
    }

    /**
     * إحصائيات المخالفات
     */
    public static function getStatistics($filters = [])
    {
        $query = "SELECT 
                    COUNT(*) as total_violations,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                    SUM(CASE WHEN status = 'disputed' THEN 1 ELSE 0 END) as disputed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN paid_by = 'company' THEN 1 ELSE 0 END) as paid_by_company,
                    SUM(CASE WHEN paid_by = 'customer' THEN 1 ELSE 0 END) as paid_by_customer,
                    SUM(CASE WHEN paid_by = 'pending' THEN 1 ELSE 0 END) as payment_pending,
                    SUM(fine_amount) as total_fines,
                    SUM(CASE WHEN status = 'paid' THEN fine_amount ELSE 0 END) as paid_fines,
                    SUM(CASE WHEN status = 'pending' THEN fine_amount ELSE 0 END) as pending_fines
                  FROM violations
                  WHERE 1=1";

        $params = [];

        if (!empty($filters['date_from'])) {
            $query .= " AND DATE(violation_date) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND DATE(violation_date) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['car_id'])) {
            $query .= " AND car_id = :car_id";
            $params[':car_id'] = $filters['car_id'];
        }

        return Database::queryOne($query, $params);
    }

    /**
     * المخالفات المعلقة (تحتاج معالجة)
     */
    public static function getPending($limit = 10)
    {
        $query = "SELECT v.*, 
                         c.plate_number, c.nickname as car_name,
                         CONCAT(cb.name, ' ', cm.name) as car_full_name,
                         cu.full_name as customer_name
                  FROM violations v
                  INNER JOIN cars c ON v.car_id = c.id
                  INNER JOIN car_brands cb ON c.brand_id = cb.id
                  INNER JOIN car_models cm ON c.model_id = cm.id
                  LEFT JOIN customers cu ON v.customer_id = cu.id
                  WHERE v.status = 'pending'
                  ORDER BY v.violation_date ASC
                  LIMIT :limit";

        return Database::query($query, [':limit' => $limit]);
    }

    /**
     * رفع مستند المخالفة
     */
    public static function uploadDocument($id, $filePath)
    {
        return self::updateById($id, ['document_path' => $filePath]);
    }

    /**
     * توليد رقم مخالفة تلقائي
     */
    public static function generateViolationNumber()
    {
        $prefix = 'VIO';
        $year = date('Y');
        $month = date('m');

        // الحصول على آخر رقم في هذا الشهر
        $query = "SELECT violation_number 
                  FROM violations 
                  WHERE violation_number LIKE :pattern
                  ORDER BY id DESC 
                  LIMIT 1";

        $pattern = $prefix . '-' . $year . $month . '%';
        $lastNumber = Database::queryOne($query, [':pattern' => $pattern]);

        if ($lastNumber) {
            // استخراج الرقم التسلسلي
            $parts = explode('-', $lastNumber['violation_number']);
            $sequence = intval($parts[count($parts) - 1]) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . '-' . $year . $month . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * التحقق من وجود مخالفات معلقة لسيارة
     */
    public static function hasPendingViolations($carId)
    {
        $query = "SELECT COUNT(*) as count 
                  FROM violations 
                  WHERE car_id = :car_id 
                  AND status = 'pending'";

        $result = Database::queryOne($query, [':car_id' => $carId]);
        return $result['count'] > 0;
    }

    /**
     * المخالفات المتأخرة (أكثر من 30 يوم ولم تدفع)
     */
    public static function getOverdueViolations($days = 30)
    {
        $query = "SELECT v.*, 
                         c.plate_number, c.nickname as car_name,
                         CONCAT(cb.name, ' ', cm.name) as car_full_name,
                         cu.full_name as customer_name, cu.phone as customer_phone
                  FROM violations v
                  INNER JOIN cars c ON v.car_id = c.id
                  INNER JOIN car_brands cb ON c.brand_id = cb.id
                  INNER JOIN car_models cm ON c.model_id = cm.id
                  LEFT JOIN customers cu ON v.customer_id = cu.id
                  WHERE v.status = 'pending'
                  AND DATEDIFF(NOW(), v.violation_date) > :days
                  ORDER BY v.violation_date ASC";

        return Database::query($query, [':days' => $days]);
    }
}

// File Tracking
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 8');
