<?php
/**
 * File: Branch.php
 * Path: /app/models/Branch.php
 * Purpose: نموذج إدارة الفروع في النظام
 * Dependencies: Core\Model, Core\Database
 * Phase: Phase 3 - Settings & Administration
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

/**
 * Class Branch
 * 
 * نموذج إدارة الفروع
 * - إدارة معلومات الفروع (المواقع)
 * - إحداثيات GPS للموقع
 * - مناطق التغطية
 * - معلومات الاتصال
 * - التقارير والإحصائيات
 * 
 * @package App\Models
 */
class Branch extends Model
{
    /**
     * اسم الجدول في قاعدة البيانات
     */
    protected string $table = 'branches';
    
    /**
     * الحقول المسموح بها للإدخال الجماعي
     */
    protected array $fillable = [
        'name',
        'code',
        'address',
        'city',
        'country',
        'phone',
        'email',
        'whatsapp',
        'location_lat',
        'location_lng',
        'location_url',
        'coverage_area',
        'manager_name',
        'is_active'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        // تسجيل الملف في FileTracker
        FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 3');
    }

    // ========================================
    // CRUD Operations
    // ========================================

    /**
     * الحصول على جميع الفروع
     * 
     * @param bool $activeOnly الفروع النشطة فقط
     * @return array
     */
    public function getAll(bool $activeOnly = false): array
    {
        $query = "SELECT * FROM {$this->table}";
        
        if ($activeOnly) {
            $query .= " WHERE is_active = 1";
        }
        
        $query .= " ORDER BY is_active DESC, name ASC";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * الحصول على فرع حسب الكود
     * 
     * @param string $code كود الفرع
     * @return array|null
     */
    public function getByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE code = ?
        ");
        $stmt->execute([$code]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * الحصول على الفروع حسب المدينة
     * 
     * @param string $city المدينة
     * @param bool $activeOnly النشطة فقط
     * @return array
     */
    public function getByCity(string $city, bool $activeOnly = true): array
    {
        $query = "SELECT * FROM {$this->table} WHERE city = ?";
        $params = [$city];
        
        if ($activeOnly) {
            $query .= " AND is_active = 1";
        }
        
        $query .= " ORDER BY name";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * الحصول على الفروع حسب الدولة
     * 
     * @param string $country الدولة
     * @param bool $activeOnly النشطة فقط
     * @return array
     */
    public function getByCountry(string $country, bool $activeOnly = true): array
    {
        $query = "SELECT * FROM {$this->table} WHERE country = ?";
        $params = [$country];
        
        if ($activeOnly) {
            $query .= " AND is_active = 1";
        }
        
        $query .= " ORDER BY city, name";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * إنشاء فرع جديد
     * 
     * @param array $data بيانات الفرع
     * @return int|false معرف الفرع الجديد
     */
    public function create(array $data)
    {
        // توليد كود تلقائي إذا لم يتم توفيره
        if (empty($data['code'])) {
            $data['code'] = $this->generateBranchCode($data['name']);
        }
        
        // التحقق من عدم تكرار الكود
        if ($this->getByCode($data['code'])) {
            return false;
        }
        
        return parent::create($data);
    }

    /**
     * تحديث فرع
     * 
     * @param int $id معرف الفرع
     * @param array $data البيانات الجديدة
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        // التحقق من عدم تكرار الكود
        if (isset($data['code'])) {
            $existing = $this->getByCode($data['code']);
            if ($existing && $existing['id'] != $id) {
                return false;
            }
        }
        
        return parent::update($id, $data);
    }

    // ========================================
    // Branch Management - إدارة الفروع
    // ========================================

    /**
     * تفعيل فرع
     * 
     * @param int $id معرف الفرع
     * @return bool
     */
    public function activate(int $id): bool
    {
        return $this->update($id, ['is_active' => 1]);
    }

    /**
     * تعطيل فرع
     * 
     * @param int $id معرف الفرع
     * @return bool
     */
    public function deactivate(int $id): bool
    {
        // التحقق من عدم وجود سيارات أو موظفين نشطين في الفرع
        if ($this->hasActiveResources($id)) {
            error_log("Cannot deactivate branch with active resources");
            return false;
        }
        
        return $this->update($id, ['is_active' => 0]);
    }

    /**
     * التحقق من وجود موارد نشطة في الفرع
     * 
     * @param int $branchId معرف الفرع
     * @return bool
     */
    private function hasActiveResources(int $branchId): bool
    {
        // التحقق من السيارات
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM cars 
            WHERE branch_id = ? AND status IN ('available', 'rented')
        ");
        $stmt->execute([$branchId]);
        
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        
        // التحقق من الموظفين
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM users 
            WHERE branch_id = ? AND is_active = 1
        ");
        $stmt->execute([$branchId]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * الحصول على الفروع النشطة
     * 
     * @return array
     */
    public function getActive(): array
    {
        return $this->getAll(true);
    }

    /**
     * الحصول على عدد الفروع النشطة
     * 
     * @return int
     */
    public function countActive(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM {$this->table} 
            WHERE is_active = 1
        ");
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * التحقق من وجود فرع بكود معين
     * 
     * @param string $code كود الفرع
     * @return bool
     */
    public function codeExists(string $code): bool
    {
        return $this->getByCode($code) !== null;
    }

    // ========================================
    // Helper Methods - الطرق المساعدة
    // ========================================

    /**
     * توليد كود فرع تلقائياً
     * 
     * @param string $name اسم الفرع
     * @return string
     */
    private function generateBranchCode(string $name): string
    {
        // استخراج الحروف الأولى
        $words = explode(' ', $name);
        $code = '';
        
        foreach ($words as $word) {
            if (!empty($word)) {
                $code .= strtoupper(substr($word, 0, 1));
            }
        }
        
        // إذا كان الكود قصيراً جداً، استخدم أول 3 حروف من الاسم
        if (strlen($code) < 2) {
            $code = strtoupper(substr($name, 0, 3));
        }
        
        // التحقق من عدم التكرار وإضافة رقم إذا لزم الأمر
        $originalCode = $code;
        $counter = 1;
        
        while ($this->codeExists($code)) {
            $code = $originalCode . $counter;
            $counter++;
        }
        
        return $code;
    }

    /**
     * الحصول على الفروع كخيارات للقوائم المنسدلة
     * 
     * @param bool $activeOnly النشطة فقط
     * @return array [id => name]
     */
    public function getAsOptions(bool $activeOnly = true): array
    {
        $branches = $this->getAll($activeOnly);
        $options = [];
        
        foreach ($branches as $branch) {
            $options[$branch['id']] = $branch['name'];
        }
        
        return $options;
    }

    /**
     * الحصول على جميع المدن
     * 
     * @return array
     */
    public function getCities(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT city 
            FROM {$this->table} 
            WHERE city IS NOT NULL AND city != ''
            ORDER BY city
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * الحصول على جميع الدول
     * 
     * @return array
     */
    public function getCountries(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT country 
            FROM {$this->table} 
            WHERE country IS NOT NULL AND country != ''
            ORDER BY country
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    // ========================================
    // Location Methods - طرق الموقع
    // ========================================

    /**
     * الحصول على الفروع القريبة من موقع معين
     * 
     * @param float $lat خط العرض
     * @param float $lng خط الطول
     * @param float $radius نصف القطر بالكيلومتر
     * @param bool $activeOnly النشطة فقط
     * @return array
     */
    public function getNearby(float $lat, float $lng, float $radius = 50, bool $activeOnly = true): array
    {
        // استخدام صيغة Haversine لحساب المسافة
        $query = "
            SELECT *,
                (6371 * acos(cos(radians(?)) 
                * cos(radians(location_lat)) 
                * cos(radians(location_lng) - radians(?)) 
                + sin(radians(?)) 
                * sin(radians(location_lat)))) AS distance
            FROM {$this->table}
            WHERE location_lat IS NOT NULL 
                AND location_lng IS NOT NULL
        ";
        
        $params = [$lat, $lng, $lat];
        
        if ($activeOnly) {
            $query .= " AND is_active = 1";
        }
        
        $query .= " HAVING distance < ? ORDER BY distance";
        $params[] = $radius;
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * تحديث الموقع الجغرافي
     * 
     * @param int $id معرف الفرع
     * @param float $lat خط العرض
     * @param float $lng خط الطول
     * @param string|null $url رابط الموقع
     * @return bool
     */
    public function updateLocation(int $id, float $lat, float $lng, ?string $url = null): bool
    {
        $data = [
            'location_lat' => $lat,
            'location_lng' => $lng
        ];
        
        if ($url !== null) {
            $data['location_url'] = $url;
        }
        
        return $this->update($id, $data);
    }

    /**
     * التحقق من أن الفرع لديه موقع جغرافي
     * 
     * @param int $id معرف الفرع
     * @return bool
     */
    public function hasLocation(int $id): bool
    {
        $branch = $this->findById($id);
        
        if (!$branch) {
            return false;
        }
        
        return !empty($branch['location_lat']) && !empty($branch['location_lng']);
    }

    // ========================================
    // Statistics & Reports - الإحصائيات والتقارير
    // ========================================

    /**
     * الحصول على إحصائيات الفرع
     * 
     * @param int $branchId معرف الفرع
     * @return array
     */
    public function getStatistics(int $branchId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM cars WHERE branch_id = ?) as total_cars,
                (SELECT COUNT(*) FROM cars WHERE branch_id = ? AND status = 'available') as available_cars,
                (SELECT COUNT(*) FROM cars WHERE branch_id = ? AND status = 'rented') as rented_cars,
                (SELECT COUNT(*) FROM users WHERE branch_id = ? AND is_active = 1) as total_employees,
                (SELECT COUNT(*) FROM rentals WHERE pickup_branch_id = ?) as total_rentals
        ");
        $stmt->execute([$branchId, $branchId, $branchId, $branchId, $branchId]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * الحصول على إحصائيات جميع الفروع
     * 
     * @return array
     */
    public function getAllStatistics(): array
    {
        $stmt = $this->db->query("
            SELECT 
                b.*,
                COUNT(DISTINCT c.id) as total_cars,
                COUNT(DISTINCT CASE WHEN c.status = 'available' THEN c.id END) as available_cars,
                COUNT(DISTINCT CASE WHEN c.status = 'rented' THEN c.id END) as rented_cars,
                COUNT(DISTINCT u.id) as total_employees
            FROM {$this->table} b
            LEFT JOIN cars c ON b.id = c.branch_id
            LEFT JOIN users u ON b.id = u.branch_id AND u.is_active = 1
            GROUP BY b.id
            ORDER BY b.is_active DESC, b.name
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * الحصول على أداء الفروع (حسب الإيرادات)
     * 
     * @param string $startDate تاريخ البداية
     * @param string $endDate تاريخ النهاية
     * @return array
     */
    public function getPerformance(string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                b.id,
                b.name,
                b.code,
                COUNT(r.id) as total_rentals,
                SUM(r.total_amount) as total_revenue,
                AVG(r.total_amount) as avg_rental_amount,
                SUM(rp.amount_paid) as total_paid
            FROM {$this->table} b
            LEFT JOIN rentals r ON b.id = r.pickup_branch_id
                AND r.start_date BETWEEN ? AND ?
            LEFT JOIN rental_payments rp ON r.id = rp.rental_id
            WHERE b.is_active = 1
            GROUP BY b.id
            ORDER BY total_revenue DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ========================================
    // Validation - التحقق من الصحة
    // ========================================

    /**
     * التحقق من صحة بيانات الفرع
     * 
     * @param array $data البيانات
     * @param bool $isUpdate هل هو تحديث؟
     * @return array أخطاء التحقق
     */
    public function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];
        
        // التحقق من الاسم
        if (empty($data['name'])) {
            $errors['name'] = 'اسم الفرع مطلوب';
        }
        
        // التحقق من الكود
        if (!$isUpdate && isset($data['code'])) {
            if (empty($data['code'])) {
                $errors['code'] = 'كود الفرع مطلوب';
            } elseif ($this->codeExists($data['code'])) {
                $errors['code'] = 'كود الفرع موجود مسبقاً';
            }
        }
        
        // التحقق من البريد الإلكتروني
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'البريد الإلكتروني غير صحيح';
        }
        
        // التحقق من الإحداثيات
        if (!empty($data['location_lat'])) {
            $lat = floatval($data['location_lat']);
            if ($lat < -90 || $lat > 90) {
                $errors['location_lat'] = 'خط العرض يجب أن يكون بين -90 و 90';
            }
        }
        
        if (!empty($data['location_lng'])) {
            $lng = floatval($data['location_lng']);
            if ($lng < -180 || $lng > 180) {
                $errors['location_lng'] = 'خط الطول يجب أن يكون بين -180 و 180';
            }
        }
        
        return $errors;
    }

    // ========================================
    // Search & Filter - البحث والتصفية
    // ========================================

    /**
     * البحث في الفروع
     * 
     * @param string $search نص البحث
     * @param bool $activeOnly النشطة فقط
     * @return array
     */
    public function search(string $search, bool $activeOnly = false): array
    {
        $query = "
            SELECT * FROM {$this->table}
            WHERE (
                name LIKE ? OR
                code LIKE ? OR
                city LIKE ? OR
                address LIKE ? OR
                manager_name LIKE ?
            )
        ";
        
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
        
        if ($activeOnly) {
            $query .= " AND is_active = 1";
        }
        
        $query .= " ORDER BY name LIMIT 50";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * تصفية الفروع
     * 
     * @param array $filters مرشحات البحث
     * @return array
     */
    public function filter(array $filters): array
    {
        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['city'])) {
            $query .= " AND city = ?";
            $params[] = $filters['city'];
        }
        
        if (!empty($filters['country'])) {
            $query .= " AND country = ?";
            $params[] = $filters['country'];
        }
        
        if (isset($filters['is_active'])) {
            $query .= " AND is_active = ?";
            $params[] = $filters['is_active'] ? 1 : 0;
        }
        
        if (!empty($filters['has_location'])) {
            $query .= " AND location_lat IS NOT NULL AND location_lng IS NOT NULL";
        }
        
        $query .= " ORDER BY name";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
