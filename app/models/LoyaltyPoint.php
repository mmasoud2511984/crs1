<?php
/**
 * File: LoyaltyPoint.php
 * Path: /app/models/LoyaltyPoint.php
 * Purpose: Loyalty Points Model - إدارة نقاط الولاء
 * Dependencies: Core/Model.php
 * Phase: Phase 6 - Customer Management
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use PDO;

class LoyaltyPoint extends Model
{
    protected $table = 'loyalty_points';
    protected $fillable = [
        'customer_id', 'rental_id', 'points', 'transaction_type',
        'description', 'expiry_date'
    ];

    // أنواع المعاملات
    const TYPE_EARNED = 'earned';
    const TYPE_REDEEMED = 'redeemed';
    const TYPE_EXPIRED = 'expired';
    const TYPE_ADJUSTED = 'adjusted';

    // ========================================
    // Create Points - إنشاء النقاط
    // ========================================

    /**
     * إضافة نقاط مكتسبة
     */
    public function earnPoints(int $customerId, int $points, int $rentalId = null, string $description = '', int $expiryDays = 365): int
    {
        $expiryDate = date('Y-m-d', strtotime("+{$expiryDays} days"));

        $id = $this->create([
            'customer_id' => $customerId,
            'rental_id' => $rentalId,
            'points' => $points,
            'transaction_type' => self::TYPE_EARNED,
            'description' => $description,
            'expiry_date' => $expiryDate,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // تحديث رصيد العميل
        $this->updateCustomerBalance($customerId);

        return $id;
    }

    /**
     * خصم نقاط
     */
    public function redeemPoints(int $customerId, int $points, string $description = ''): bool
    {
        // التحقق من الرصيد
        $balance = $this->getCustomerBalance($customerId);
        if ($balance < $points) {
            return false;
        }

        $this->create([
            'customer_id' => $customerId,
            'points' => -$points,
            'transaction_type' => self::TYPE_REDEEMED,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // تحديث رصيد العميل
        $this->updateCustomerBalance($customerId);

        return true;
    }

    /**
     * تعديل النقاط يدوياً
     */
    public function adjustPoints(int $customerId, int $points, string $description = ''): int
    {
        $id = $this->create([
            'customer_id' => $customerId,
            'points' => $points,
            'transaction_type' => self::TYPE_ADJUSTED,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // تحديث رصيد العميل
        $this->updateCustomerBalance($customerId);

        return $id;
    }

    /**
     * انتهاء صلاحية النقاط
     */
    public function expirePoints(int $id): bool
    {
        return $this->update($id, [
            'transaction_type' => self::TYPE_EXPIRED
        ]);
    }

    // ========================================
    // Retrieve Points - استرجاع النقاط
    // ========================================

    /**
     * الحصول على رصيد العميل
     */
    public function getCustomerBalance(int $customerId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(points), 0) as balance
            FROM {$this->table}
            WHERE customer_id = ?
            AND (expiry_date IS NULL OR expiry_date >= CURDATE())
            AND transaction_type != ?
        ");
        $stmt->execute([$customerId, self::TYPE_EXPIRED]);

        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['balance'];
    }

    /**
     * الحصول على تاريخ النقاط للعميل
     */
    public function getCustomerHistory(int $customerId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                lp.*,
                r.contract_number,
                c.brand_name,
                c.model_name
            FROM {$this->table} lp
            LEFT JOIN rentals r ON lp.rental_id = r.id
            LEFT JOIN cars c ON r.car_id = c.id
            WHERE lp.customer_id = ?
            ORDER BY lp.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$customerId, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * النقاط المكتسبة
     */
    public function getEarnedPoints(int $customerId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(points), 0) as total
            FROM {$this->table}
            WHERE customer_id = ?
            AND transaction_type = ?
        ");
        $stmt->execute([$customerId, self::TYPE_EARNED]);

        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * النقاط المستخدمة
     */
    public function getRedeemedPoints(int $customerId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(ABS(SUM(points)), 0) as total
            FROM {$this->table}
            WHERE customer_id = ?
            AND transaction_type = ?
        ");
        $stmt->execute([$customerId, self::TYPE_REDEEMED]);

        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * النقاط المنتهية
     */
    public function getExpiredPoints(int $customerId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(points), 0) as total
            FROM {$this->table}
            WHERE customer_id = ?
            AND transaction_type = ?
        ");
        $stmt->execute([$customerId, self::TYPE_EXPIRED]);

        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * النقاط التي ستنتهي قريباً
     */
    public function getExpiringPoints(int $customerId, int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                *,
                DATEDIFF(expiry_date, CURDATE()) as days_until_expiry
            FROM {$this->table}
            WHERE customer_id = ?
            AND transaction_type = ?
            AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY expiry_date ASC
        ");
        $stmt->execute([$customerId, self::TYPE_EARNED, $days]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================
    // Statistics - الإحصائيات
    // ========================================

    /**
     * إحصائيات عامة
     */
    public function getGeneralStatistics(): array
    {
        $stmt = $this->db->query("
            SELECT 
                COUNT(DISTINCT customer_id) as total_customers_with_points,
                COALESCE(SUM(CASE WHEN points > 0 THEN points END), 0) as total_points_earned,
                COALESCE(ABS(SUM(CASE WHEN points < 0 THEN points END)), 0) as total_points_redeemed,
                COALESCE(SUM(CASE WHEN transaction_type = 'expired' THEN points END), 0) as total_points_expired,
                COUNT(CASE WHEN transaction_type = 'earned' THEN 1 END) as total_earn_transactions,
                COUNT(CASE WHEN transaction_type = 'redeemed' THEN 1 END) as total_redeem_transactions
            FROM {$this->table}
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * العملاء الأكثر نقاطاً
     */
    public function getTopCustomers(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                c.id,
                c.full_name,
                c.email,
                c.phone,
                c.loyalty_points as current_balance,
                COALESCE(SUM(CASE WHEN lp.transaction_type = 'earned' THEN lp.points END), 0) as total_earned,
                COALESCE(ABS(SUM(CASE WHEN lp.transaction_type = 'redeemed' THEN lp.points END)), 0) as total_redeemed
            FROM customers c
            LEFT JOIN {$this->table} lp ON c.id = lp.customer_id
            WHERE c.deleted_at IS NULL
            GROUP BY c.id
            HAVING current_balance > 0
            ORDER BY current_balance DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * إحصائيات شهرية
     */
    public function getMonthlyStatistics(int $months = 6): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COALESCE(SUM(CASE WHEN transaction_type = 'earned' THEN points END), 0) as earned,
                COALESCE(ABS(SUM(CASE WHEN transaction_type = 'redeemed' THEN points END)), 0) as redeemed,
                COUNT(DISTINCT customer_id) as unique_customers
            FROM {$this->table}
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY month
            ORDER BY month DESC
        ");
        $stmt->execute([$months]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================
    // Maintenance - الصيانة
    // ========================================

    /**
     * انتهاء صلاحية النقاط القديمة (Cron Job)
     */
    public function expireOldPoints(): int
    {
        // الحصول على النقاط المنتهية
        $stmt = $this->db->query("
            SELECT id, customer_id, points
            FROM {$this->table}
            WHERE transaction_type = 'earned'
            AND expiry_date < CURDATE()
            AND expiry_date IS NOT NULL
        ");
        $expiredPoints = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($expiredPoints as $point) {
            // تحديث نوع المعاملة
            $this->update($point['id'], [
                'transaction_type' => self::TYPE_EXPIRED
            ]);

            // تحديث رصيد العميل
            $this->updateCustomerBalance($point['customer_id']);

            $count++;
        }

        return $count;
    }

    /**
     * تحديث رصيد العميل
     */
    private function updateCustomerBalance(int $customerId): void
    {
        $balance = $this->getCustomerBalance($customerId);

        $stmt = $this->db->prepare("
            UPDATE customers
            SET loyalty_points = ?
            WHERE id = ?
        ");
        $stmt->execute([$balance, $customerId]);
    }

    /**
     * حساب النقاط بناءً على المبلغ
     * 
     * @param float $amount المبلغ
     * @param string $pointsRate نسبة النقاط (مثال: "1:100" يعني 1 نقطة لكل 100 ريال)
     * @return int
     */
    public static function calculatePoints(float $amount, string $pointsRate = '1:100'): int
    {
        list($points, $currency) = explode(':', $pointsRate);
        
        return (int)floor(($amount / (float)$currency) * (float)$points);
    }

    /**
     * حساب الخصم من النقاط
     * 
     * @param int $points عدد النقاط
     * @param string $pointsValue قيمة النقطة (مثال: "1:10" يعني كل نقطة = 10 ريال)
     * @return float
     */
    public static function calculateDiscount(int $points, string $pointsValue = '1:10'): float
    {
        list($pointUnit, $currencyValue) = explode(':', $pointsValue);
        
        return ($points / (float)$pointUnit) * (float)$currencyValue;
    }

    // ========================================
    // Reports - التقارير
    // ========================================

    /**
     * تقرير النقاط حسب الفترة
     */
    public function getPointsReport(string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                transaction_type,
                COUNT(*) as transactions_count,
                COALESCE(SUM(points), 0) as total_points,
                COUNT(DISTINCT customer_id) as unique_customers
            FROM {$this->table}
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY date, transaction_type
            ORDER BY date DESC, transaction_type
        ");
        $stmt->execute([$startDate, $endDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * تقرير العملاء النشطين في نقاط الولاء
     */
    public function getActiveCustomersReport(int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                c.id,
                c.full_name,
                c.email,
                c.loyalty_points as current_balance,
                COUNT(lp.id) as transactions_count,
                COALESCE(SUM(CASE WHEN lp.points > 0 THEN lp.points END), 0) as earned,
                COALESCE(ABS(SUM(CASE WHEN lp.points < 0 THEN lp.points END)), 0) as redeemed,
                MAX(lp.created_at) as last_transaction
            FROM customers c
            INNER JOIN {$this->table} lp ON c.id = lp.customer_id
            WHERE lp.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            AND c.deleted_at IS NULL
            GROUP BY c.id
            ORDER BY transactions_count DESC
        ");
        $stmt->execute([$days]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================
    // Validation - التحقق
    // ========================================

    /**
     * التحقق من صحة البيانات
     */
    public function validate(array $data): array
    {
        $errors = [];

        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'معرف العميل مطلوب';
        }

        if (empty($data['points'])) {
            $errors['points'] = 'عدد النقاط مطلوب';
        } elseif (!is_numeric($data['points'])) {
            $errors['points'] = 'عدد النقاط يجب أن يكون رقماً';
        }

        if (empty($data['transaction_type'])) {
            $errors['transaction_type'] = 'نوع المعاملة مطلوب';
        } elseif (!in_array($data['transaction_type'], [
            self::TYPE_EARNED,
            self::TYPE_REDEEMED,
            self::TYPE_EXPIRED,
            self::TYPE_ADJUSTED
        ])) {
            $errors['transaction_type'] = 'نوع المعاملة غير صحيح';
        }

        // التحقق من رصيد كافي للخصم
        if ($data['transaction_type'] === self::TYPE_REDEEMED) {
            $balance = $this->getCustomerBalance($data['customer_id']);
            if ($balance < abs($data['points'])) {
                $errors['points'] = 'الرصيد غير كافٍ';
            }
        }

        return $errors;
    }
}
