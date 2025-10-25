<?php
/**
 * File: Review.php
 * Path: /app/models/Review.php
 * Purpose: نموذج إدارة تقييمات العملاء للسيارات
 * Dependencies: Core/Model.php, Database.php
 * Phase: Phase 8 - Violations & Reviews
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

class Review extends Model
{
    protected $table = 'reviews';
    protected $fillable = [
        'rental_id', 'car_id', 'customer_id', 'rating', 'review_title',
        'review_text', 'is_approved', 'approved_by', 'approved_at',
        'response_text', 'responded_by', 'responded_at'
    ];

    /**
     * الحصول على جميع التقييمات مع الفلترة
     */
    public static function getAll($filters = [])
    {
        $query = "SELECT r.*, 
                         c.plate_number, c.nickname as car_name,
                         CONCAT(cb.name, ' ', cm.name, ' ', c.manufacturing_year) as car_full_name,
                         cu.full_name as customer_name, cu.email as customer_email,
                         re.rental_number,
                         u1.full_name as approved_by_name,
                         u2.full_name as responded_by_name
                  FROM reviews r
                  INNER JOIN cars c ON r.car_id = c.id
                  INNER JOIN car_brands cb ON c.brand_id = cb.id
                  INNER JOIN car_models cm ON c.model_id = cm.id
                  INNER JOIN customers cu ON r.customer_id = cu.id
                  INNER JOIN rentals re ON r.rental_id = re.id
                  LEFT JOIN users u1 ON r.approved_by = u1.id
                  LEFT JOIN users u2 ON r.responded_by = u2.id
                  WHERE 1=1";

        $params = [];

        // فلتر حسب الموافقة
        if (isset($filters['is_approved'])) {
            $query .= " AND r.is_approved = :is_approved";
            $params[':is_approved'] = $filters['is_approved'];
        }

        // فلتر حسب التقييم
        if (!empty($filters['rating'])) {
            $query .= " AND r.rating = :rating";
            $params[':rating'] = $filters['rating'];
        }

        // فلتر حسب السيارة
        if (!empty($filters['car_id'])) {
            $query .= " AND r.car_id = :car_id";
            $params[':car_id'] = $filters['car_id'];
        }

        // فلتر حسب العميل
        if (!empty($filters['customer_id'])) {
            $query .= " AND r.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }

        // فلتر حسب وجود رد
        if (isset($filters['has_response'])) {
            if ($filters['has_response']) {
                $query .= " AND r.response_text IS NOT NULL";
            } else {
                $query .= " AND r.response_text IS NULL";
            }
        }

        // البحث
        if (!empty($filters['search'])) {
            $query .= " AND (r.review_title LIKE :search 
                        OR r.review_text LIKE :search 
                        OR cu.full_name LIKE :search
                        OR c.plate_number LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $query .= " ORDER BY r.created_at DESC";

        // Pagination
        if (isset($filters['limit']) && isset($filters['offset'])) {
            $query .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = (int)$filters['limit'];
            $params[':offset'] = (int)$filters['offset'];
        }

        return Database::query($query, $params);
    }

    /**
     * عدد التقييمات حسب الفلاتر
     */
    public static function count($filters = [])
    {
        $query = "SELECT COUNT(*) as total
                  FROM reviews r
                  INNER JOIN cars c ON r.car_id = c.id
                  INNER JOIN customers cu ON r.customer_id = cu.id
                  WHERE 1=1";

        $params = [];

        if (isset($filters['is_approved'])) {
            $query .= " AND r.is_approved = :is_approved";
            $params[':is_approved'] = $filters['is_approved'];
        }

        if (!empty($filters['rating'])) {
            $query .= " AND r.rating = :rating";
            $params[':rating'] = $filters['rating'];
        }

        if (!empty($filters['car_id'])) {
            $query .= " AND r.car_id = :car_id";
            $params[':car_id'] = $filters['car_id'];
        }

        if (!empty($filters['customer_id'])) {
            $query .= " AND r.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }

        if (isset($filters['has_response'])) {
            if ($filters['has_response']) {
                $query .= " AND r.response_text IS NOT NULL";
            } else {
                $query .= " AND r.response_text IS NULL";
            }
        }

        if (!empty($filters['search'])) {
            $query .= " AND (r.review_title LIKE :search 
                        OR r.review_text LIKE :search 
                        OR cu.full_name LIKE :search
                        OR c.plate_number LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $result = Database::queryOne($query, $params);
        return $result['total'] ?? 0;
    }

    /**
     * الحصول على تقييم واحد مع التفاصيل
     */
    public static function getById($id)
    {
        $query = "SELECT r.*, 
                         c.plate_number, c.nickname as car_name,
                         CONCAT(cb.name, ' ', cm.name, ' ', c.manufacturing_year) as car_full_name,
                         cu.full_name as customer_name, cu.email as customer_email, cu.phone as customer_phone,
                         re.rental_number, re.start_date, re.end_date, re.actual_return_date,
                         u1.full_name as approved_by_name,
                         u2.full_name as responded_by_name
                  FROM reviews r
                  INNER JOIN cars c ON r.car_id = c.id
                  INNER JOIN car_brands cb ON c.brand_id = cb.id
                  INNER JOIN car_models cm ON c.model_id = cm.id
                  INNER JOIN customers cu ON r.customer_id = cu.id
                  INNER JOIN rentals re ON r.rental_id = re.id
                  LEFT JOIN users u1 ON r.approved_by = u1.id
                  LEFT JOIN users u2 ON r.responded_by = u2.id
                  WHERE r.id = :id";

        return Database::queryOne($query, [':id' => $id]);
    }

    /**
     * إنشاء تقييم جديد
     */
    public static function create($data)
    {
        try {
            Database::beginTransaction();

            // التحقق من عدم وجود تقييم مسبق لنفس العقد
            $existing = Database::queryOne(
                "SELECT id FROM reviews WHERE rental_id = :rental_id AND customer_id = :customer_id",
                [':rental_id' => $data['rental_id'], ':customer_id' => $data['customer_id']]
            );

            if ($existing) {
                throw new \Exception('review_already_exists');
            }

            // التحقق من أن العقد مكتمل
            $rental = Database::queryOne(
                "SELECT status FROM rentals WHERE id = :id",
                [':id' => $data['rental_id']]
            );

            if (!$rental || $rental['status'] !== 'completed') {
                throw new \Exception('rental_not_completed');
            }

            // إنشاء التقييم
            $reviewId = parent::insert($data);

            // Audit Log
            if (function_exists('logAudit')) {
                logAudit('create', 'reviews', $reviewId, null, $data);
            }

            Database::commit();

            FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 8');

            return $reviewId;

        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Error creating review: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * تحديث تقييم
     */
    public static function updateById($id, $data)
    {
        try {
            Database::beginTransaction();

            // الحصول على البيانات القديمة
            $oldData = self::find($id);

            // تحديث التقييم
            $updated = parent::update($id, $data);

            // Audit Log
            if (function_exists('logAudit')) {
                logAudit('update', 'reviews', $id, $oldData, $data);
            }

            Database::commit();

            FileTracker::logModify(__FILE__, 250, FileTracker::countLines(__FILE__), 'Phase 8');

            return $updated;

        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Error updating review: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * الموافقة على تقييم
     */
    public static function approve($id, $userId)
    {
        $data = [
            'is_approved' => 1,
            'approved_by' => $userId,
            'approved_at' => date('Y-m-d H:i:s')
        ];

        return self::updateById($id, $data);
    }

    /**
     * رفض تقييم (إلغاء الموافقة)
     */
    public static function reject($id)
    {
        $data = [
            'is_approved' => 0,
            'approved_by' => null,
            'approved_at' => null
        ];

        return self::updateById($id, $data);
    }

    /**
     * إضافة رد على تقييم
     */
    public static function addResponse($id, $responseText, $userId)
    {
        $data = [
            'response_text' => $responseText,
            'responded_by' => $userId,
            'responded_at' => date('Y-m-d H:i:s')
        ];

        return self::updateById($id, $data);
    }

    /**
     * حذف رد من تقييم
     */
    public static function removeResponse($id)
    {
        $data = [
            'response_text' => null,
            'responded_by' => null,
            'responded_at' => null
        ];

        return self::updateById($id, $data);
    }

    /**
     * حذف تقييم
     */
    public static function deleteById($id)
    {
        try {
            Database::beginTransaction();

            // الحصول على بيانات التقييم
            $review = self::find($id);

            // حذف التقييم
            $deleted = parent::delete($id);

            // Audit Log
            if (function_exists('logAudit')) {
                logAudit('delete', 'reviews', $id, $review, null);
            }

            Database::commit();

            return $deleted;

        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Error deleting review: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * الحصول على تقييمات سيارة معينة (المعتمدة فقط)
     */
    public static function getByCarId($carId, $approvedOnly = true, $limit = null)
    {
        $query = "SELECT r.*, 
                         cu.full_name as customer_name,
                         re.rental_number
                  FROM reviews r
                  INNER JOIN customers cu ON r.customer_id = cu.id
                  INNER JOIN rentals re ON r.rental_id = re.id
                  WHERE r.car_id = :car_id";

        $params = [':car_id' => $carId];

        if ($approvedOnly) {
            $query .= " AND r.is_approved = 1";
        }

        $query .= " ORDER BY r.created_at DESC";

        if ($limit) {
            $query .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }

        return Database::query($query, $params);
    }

    /**
     * الحصول على تقييمات عميل معين
     */
    public static function getByCustomerId($customerId, $limit = null)
    {
        $query = "SELECT r.*, 
                         c.plate_number, c.nickname as car_name,
                         CONCAT(cb.name, ' ', cm.name) as car_full_name,
                         re.rental_number
                  FROM reviews r
                  INNER JOIN cars c ON r.car_id = c.id
                  INNER JOIN car_brands cb ON c.brand_id = cb.id
                  INNER JOIN car_models cm ON c.model_id = cm.id
                  INNER JOIN rentals re ON r.rental_id = re.id
                  WHERE r.customer_id = :customer_id
                  ORDER BY r.created_at DESC";

        if ($limit) {
            $query .= " LIMIT :limit";
            return Database::query($query, [':customer_id' => $customerId, ':limit' => $limit]);
        }

        return Database::query($query, [':customer_id' => $customerId]);
    }

    /**
     * الحصول على تقييم عقد إيجار (إن وجد)
     */
    public static function getByRentalId($rentalId)
    {
        $query = "SELECT r.*, 
                         cu.full_name as customer_name
                  FROM reviews r
                  INNER JOIN customers cu ON r.customer_id = cu.id
                  WHERE r.rental_id = :rental_id";

        return Database::queryOne($query, [':rental_id' => $rentalId]);
    }

    /**
     * متوسط تقييم سيارة معينة
     */
    public static function getCarAverageRating($carId)
    {
        $query = "SELECT 
                    AVG(rating) as average_rating,
                    COUNT(*) as total_reviews
                  FROM reviews
                  WHERE car_id = :car_id
                  AND is_approved = 1";

        $result = Database::queryOne($query, [':car_id' => $carId]);
        
        return [
            'average' => $result['average_rating'] ? round($result['average_rating'], 1) : 0,
            'total' => $result['total_reviews'] ?? 0
        ];
    }

    /**
     * إحصائيات التقييمات
     */
    public static function getStatistics($filters = [])
    {
        $query = "SELECT 
                    COUNT(*) as total_reviews,
                    SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN response_text IS NOT NULL THEN 1 ELSE 0 END) as responded,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star,
                    AVG(rating) as average_rating
                  FROM reviews
                  WHERE 1=1";

        $params = [];

        if (!empty($filters['car_id'])) {
            $query .= " AND car_id = :car_id";
            $params[':car_id'] = $filters['car_id'];
        }

        if (isset($filters['is_approved'])) {
            $query .= " AND is_approved = :is_approved";
            $params[':is_approved'] = $filters['is_approved'];
        }

        return Database::queryOne($query, $params);
    }

    /**
     * التقييمات المعلقة (بانتظار الموافقة)
     */
    public static function getPending($limit = 10)
    {
        $query = "SELECT r.*, 
                         c.plate_number, c.nickname as car_name,
                         CONCAT(cb.name, ' ', cm.name) as car_full_name,
                         cu.full_name as customer_name
                  FROM reviews r
                  INNER JOIN cars c ON r.car_id = c.id
                  INNER JOIN car_brands cb ON c.brand_id = cb.id
                  INNER JOIN car_models cm ON c.model_id = cm.id
                  INNER JOIN customers cu ON r.customer_id = cu.id
                  WHERE r.is_approved = 0
                  ORDER BY r.created_at ASC
                  LIMIT :limit";

        return Database::query($query, [':limit' => $limit]);
    }

    /**
     * أحدث التقييمات المعتمدة
     */
    public static function getLatestApproved($limit = 10)
    {
        $query = "SELECT r.*, 
                         c.plate_number, c.nickname as car_name,
                         CONCAT(cb.name, ' ', cm.name) as car_full_name,
                         cu.full_name as customer_name
                  FROM reviews r
                  INNER JOIN cars c ON r.car_id = c.id
                  INNER JOIN car_brands cb ON c.brand_id = cb.id
                  INNER JOIN car_models cm ON c.model_id = cm.id
                  INNER JOIN customers cu ON r.customer_id = cu.id
                  WHERE r.is_approved = 1
                  ORDER BY r.created_at DESC
                  LIMIT :limit";

        return Database::query($query, [':limit' => $limit]);
    }

    /**
     * التحقق من إمكانية إضافة تقييم لعقد معين
     */
    public static function canReview($rentalId, $customerId)
    {
        // التحقق من حالة العقد
        $rental = Database::queryOne(
            "SELECT status FROM rentals WHERE id = :id AND customer_id = :customer_id",
            [':id' => $rentalId, ':customer_id' => $customerId]
        );

        if (!$rental || $rental['status'] !== 'completed') {
            return false;
        }

        // التحقق من عدم وجود تقييم مسبق
        $existing = Database::queryOne(
            "SELECT id FROM reviews WHERE rental_id = :rental_id AND customer_id = :customer_id",
            [':rental_id' => $rentalId, ':customer_id' => $customerId]
        );

        return !$existing;
    }

    /**
     * تقييمات بدون رد
     */
    public static function getWithoutResponse($limit = 10)
    {
        $query = "SELECT r.*, 
                         c.plate_number, c.nickname as car_name,
                         CONCAT(cb.name, ' ', cm.name) as car_full_name,
                         cu.full_name as customer_name
                  FROM reviews r
                  INNER JOIN cars c ON r.car_id = c.id
                  INNER JOIN car_brands cb ON c.brand_id = cb.id
                  INNER JOIN car_models cm ON c.model_id = cm.id
                  INNER JOIN customers cu ON r.customer_id = cu.id
                  WHERE r.is_approved = 1
                  AND r.response_text IS NULL
                  ORDER BY r.created_at ASC
                  LIMIT :limit";

        return Database::query($query, [':limit' => $limit]);
    }

    /**
     * توزيع التقييمات (نسبة كل تقييم)
     */
    public static function getRatingDistribution($carId = null)
    {
        $query = "SELECT 
                    rating,
                    COUNT(*) as count,
                    (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM reviews WHERE is_approved = 1" . 
                    ($carId ? " AND car_id = :car_id" : "") . ")) as percentage
                  FROM reviews
                  WHERE is_approved = 1";

        $params = [];

        if ($carId) {
            $query .= " AND car_id = :car_id";
            $params[':car_id'] = $carId;
        }

        $query .= " GROUP BY rating ORDER BY rating DESC";

        return Database::query($query, $params);
    }
}

// File Tracking
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 8');
