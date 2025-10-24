<?php
/**
 * File: Customer.php
 * Path: /app/models/Customer.php
 * Purpose: Customer Model - إدارة بيانات العملاء
 * Dependencies: Core/Model.php
 * Phase: Phase 6 - Customer Management
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use PDO;

class Customer extends Model
{
    protected $table = 'customers';
    protected $fillable = [
        'registration_type', 'google_id', 'email', 'password_hash',
        'full_name', 'phone', 'whatsapp', 'date_of_birth', 'nationality',
        'id_number', 'id_type', 'id_expiry_date', 'id_document_path',
        'license_number', 'license_expiry_date', 'license_document_path',
        'address', 'city', 'location_lat', 'location_lng',
        'preferred_language', 'is_verified', 'is_blacklisted',
        'blacklist_reason', 'two_factor_enabled', 'two_factor_secret'
    ];

    // ========================================
    // Authentication - المصادقة
    // ========================================

    /**
     * تسجيل دخول العميل
     */
    public function login(string $email, string $password): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE email = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$email]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer && password_verify($password, $customer['password_hash'])) {
            // تحقق من القائمة السوداء
            if ($customer['is_blacklisted']) {
                return null;
            }

            // تحديث آخر تسجيل دخول
            $this->update($customer['id'], [
                'last_login' => date('Y-m-d H:i:s')
            ]);

            return $customer;
        }

        return null;
    }

    /**
     * تسجيل عميل جديد
     */
    public function register(array $data): ?int
    {
        // تشفير كلمة المرور
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            unset($data['password']);
        }

        $data['registration_type'] = $data['registration_type'] ?? 'form';
        $data['created_at'] = date('Y-m-d H:i:s');

        return $this->create($data);
    }

    /**
     * تسجيل دخول بواسطة Google
     */
    public function loginWithGoogle(string $googleId, array $userData): array
    {
        // البحث عن العميل بـ Google ID
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE google_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$googleId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer) {
            // تحديث البيانات
            $this->update($customer['id'], [
                'last_login' => date('Y-m-d H:i:s')
            ]);
            return $customer;
        }

        // إنشاء عميل جديد
        $customerId = $this->create([
            'registration_type' => 'google',
            'google_id' => $googleId,
            'email' => $userData['email'],
            'full_name' => $userData['name'],
            'is_verified' => true,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $this->find($customerId);
    }

    /**
     * تغيير كلمة المرور
     */
    public function changePassword(int $customerId, string $oldPassword, string $newPassword): bool
    {
        $customer = $this->find($customerId);

        if (!$customer || !password_verify($oldPassword, $customer['password_hash'])) {
            return false;
        }

        return $this->update($customerId, [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12])
        ]);
    }

    /**
     * إعادة تعيين كلمة المرور
     */
    public function resetPassword(string $email): bool
    {
        $stmt = $this->db->prepare("
            SELECT id FROM {$this->table}
            WHERE email = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$email]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            return false;
        }

        // توليد كلمة مرور مؤقتة
        $tempPassword = bin2hex(random_bytes(8));
        
        $this->update($customer['id'], [
            'password_hash' => password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12])
        ]);

        // هنا يجب إرسال البريد الإلكتروني بكلمة المرور المؤقتة
        // TODO: Send email with temp password

        return true;
    }

    // ========================================
    // Customer Management - إدارة العملاء
    // ========================================

    /**
     * الحصول على جميع العملاء مع إحصائيات
     */
    public function getAllWithStats(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ["c.deleted_at IS NULL"];
        $params = [];

        // تطبيق الفلاتر
        if (!empty($filters['search'])) {
            $where[] = "(c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.id_number LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (isset($filters['is_verified'])) {
            $where[] = "c.is_verified = ?";
            $params[] = $filters['is_verified'];
        }

        if (isset($filters['is_blacklisted'])) {
            $where[] = "c.is_blacklisted = ?";
            $params[] = $filters['is_blacklisted'];
        }

        if (!empty($filters['registration_type'])) {
            $where[] = "c.registration_type = ?";
            $params[] = $filters['registration_type'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        // الحصول على البيانات
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                COUNT(DISTINCT r.id) as total_rentals,
                COALESCE(SUM(rp.amount), 0) as total_spent,
                l.name as language_name
            FROM {$this->table} c
            LEFT JOIN rentals r ON c.id = r.customer_id
            LEFT JOIN rental_payments rp ON r.id = rp.rental_id
            LEFT JOIN languages l ON c.preferred_language = l.code
            WHERE {$whereClause}
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // الحصول على العدد الإجمالي
        $countStmt = $this->db->prepare("
            SELECT COUNT(DISTINCT c.id) as total
            FROM {$this->table} c
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'data' => $customers,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    /**
     * الحصول على تفاصيل العميل الكاملة
     */
    public function getFullDetails(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                l.name as language_name,
                COUNT(DISTINCT r.id) as total_rentals,
                COUNT(DISTINCT CASE WHEN r.status = 'active' THEN r.id END) as active_rentals,
                COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) as completed_rentals,
                COALESCE(SUM(rp.amount), 0) as total_spent,
                COUNT(DISTINCT v.id) as total_violations,
                COALESCE(SUM(v.fine_amount), 0) as total_fines,
                AVG(rev.rating) as average_rating,
                COUNT(DISTINCT rev.id) as total_reviews
            FROM {$this->table} c
            LEFT JOIN languages l ON c.preferred_language = l.code
            LEFT JOIN rentals r ON c.id = r.customer_id
            LEFT JOIN rental_payments rp ON r.id = rp.rental_id
            LEFT JOIN violations v ON r.id = v.rental_id
            LEFT JOIN reviews rev ON c.id = rev.customer_id
            WHERE c.id = ? AND c.deleted_at IS NULL
            GROUP BY c.id
        ");
        $stmt->execute([$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * التحقق من المستندات
     */
    public function verifyDocuments(int $id, bool $verified, string $notes = ''): bool
    {
        $result = $this->update($id, [
            'is_verified' => $verified,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if ($result && !empty($notes)) {
            // TODO: إضافة ملاحظة في سجل التدقيق
        }

        return $result;
    }

    /**
     * إضافة/إزالة من القائمة السوداء
     */
    public function toggleBlacklist(int $id, bool $blacklisted, string $reason = ''): bool
    {
        return $this->update($id, [
            'is_blacklisted' => $blacklisted,
            'blacklist_reason' => $blacklisted ? $reason : null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * الحصول على تاريخ الإيجارات للعميل
     */
    public function getRentalHistory(int $customerId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                r.*,
                c.brand_name,
                c.model_name,
                c.year,
                c.plate_number,
                COALESCE(SUM(rp.amount), 0) as total_paid
            FROM rentals r
            JOIN cars c ON r.car_id = c.id
            LEFT JOIN rental_payments rp ON r.id = rp.rental_id
            WHERE r.customer_id = ?
            GROUP BY r.id
            ORDER BY r.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$customerId, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * الحصول على المخالفات للعميل
     */
    public function getViolations(int $customerId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                v.*,
                r.contract_number,
                c.brand_name,
                c.model_name,
                c.plate_number
            FROM violations v
            JOIN rentals r ON v.rental_id = r.id
            JOIN cars c ON r.car_id = c.id
            WHERE r.customer_id = ?
            ORDER BY v.violation_date DESC
        ");
        $stmt->execute([$customerId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * الحصول على التقييمات للعميل
     */
    public function getReviews(int $customerId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                rev.*,
                c.brand_name,
                c.model_name,
                r.contract_number
            FROM reviews rev
            JOIN rentals r ON rev.rental_id = r.id
            JOIN cars c ON rev.car_id = c.id
            WHERE rev.customer_id = ?
            ORDER BY rev.created_at DESC
        ");
        $stmt->execute([$customerId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================
    // Loyalty Points - نقاط الولاء
    // ========================================

    /**
     * الحصول على رصيد نقاط الولاء
     */
    public function getLoyaltyBalance(int $customerId): int
    {
        $customer = $this->find($customerId);
        return $customer ? (int)$customer['loyalty_points'] : 0;
    }

    /**
     * إضافة نقاط ولاء
     */
    public function addLoyaltyPoints(int $customerId, int $points, int $rentalId = null, string $description = ''): bool
    {
        // إضافة السجل في جدول loyalty_points
        $stmt = $this->db->prepare("
            INSERT INTO loyalty_points (customer_id, rental_id, points, transaction_type, description, created_at)
            VALUES (?, ?, ?, 'earned', ?, NOW())
        ");
        $stmt->execute([$customerId, $rentalId, $points, $description]);

        // تحديث الرصيد في جدول العملاء
        $this->db->prepare("
            UPDATE {$this->table}
            SET loyalty_points = loyalty_points + ?
            WHERE id = ?
        ")->execute([$points, $customerId]);

        return true;
    }

    /**
     * خصم نقاط ولاء
     */
    public function redeemLoyaltyPoints(int $customerId, int $points, string $description = ''): bool
    {
        $balance = $this->getLoyaltyBalance($customerId);
        
        if ($balance < $points) {
            return false;
        }

        // إضافة السجل
        $stmt = $this->db->prepare("
            INSERT INTO loyalty_points (customer_id, points, transaction_type, description, created_at)
            VALUES (?, ?, 'redeemed', ?, NOW())
        ");
        $stmt->execute([$customerId, -$points, $description]);

        // تحديث الرصيد
        $this->db->prepare("
            UPDATE {$this->table}
            SET loyalty_points = loyalty_points - ?
            WHERE id = ?
        ")->execute([$points, $customerId]);

        return true;
    }

    // ========================================
    // Statistics - الإحصائيات
    // ========================================

    /**
     * إحصائيات العملاء
     */
    public function getStatistics(): array
    {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_customers,
                COUNT(CASE WHEN is_verified = 1 THEN 1 END) as verified_customers,
                COUNT(CASE WHEN is_blacklisted = 1 THEN 1 END) as blacklisted_customers,
                COUNT(CASE WHEN registration_type = 'google' THEN 1 END) as google_customers,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as new_today,
                COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as new_this_week,
                COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as new_this_month
            FROM {$this->table}
            WHERE deleted_at IS NULL
        ");

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * أفضل العملاء
     */
    public function getTopCustomers(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                c.id,
                c.full_name,
                c.email,
                c.phone,
                COUNT(r.id) as total_rentals,
                COALESCE(SUM(rp.amount), 0) as total_spent
            FROM {$this->table} c
            LEFT JOIN rentals r ON c.id = r.customer_id
            LEFT JOIN rental_payments rp ON r.id = rp.rental_id
            WHERE c.deleted_at IS NULL
            GROUP BY c.id
            HAVING total_rentals > 0
            ORDER BY total_spent DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================
    // Validation - التحقق من الصحة
    // ========================================

    /**
     * التحقق من وجود البريد الإلكتروني
     */
    public function emailExists(string $email, int $excludeId = null): bool
    {
        $query = "SELECT COUNT(*) FROM {$this->table} WHERE email = ? AND deleted_at IS NULL";
        $params = [$email];

        if ($excludeId) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * التحقق من وجود رقم الهاتف
     */
    public function phoneExists(string $phone, int $excludeId = null): bool
    {
        $query = "SELECT COUNT(*) FROM {$this->table} WHERE phone = ? AND deleted_at IS NULL";
        $params = [$phone];

        if ($excludeId) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * التحقق من صحة البيانات
     */
    public function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // الاسم الكامل
        if (empty($data['full_name'])) {
            $errors['full_name'] = 'الاسم الكامل مطلوب';
        } elseif (strlen($data['full_name']) < 3) {
            $errors['full_name'] = 'الاسم يجب أن يكون 3 أحرف على الأقل';
        }

        // البريد الإلكتروني
        if (empty($data['email'])) {
            $errors['email'] = 'البريد الإلكتروني مطلوب';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'البريد الإلكتروني غير صحيح';
        } elseif ($this->emailExists($data['email'], $data['id'] ?? null)) {
            $errors['email'] = 'البريد الإلكتروني موجود مسبقاً';
        }

        // رقم الهاتف
        if (empty($data['phone'])) {
            $errors['phone'] = 'رقم الهاتف مطلوب';
        } elseif ($this->phoneExists($data['phone'], $data['id'] ?? null)) {
            $errors['phone'] = 'رقم الهاتف موجود مسبقاً';
        }

        // كلمة المرور (للتسجيل الجديد)
        if (!$isUpdate && empty($data['google_id'])) {
            if (empty($data['password'])) {
                $errors['password'] = 'كلمة المرور مطلوبة';
            } elseif (strlen($data['password']) < 8) {
                $errors['password'] = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل';
            }
        }

        return $errors;
    }

    // ========================================
    // Document Management - إدارة المستندات
    // ========================================

    /**
     * تحديث مسار مستند الهوية
     */
    public function updateIdDocument(int $id, string $path, string $type, string $expiryDate): bool
    {
        return $this->update($id, [
            'id_document_path' => $path,
            'id_type' => $type,
            'id_expiry_date' => $expiryDate
        ]);
    }

    /**
     * تحديث مسار رخصة القيادة
     */
    public function updateLicenseDocument(int $id, string $path, string $expiryDate): bool
    {
        return $this->update($id, [
            'license_document_path' => $path,
            'license_expiry_date' => $expiryDate
        ]);
    }

    /**
     * التحقق من انتهاء صلاحية المستندات
     */
    public function getExpiredDocuments(): array
    {
        $stmt = $this->db->query("
            SELECT 
                id,
                full_name,
                email,
                phone,
                id_expiry_date,
                license_expiry_date,
                CASE 
                    WHEN id_expiry_date < CURDATE() THEN 'id'
                    WHEN license_expiry_date < CURDATE() THEN 'license'
                    ELSE 'both'
                END as expired_document
            FROM {$this->table}
            WHERE deleted_at IS NULL
            AND (
                id_expiry_date < CURDATE()
                OR license_expiry_date < CURDATE()
            )
            ORDER BY 
                CASE 
                    WHEN id_expiry_date < license_expiry_date THEN id_expiry_date
                    ELSE license_expiry_date
                END ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * المستندات التي تنتهي صلاحيتها قريباً
     */
    public function getExpiringDocuments(int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                full_name,
                email,
                phone,
                id_expiry_date,
                license_expiry_date,
                DATEDIFF(
                    CASE 
                        WHEN id_expiry_date < license_expiry_date THEN id_expiry_date
                        ELSE license_expiry_date
                    END,
                    CURDATE()
                ) as days_until_expiry
            FROM {$this->table}
            WHERE deleted_at IS NULL
            AND (
                id_expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                OR license_expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            )
            ORDER BY days_until_expiry ASC
        ");
        $stmt->execute([$days, $days]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
