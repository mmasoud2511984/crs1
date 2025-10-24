<?php
/**
 * File: Uploader.php
 * Path: /core/Uploader.php
 * Purpose: Secure file upload handler
 * Dependencies: Security.php
 */

namespace Core;

/**
 * Uploader Class
 * رفع الملفات بشكل آمن
 */
class Uploader
{
    /** @var array أنواع الملفات المسموحة */
    private const ALLOWED_TYPES = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
        'any' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']
    ];
    
    /** @var int الحجم الأقصى (5 ميجا) */
    private const MAX_SIZE = 5242880;
    
    /**
     * رفع ملف
     * 
     * @param array $file
     * @param string $path
     * @param array $options
     * @return string|false
     */
    public static function upload(array $file, string $path, array $options = [])
    {
        // التحقق من وجود خطأ
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        // التحقق من النوع
        $allowedTypes = $options['types'] ?? self::ALLOWED_TYPES['any'];
        if (!self::isAllowedType($file, $allowedTypes)) {
            return false;
        }
        
        // التحقق من الحجم
        $maxSize = $options['max_size'] ?? self::MAX_SIZE;
        if (!self::isValidSize($file, $maxSize)) {
            return false;
        }
        
        // إنشاء المجلد إذا لم يكن موجوداً
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        
        // توليد اسم آمن
        $filename = self::generateFilename($file['name']);
        $destination = rtrim($path, '/') . '/' . $filename;
        
        // نقل الملف
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // إنشاء thumbnail إذا كان صورة
            if (isset($options['thumbnail']) && $options['thumbnail']) {
                self::createThumbnail(
                    $destination,
                    $options['thumb_width'] ?? 200,
                    $options['thumb_height'] ?? 200
                );
            }
            
            return $filename;
        }
        
        return false;
    }
    
    /**
     * رفع ملفات متعددة
     * 
     * @param array $files
     * @param string $path
     * @param array $options
     * @return array
     */
    public static function uploadMultiple(array $files, string $path, array $options = []): array
    {
        $uploaded = [];
        
        foreach ($files['name'] as $key => $name) {
            $file = [
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error' => $files['error'][$key],
                'size' => $files['size'][$key]
            ];
            
            $filename = self::upload($file, $path, $options);
            if ($filename) {
                $uploaded[] = $filename;
            }
        }
        
        return $uploaded;
    }
    
    /**
     * التحقق من نوع الملف
     * 
     * @param array $file
     * @param array $types
     * @return bool
     */
    public static function isAllowedType(array $file, array $types): bool
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        return in_array($ext, $types);
    }
    
    /**
     * التحقق من حجم الملف
     * 
     * @param array $file
     * @param int $maxSize
     * @return bool
     */
    public static function isValidSize(array $file, int $maxSize): bool
    {
        return $file['size'] <= $maxSize;
    }
    
    /**
     * توليد اسم ملف آمن
     * 
     * @param string $originalName
     * @return string
     */
    public static function generateFilename(string $originalName): string
    {
        return Security::sanitizeFilename($originalName);
    }
    
    /**
     * تغيير حجم صورة
     * 
     * @param string $file
     * @param int $width
     * @param int $height
     * @return bool
     */
    public static function resize(string $file, int $width, int $height): bool
    {
        if (!file_exists($file)) {
            return false;
        }
        
        $info = getimagesize($file);
        if (!$info) {
            return false;
        }
        
        [$origWidth, $origHeight, $type] = $info;
        
        // إنشاء الصورة الأصلية
        $source = match($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($file),
            IMAGETYPE_PNG => imagecreatefrompng($file),
            IMAGETYPE_GIF => imagecreatefromgif($file),
            default => null
        };
        
        if (!$source) {
            return false;
        }
        
        // إنشاء صورة جديدة
        $dest = imagecreatetruecolor($width, $height);
        
        // الحفاظ على الشفافية للـ PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
        }
        
        // تغيير الحجم
        imagecopyresampled($dest, $source, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
        
        // حفظ الصورة
        $result = match($type) {
            IMAGETYPE_JPEG => imagejpeg($dest, $file, 90),
            IMAGETYPE_PNG => imagepng($dest, $file),
            IMAGETYPE_GIF => imagegif($dest, $file),
            default => false
        };
        
        imagedestroy($source);
        imagedestroy($dest);
        
        return $result;
    }
    
    /**
     * إنشاء thumbnail
     * 
     * @param string $file
     * @param int $width
     * @param int $height
     * @return string|false
     */
    public static function createThumbnail(string $file, int $width, int $height)
    {
        $info = pathinfo($file);
        $thumbFile = $info['dirname'] . '/thumb_' . $info['basename'];
        
        if (!copy($file, $thumbFile)) {
            return false;
        }
        
        if (self::resize($thumbFile, $width, $height)) {
            return $thumbFile;
        }
        
        return false;
    }
    
    /**
     * حذف ملف
     * 
     * @param string $file
     * @return bool
     */
    public static function delete(string $file): bool
    {
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return false;
    }
}
