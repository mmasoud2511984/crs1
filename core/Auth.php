<?php
/**
 * File: Auth.php
 * Path: /core/Auth.php
 * Purpose: Authentication system with 2FA support
 * Dependencies: Session.php, Database.php, Security.php
 */

namespace Core;

/**
 * Auth Class
 * نظام المصادقة مع دعم 2FA
 */
class Auth
{
    /** @var string مفتاح المستخدم في Session */
    private const SESSION_USER_KEY = '_auth_user';
    
    /** @var string مفتاح 2FA */
    private const SESSION_2FA_KEY = '_auth_2fa_verified';
    
    /**
     * محاولة تسجيل الدخول
     * 
     * @param string $username
     * @param string $password
     * @param bool $remember
     * @return bool
     */
    public static function attempt(string $username, string $password, bool $remember = false): bool
    {
        $user = Database::selectOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 AND deleted_at IS NULL",
            [$username, $username]
        );
        
        if (!$user) {
            self::incrementFailedAttempts($username);
            return false;
        }
        
        // التحقق من القفل
        if (self::isLocked($user)) {
            return false;
        }
        
        // التحقق من كلمة المرور
        if (!Security::verifyPassword($password, $user['password_hash'])) {
            self::incrementFailedAttempts($username);
            return false;
        }
        
        // إعادة تعيين محاولات الفشل
        self::resetFailedAttempts($user['id']);
        
        // التحقق من 2FA
        if ($user['two_factor_enabled']) {
            Session::set('_2fa_pending', $user['id']);
            return false; // يحتاج إلى رمز 2FA
        }
        
        // تسجيل الدخول
        return self::login($user['id'], $remember);
    }
    
    /**
     * تسجيل دخول مستخدم
     * 
     * @param int $userId
     * @param bool $remember
     * @return bool
     */
    public static function login(int $userId, bool $remember = false): bool
    {
        $user = Database::selectOne("SELECT * FROM users WHERE id = ?", [$userId]);
        
        if (!$user) {
            return false;
        }
        
        // تجديد معرف الجلسة للأمان
        Session::regenerate();
        
        // حفظ معلومات المستخدم
        Session::set(self::SESSION_USER_KEY, [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'full_name' => $user['full_name']
        ]);
        
        // تحديث آخر تسجيل دخول
        Database::update(
            "UPDATE users SET last_login = NOW(), last_login_ip = ? WHERE id = ?",
            [$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $userId]
        );
        
        // Remember me
        if ($remember) {
            self::setRememberToken($userId);
        }
        
        return true;
    }
    
    /**
     * تسجيل الخروج
     * 
     * @return bool
     */
    public static function logout(): bool
    {
        Session::delete(self::SESSION_USER_KEY);
        Session::delete(self::SESSION_2FA_KEY);
        
        // حذف remember token
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        return true;
    }
    
    /**
     * التحقق من تسجيل الدخول
     * 
     * @return bool
     */
    public static function check(): bool
    {
        return Session::has(self::SESSION_USER_KEY);
    }
    
    /**
     * الحصول على المستخدم الحالي
     * 
     * @return array|null
     */
    public static function user(): ?array
    {
        return Session::get(self::SESSION_USER_KEY);
    }
    
    /**
     * الحصول على معرف المستخدم
     * 
     * @return int|null
     */
    public static function id(): ?int
    {
        $user = self::user();
        return $user['id'] ?? null;
    }
    
    /**
     * التحقق من الصلاحية
     * 
     * @param string $permission
     * @return bool
     */
    public static function hasPermission(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }
        
        $userId = self::id();
        
        $result = Database::selectOne(
            "SELECT COUNT(*) as count FROM user_roles ur
             JOIN role_permissions rp ON ur.role_id = rp.role_id
             JOIN permissions p ON rp.permission_id = p.id
             WHERE ur.user_id = ? AND p.slug = ?",
            [$userId, $permission]
        );
        
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * التحقق من الدور
     * 
     * @param string $role
     * @return bool
     */
    public static function hasRole(string $role): bool
    {
        if (!self::check()) {
            return false;
        }
        
        $userId = self::id();
        
        $result = Database::selectOne(
            "SELECT COUNT(*) as count FROM user_roles ur
             JOIN roles r ON ur.role_id = r.id
             WHERE ur.user_id = ? AND r.slug = ?",
            [$userId, $role]
        );
        
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * تفعيل 2FA
     * 
     * @param int $userId
     * @return string
     */
    public static function enable2FA(int $userId): string
    {
        $secret = self::generate2FASecret();
        
        Database::update(
            "UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1 WHERE id = ?",
            [$secret, $userId]
        );
        
        return $secret;
    }
    
    /**
     * التحقق من رمز 2FA
     * 
     * @param string $code
     * @return bool
     */
    public static function verify2FA(string $code): bool
    {
        $userId = Session::get('_2fa_pending');
        
        if (!$userId) {
            return false;
        }
        
        $user = Database::selectOne("SELECT two_factor_secret FROM users WHERE id = ?", [$userId]);
        
        if (!$user || !$user['two_factor_secret']) {
            return false;
        }
        
        // التحقق من الرمز (يمكن استخدام مكتبة TOTP)
        // هذا مثال بسيط
        $valid = $code === self::generate2FACode($user['two_factor_secret']);
        
        if ($valid) {
            Session::delete('_2fa_pending');
            Session::set(self::SESSION_2FA_KEY, true);
            self::login($userId);
        }
        
        return $valid;
    }
    
    /**
     * توليد سر 2FA
     * 
     * @return string
     */
    private static function generate2FASecret(): string
    {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * توليد رمز 2FA
     * 
     * @param string $secret
     * @return string
     */
    private static function generate2FACode(string $secret): string
    {
        // مثال بسيط - في الإنتاج استخدم مكتبة TOTP
        return substr(hash('sha256', $secret . floor(time() / 30)), 0, 6);
    }
    
    /**
     * زيادة محاولات الفشل
     * 
     * @param string $identifier
     * @return void
     */
    private static function incrementFailedAttempts(string $identifier): void
    {
        Database::execute(
            "UPDATE users SET failed_login_attempts = failed_login_attempts + 1,
             locked_until = IF(failed_login_attempts >= 4, DATE_ADD(NOW(), INTERVAL 15 MINUTE), locked_until)
             WHERE username = ? OR email = ?",
            [$identifier, $identifier]
        );
    }
    
    /**
     * إعادة تعيين محاولات الفشل
     * 
     * @param int $userId
     * @return void
     */
    private static function resetFailedAttempts(int $userId): void
    {
        Database::update(
            "UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?",
            [$userId]
        );
    }
    
    /**
     * التحقق من القفل
     * 
     * @param array $user
     * @return bool
     */
    private static function isLocked(array $user): bool
    {
        if (!$user['locked_until']) {
            return false;
        }
        
        return strtotime($user['locked_until']) > time();
    }
    
    /**
     * تعيين remember token
     * 
     * @param int $userId
     * @return void
     */
    private static function setRememberToken(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        
        Database::update(
            "UPDATE users SET remember_token = ? WHERE id = ?",
            [hash('sha256', $token), $userId]
        );
        
        setcookie('remember_token', $token, time() + (30 * 24 * 3600), '/');
    }
}
