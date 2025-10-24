<?php
/**
 * File: RentalPayment.php
 * Path: /app/models/RentalPayment.php
 * Purpose: Rental payment model - manages all payments related to rentals
 * Dependencies: Model.php, Rental.php
 * Phase: Phase 7 - Rental System
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

class RentalPayment extends Model
{
    protected string $table = 'rental_payments';
    protected array $fillable = [
        'rental_id', 'payment_method_id', 'amount', 'payment_type',
        'payment_date', 'reference_number', 'receipt_path', 'notes', 'created_by'
    ];

    /**
     * الحصول على جميع دفعات إيجار محدد
     * 
     * @param int $rentalId معرف الإيجار
     * @return array الدفعات
     */
    public function getByRentalId(int $rentalId): array
    {
        $sql = "SELECT rp.*, 
                pm.name as payment_method_name,
                u.full_name as created_by_name
                FROM {$this->table} rp
                LEFT JOIN payment_methods pm ON rp.payment_method_id = pm.id
                LEFT JOIN users u ON rp.created_by = u.id
                WHERE rp.rental_id = :rental_id
                ORDER BY rp.payment_date DESC";
        
        return Database::query($sql, ['rental_id' => $rentalId]);
    }

    /**
     * إضافة دفعة جديدة
     * 
     * @param array $data بيانات الدفعة
     * @return int|null معرف الدفعة
     */
    public function addPayment(array $data): ?int
    {
        try {
            Database::beginTransaction();
            
            // إنشاء الدفعة
            $paymentId = $this->create($data);
            
            if (!$paymentId) {
                throw new \Exception('Failed to create payment');
            }
            
            // تحديث مبالغ الإيجار
            $this->updateRentalAmounts($data['rental_id']);
            
            Database::commit();
            return $paymentId;
            
        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Add payment error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * تحديث مبالغ الإيجار بناءً على الدفعات
     * 
     * @param int $rentalId معرف الإيجار
     * @return bool نجاح العملية
     */
    private function updateRentalAmounts(int $rentalId): bool
    {
        // حساب إجمالي المدفوع (دفعات الإيجار فقط، بدون العربون والغرامات)
        $sql = "SELECT 
                SUM(CASE WHEN payment_type = 'rental' THEN amount ELSE 0 END) as total_rental_paid,
                SUM(CASE WHEN payment_type = 'deposit' THEN amount ELSE 0 END) as total_deposit
                FROM {$this->table}
                WHERE rental_id = :rental_id";
        
        $result = Database::query($sql, ['rental_id' => $rentalId]);
        $totals = $result[0] ?? [];
        
        $rentalPaid = floatval($totals['total_rental_paid'] ?? 0);
        
        // الحصول على الإيجار
        $rental = Database::query(
            "SELECT total_amount FROM rentals WHERE id = :id",
            ['id' => $rentalId]
        )[0] ?? null;
        
        if (!$rental) {
            return false;
        }
        
        $totalAmount = floatval($rental['total_amount']);
        $remainingAmount = $totalAmount - $rentalPaid;
        
        // تحديد حالة الدفع
        if ($rentalPaid <= 0) {
            $paymentStatus = 'pending';
        } elseif ($rentalPaid >= $totalAmount) {
            $paymentStatus = 'paid';
        } else {
            $paymentStatus = 'partial';
        }
        
        // تحديث الإيجار
        return Database::query(
            "UPDATE rentals SET 
             paid_amount = :paid_amount,
             remaining_amount = :remaining_amount,
             payment_status = :payment_status
             WHERE id = :id",
            [
                'paid_amount' => $rentalPaid,
                'remaining_amount' => $remainingAmount,
                'payment_status' => $paymentStatus,
                'id' => $rentalId
            ]
        );
    }

    /**
     * حذف دفعة
     * 
     * @param int $id معرف الدفعة
     * @return bool نجاح العملية
     */
    public function deletePayment(int $id): bool
    {
        try {
            Database::beginTransaction();
            
            // الحصول على معرف الإيجار قبل الحذف
            $payment = $this->find($id);
            if (!$payment) {
                throw new \Exception('Payment not found');
            }
            
            // حذف الدفعة
            $deleted = $this->delete($id);
            
            if (!$deleted) {
                throw new \Exception('Failed to delete payment');
            }
            
            // تحديث مبالغ الإيجار
            $this->updateRentalAmounts($payment['rental_id']);
            
            Database::commit();
            return true;
            
        } catch (\Exception $e) {
            Database::rollBack();
            error_log("Delete payment error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على إجمالي الدفعات حسب النوع
     * 
     * @param int $rentalId معرف الإيجار
     * @param string $type نوع الدفعة
     * @return float المبلغ
     */
    public function getTotalByType(int $rentalId, string $type): float
    {
        $sql = "SELECT SUM(amount) as total 
                FROM {$this->table}
                WHERE rental_id = :rental_id 
                AND payment_type = :type";
        
        $result = Database::query($sql, [
            'rental_id' => $rentalId,
            'type' => $type
        ]);
        
        return floatval($result[0]['total'] ?? 0);
    }

    /**
     * الحصول على جميع الدفعات مع الفلاتر
     * 
     * @param array $filters الفلاتر
     * @param int $page رقم الصفحة
     * @param int $perPage عدد السجلات في الصفحة
     * @return array النتائج
     */
    public function getAllWithDetails(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT rp.*,
                r.rental_number,
                c.name as customer_name,
                pm.name as payment_method_name,
                u.full_name as created_by_name
                FROM {$this->table} rp
                INNER JOIN rentals r ON rp.rental_id = r.id
                INNER JOIN customers c ON r.customer_id = c.id
                LEFT JOIN payment_methods pm ON rp.payment_method_id = pm.id
                LEFT JOIN users u ON rp.created_by = u.id
                WHERE 1=1";
        
        $params = [];
        
        // تطبيق الفلاتر
        if (!empty($filters['rental_id'])) {
            $sql .= " AND rp.rental_id = :rental_id";
            $params['rental_id'] = $filters['rental_id'];
        }
        
        if (!empty($filters['payment_type'])) {
            $sql .= " AND rp.payment_type = :payment_type";
            $params['payment_type'] = $filters['payment_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(rp.payment_date) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(rp.payment_date) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        // إجمالي العدد
        $countSql = "SELECT COUNT(*) as total FROM ({$sql}) as count_table";
        $total = Database::query($countSql, $params)[0]['total'] ?? 0;
        
        // ترتيب وصفحات
        $sql .= " ORDER BY rp.payment_date DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $perPage;
        $params['offset'] = $offset;
        
        $payments = Database::query($sql, $params);
        
        return [
            'data' => $payments,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    /**
     * الحصول على إحصائيات الدفعات
     * 
     * @param array $filters الفلاتر
     * @return array الإحصائيات
     */
    public function getPaymentStats(array $filters = []): array
    {
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $where .= " AND DATE(payment_date) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where .= " AND DATE(payment_date) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $sql = "SELECT 
                COUNT(*) as total_payments,
                SUM(amount) as total_amount,
                SUM(CASE WHEN payment_type = 'rental' THEN amount ELSE 0 END) as rental_payments,
                SUM(CASE WHEN payment_type = 'deposit' THEN amount ELSE 0 END) as deposit_payments,
                SUM(CASE WHEN payment_type = 'fine' THEN amount ELSE 0 END) as fine_payments,
                SUM(CASE WHEN payment_type = 'refund' THEN amount ELSE 0 END) as refund_payments
                FROM {$this->table} {$where}";
        
        $result = Database::query($sql, $params);
        return $result[0] ?? [];
    }
}

// تسجيل الملف
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 7');
