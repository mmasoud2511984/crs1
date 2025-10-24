<?php
/**
 * File: CarImage.php
 * Path: /app/models/CarImage.php
 * Purpose: نموذج صور السيارات - إدارة الصور المتعددة لكل سيارة
 * Dependencies: Model.php, Database.php, FileTracker.php
 * Phase: Phase 4 - Car Management
 * Created: 2025-10-24
 */

namespace App\Models;

use Core\Model;
use Core\Database;
use Core\FileTracker;

/**
 * Class CarImage
 * يدير الصور المتعددة للسيارات مع ترتيب ونوع الصورة
 * 
 * @package App\Models
 */
class CarImage extends Model
{
    protected string $table = 'car_images';
    protected array $fillable = [
        'car_id',
        'image_path',
        'image_type',
        'is_primary',
        'display_order'
    ];

    // أنواع الصور
    const TYPE_EXTERIOR = 'exterior';
    const TYPE_INTERIOR = 'interior';
    const TYPE_OTHER = 'other';

    /**
     * الحصول على جميع صور سيارة
     * 
     * @param int $carId معرف السيارة
     * @return array
     */
    public function getCarImages(int $carId): array
    {
        try {
            $query = "SELECT * FROM {$this->table} 
                     WHERE car_id = ? 
                     ORDER BY is_primary DESC, display_order ASC, id ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$carId]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getCarImages: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على الصورة الرئيسية لسيارة
     * 
     * @param int $carId معرف السيارة
     * @return array|null
     */
    public function getPrimaryImage(int $carId): ?array
    {
        try {
            $query = "SELECT * FROM {$this->table} 
                     WHERE car_id = ? AND is_primary = 1 
                     LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$carId]);
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\PDOException $e) {
            error_log("خطأ في getPrimaryImage: " . $e->getMessage());
            return null;
        }
    }

    /**
     * الحصول على صور حسب النوع
     * 
     * @param int $carId معرف السيارة
     * @param string $type نوع الصورة
     * @return array
     */
    public function getImagesByType(int $carId, string $type): array
    {
        try {
            $query = "SELECT * FROM {$this->table} 
                     WHERE car_id = ? AND image_type = ? 
                     ORDER BY display_order ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$carId, $type]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getImagesByType: " . $e->getMessage());
            return [];
        }
    }

    /**
     * إضافة صورة جديدة
     * 
     * @param array $data بيانات الصورة
     * @return int|false معرف الصورة أو false
     */
    public function create(array $data): int|false
    {
        try {
            Database::beginTransaction();

            // إذا كانت صورة رئيسية، إلغاء الرئيسية القديمة
            if (!empty($data['is_primary']) && $data['is_primary']) {
                $this->removePrimaryStatus($data['car_id']);
            }

            // تعيين ترتيب العرض إذا لم يكن محددًا
            if (!isset($data['display_order'])) {
                $data['display_order'] = $this->getNextDisplayOrder($data['car_id']);
            }

            $query = "INSERT INTO {$this->table} 
                     (car_id, image_path, image_type, is_primary, display_order) 
                     VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['car_id'],
                $data['image_path'],
                $data['image_type'] ?? self::TYPE_EXTERIOR,
                $data['is_primary'] ?? 0,
                $data['display_order']
            ]);

            $imageId = (int) $this->db->lastInsertId();

            // تسجيل في audit log
            $this->logAudit('create', 'car_images', $imageId, null, $data);

            Database::commit();
            
            return $imageId;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في create image: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث صورة
     * 
     * @param int $id معرف الصورة
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

            // إذا تم تغييرها لتصبح رئيسية
            if (!empty($data['is_primary']) && $data['is_primary'] && !$oldData['is_primary']) {
                $this->removePrimaryStatus($oldData['car_id']);
            }

            $query = "UPDATE {$this->table} 
                     SET image_path = ?,
                         image_type = ?,
                         is_primary = ?,
                         display_order = ?
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $data['image_path'] ?? $oldData['image_path'],
                $data['image_type'] ?? $oldData['image_type'],
                $data['is_primary'] ?? $oldData['is_primary'],
                $data['display_order'] ?? $oldData['display_order'],
                $id
            ]);

            // تسجيل في audit log
            $this->logAudit('update', 'car_images', $id, $oldData, $data);

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في update image: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف صورة
     * 
     * @param int $id معرف الصورة
     * @param bool $deleteFile حذف الملف من النظام
     * @return bool
     */
    public function delete(int $id, bool $deleteFile = true): bool
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

            // حذف الملف من النظام
            if ($deleteFile && $result && file_exists($oldData['image_path'])) {
                @unlink($oldData['image_path']);
            }

            // إذا كانت الصورة رئيسية، تعيين أول صورة كرئيسية
            if ($oldData['is_primary']) {
                $this->setFirstImageAsPrimary($oldData['car_id']);
            }

            // تسجيل في audit log
            $this->logAudit('delete', 'car_images', $id, $oldData, null);

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في delete image: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تعيين صورة كرئيسية
     * 
     * @param int $imageId معرف الصورة
     * @return bool
     */
    public function setPrimary(int $imageId): bool
    {
        try {
            $image = $this->find($imageId);
            if (!$image) {
                return false;
            }

            Database::beginTransaction();

            // إلغاء الحالة الرئيسية من الصور الأخرى
            $this->removePrimaryStatus($image['car_id']);

            // تعيين الصورة الحالية كرئيسية
            $query = "UPDATE {$this->table} 
                     SET is_primary = 1 
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$imageId]);

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في setPrimary: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إلغاء حالة الصورة الرئيسية لجميع صور السيارة
     * 
     * @param int $carId معرف السيارة
     * @return bool
     */
    private function removePrimaryStatus(int $carId): bool
    {
        try {
            $query = "UPDATE {$this->table} 
                     SET is_primary = 0 
                     WHERE car_id = ?";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$carId]);
        } catch (\PDOException $e) {
            error_log("خطأ في removePrimaryStatus: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تعيين أول صورة كرئيسية
     * 
     * @param int $carId معرف السيارة
     * @return bool
     */
    private function setFirstImageAsPrimary(int $carId): bool
    {
        try {
            $query = "UPDATE {$this->table} 
                     SET is_primary = 1 
                     WHERE car_id = ? 
                     ORDER BY display_order ASC, id ASC 
                     LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$carId]);
        } catch (\PDOException $e) {
            error_log("خطأ في setFirstImageAsPrimary: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إعادة ترتيب الصور
     * 
     * @param int $carId معرف السيارة
     * @param array $order مصفوفة [image_id => display_order]
     * @return bool
     */
    public function reorder(int $carId, array $order): bool
    {
        try {
            Database::beginTransaction();

            $query = "UPDATE {$this->table} 
                     SET display_order = ? 
                     WHERE id = ? AND car_id = ?";
            
            $stmt = $this->db->prepare($query);

            foreach ($order as $imageId => $displayOrder) {
                $stmt->execute([$displayOrder, $imageId, $carId]);
            }

            Database::commit();
            return true;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في reorder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على الترتيب التالي
     * 
     * @param int $carId معرف السيارة
     * @return int
     */
    private function getNextDisplayOrder(int $carId): int
    {
        try {
            $query = "SELECT MAX(display_order) FROM {$this->table} WHERE car_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$carId]);
            
            $max = $stmt->fetchColumn();
            return ($max ?? 0) + 1;
        } catch (\PDOException $e) {
            error_log("خطأ في getNextDisplayOrder: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * حذف جميع صور سيارة
     * 
     * @param int $carId معرف السيارة
     * @param bool $deleteFiles حذف الملفات من النظام
     * @return bool
     */
    public function deleteAllCarImages(int $carId, bool $deleteFiles = true): bool
    {
        try {
            // الحصول على جميع الصور
            $images = $this->getCarImages($carId);

            Database::beginTransaction();

            $query = "DELETE FROM {$this->table} WHERE car_id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$carId]);

            // حذف الملفات من النظام
            if ($deleteFiles && $result) {
                foreach ($images as $image) {
                    if (file_exists($image['image_path'])) {
                        @unlink($image['image_path']);
                    }
                }
            }

            Database::commit();
            
            return $result;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في deleteAllCarImages: " . $e->getMessage());
            return false;
        }
    }

    /**
     * عد صور سيارة
     * 
     * @param int $carId معرف السيارة
     * @param string|null $type نوع الصور (اختياري)
     * @return int
     */
    public function countCarImages(int $carId, ?string $type = null): int
    {
        try {
            $query = "SELECT COUNT(*) FROM {$this->table} WHERE car_id = ?";
            $params = [$carId];
            
            if ($type) {
                $query .= " AND image_type = ?";
                $params[] = $type;
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log("خطأ في countCarImages: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * نسخ صور من سيارة إلى أخرى
     * 
     * @param int $fromCarId معرف السيارة المصدر
     * @param int $toCarId معرف السيارة الهدف
     * @param bool $copyFiles نسخ الملفات الفعلية
     * @return bool
     */
    public function copyCarImages(int $fromCarId, int $toCarId, bool $copyFiles = true): bool
    {
        try {
            $sourceImages = $this->getCarImages($fromCarId);
            
            if (empty($sourceImages)) {
                return true;
            }

            Database::beginTransaction();

            foreach ($sourceImages as $image) {
                $newImagePath = $image['image_path'];
                
                // نسخ الملف إذا طلب ذلك
                if ($copyFiles && file_exists($image['image_path'])) {
                    $pathInfo = pathinfo($image['image_path']);
                    $newImagePath = $pathInfo['dirname'] . '/' . 
                                   uniqid('car_' . $toCarId . '_') . '.' . 
                                   $pathInfo['extension'];
                    
                    if (!copy($image['image_path'], $newImagePath)) {
                        continue; // تخطي هذه الصورة في حالة فشل النسخ
                    }
                }

                // إضافة الصورة للسيارة الجديدة
                $query = "INSERT INTO {$this->table} 
                         (car_id, image_path, image_type, is_primary, display_order) 
                         VALUES (?, ?, ?, ?, ?)";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    $toCarId,
                    $newImagePath,
                    $image['image_type'],
                    $image['is_primary'],
                    $image['display_order']
                ]);
            }

            Database::commit();
            return true;
        } catch (\PDOException $e) {
            Database::rollBack();
            error_log("خطأ في copyCarImages: " . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على إحصائيات الصور
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        try {
            $query = "SELECT 
                     COUNT(*) as total_images,
                     COUNT(DISTINCT car_id) as cars_with_images,
                     COUNT(CASE WHEN image_type = 'exterior' THEN 1 END) as exterior_images,
                     COUNT(CASE WHEN image_type = 'interior' THEN 1 END) as interior_images,
                     COUNT(CASE WHEN image_type = 'other' THEN 1 END) as other_images,
                     COUNT(CASE WHEN is_primary = 1 THEN 1 END) as primary_images,
                     AVG(images_per_car) as avg_images_per_car
                     FROM {$this->table}
                     CROSS JOIN (
                         SELECT COUNT(*) * 1.0 / COUNT(DISTINCT car_id) as images_per_car
                         FROM {$this->table}
                     ) as stats";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log("خطأ في getStatistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * البحث عن صور
     * 
     * @param array $criteria معايير البحث
     * @return array
     */
    public function search(array $criteria): array
    {
        try {
            $query = "SELECT i.*, c.plate_number, c.nickname,
                     br.name as brand_name, m.name as model_name
                     FROM {$this->table} i
                     INNER JOIN cars c ON i.car_id = c.id
                     INNER JOIN car_brands br ON c.brand_id = br.id
                     INNER JOIN car_models m ON c.model_id = m.id
                     WHERE c.deleted_at IS NULL";
            
            $params = [];

            if (!empty($criteria['car_id'])) {
                $query .= " AND i.car_id = ?";
                $params[] = $criteria['car_id'];
            }

            if (!empty($criteria['image_type'])) {
                $query .= " AND i.image_type = ?";
                $params[] = $criteria['image_type'];
            }

            if (!empty($criteria['is_primary'])) {
                $query .= " AND i.is_primary = 1";
            }

            $query .= " ORDER BY i.car_id ASC, i.display_order ASC LIMIT 100";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في search: " . $e->getMessage());
            return [];
        }
    }
}

// تسجيل الملف في FileTracker
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 4');
