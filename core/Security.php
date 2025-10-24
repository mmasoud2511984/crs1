<?php
/**
 * File: Security.php
 * Path: /core/Security.php
 * Purpose: Security features (CSRF, XSS, Encryption, Rate Limiting)
 * Dependencies: Session.php
 */

namespace Core;

/**
 * Security Class  
 * نظام أمان شامل
 */
class Security
{
    /** @var string مفتاح التشفير */
    private const ENCRYPTION_KEY = 'your-secret-key-here-change-in-production';
    
    /** @var string مفتاح CSRF */
    private const CSRF_TOKEN_KEY = '_csrf_token';
    
    /**
     * توليد CSRF token
     * 
     * @return string
     */
    public static function generateCsrfToken(): string
    {
        if (!Session::has(self::CSRF_TOKEN_KEY)) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::CSRF_TOKEN_KEY, $token);
        }
        
        return Session::get(self::CSRF_TOKEN_KEY);
    }
    
    /**
     * التحقق من CSRF token
     * 
     * @param string $token
     * @return bool
     */
    public static function verifyCsrfToken(string $token): bool
    {
        $sessionToken = Session::get(self::CSRF_TOKEN_KEY);
        
        if (!$sessionToken) {
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * تنظيف من XSS
     * 
     * @param mixed $data
     * @return mixed
     */
    public static function xssClean($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::xssClean($value);
            }
            return $data;
        }
        
        if (is_string($data)) {
            // إزالة العلامات الخطرة
            $data = strip_tags($data);
            
            // تحويل الأحرف الخاصة
            $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            return $data;
        }
        
        return $data;
    }
    
    /**
     * تشفير كلمة المرور
     * 
     * @param string $password
     * @return string
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * التحقق من كلمة المرور
     * 
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    /**
     * تشفير بيانات
     * 
     * @param string $data
     * @return string
     */
    public static function encrypt(string $data): string
    {
        $key = hash('sha256', self::ENCRYPTION_KEY, true);
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * فك تشفير بيانات
     * 
     * @param string $encrypted
     * @return string|false
     */
    public static function decrypt(string $encrypted)
    {
        $key = hash('sha256', self::ENCRYPTION_KEY, true);
        $data = base64_decode($encrypted);
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }
    
    /**
     * تنظيف البيانات
     * 
     * @param mixed $data
     * @param array $allowed
     * @return mixed
     */
    public static function sanitize($data, array $allowed = [])
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitize($value, $allowed);
            }
            return $data;
        }
        
        if (is_string($data)) {
            // إذا كانت هناك علامات مسموحة
            if (!empty($allowed)) {
                $data = strip_tags($data, '<' . implode('><', $allowed) . '>');
            } else {
                $data = strip_tags($data);
            }
            
            // تنظيف المسافات
            $data = trim($data);
            
            return $data;
        }
        
        return $data;
    }
    
    /**
     * فحص Rate Limiting
     * 
     * @param string $key
     * @param int $maxAttempts
     * @param int $decayMinutes
     * @return bool
     */
    public static function checkRateLimit(string $key, int $maxAttempts = 5, int $decayMinutes = 1): bool
    {
        $cacheKey = 'rate_limit_' . $key;
        
        // الحصول من الكاش
        $attempts = Session::get($cacheKey, []);
        
        // تنظيف المحاولات القديمة
        $now = time();
        $attempts = array_filter($attempts, function($timestamp) use ($now, $decayMinutes) {
            return $timestamp > ($now - ($decayMinutes * 60));
        });
        
        // التحقق من الحد الأقصى
        if (count($attempts) >= $maxAttempts) {
            return false;
        }
        
        // إضافة محاولة جديدة
        $attempts[] = $now;
        Session::set($cacheKey, $attempts);
        
        return true;
    }
    
    /**
     * توليد رمز عشوائي آمن
     * 
     * @param int $length
     * @return string
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * توليد OTP
     * 
     * @param int $length
     * @return string
     */
    public static function generateOTP(int $length = 6): string
    {
        $min = pow(10, $length - 1);
        $max = pow(10, $length) - 1;
        
        return (string) random_int($min, $max);
    }
    
    /**
     * التحقق من قوة كلمة المرور
     * 
     * @param string $password
     * @return bool
     */
    public static function isStrongPassword(string $password): bool
    {
        // الطول الأدنى 8 أحرف
        if (strlen($password) < 8) {
            return false;
        }
        
        // يجب أن تحتوي على حرف كبير
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        // يجب أن تحتوي على حرف صغير
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        // يجب أن تحتوي على رقم
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * تنظيف اسم الملف
     * 
     * @param string $filename
     * @return string
     */
    public static function sanitizeFilename(string $filename): string
    {
        // الحصول على الامتداد
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        // تنظيف الاسم
        $name = preg_replace('/[^a-zA-Z0-9-_]/', '', $name);
        $name = substr($name, 0, 50);
        
        // إضافة timestamp
        $name = $name . '_' . time();
        
        return $name . '.' . $ext;
    }
}
