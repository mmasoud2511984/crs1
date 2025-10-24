<?php
/**
 * File: Helper.php
 * Path: /core/Helper.php
 * Purpose: Global helper functions
 * Dependencies: Various
 */

// ============================================================================
// URL Helpers
// ============================================================================

/**
 * توليد URL كامل
 * 
 * @param string $path
 * @return string
 */
function url(string $path = ''): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = ltrim($path, '/');
    
    return "{$protocol}://{$host}/{$path}";
}

/**
 * توليد URL للـ assets
 * 
 * @param string $path
 * @return string
 */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * توليد URL من اسم route
 * 
 * @param string $name
 * @param array $params
 * @return string
 */
function route(string $name, array $params = []): string
{
    // يمكن تحسينها مع نظام routes متقدم
    return url($name);
}

// ============================================================================
// String Helpers
// ============================================================================

/**
 * تحديد طول النص
 * 
 * @param string $value
 * @param int $limit
 * @param string $end
 * @return string
 */
function str_limit(string $value, int $limit = 100, string $end = '...'): string
{
    if (mb_strlen($value) <= $limit) {
        return $value;
    }
    
    return mb_substr($value, 0, $limit) . $end;
}

/**
 * تحويل النص إلى slug
 * 
 * @param string $title
 * @param string $separator
 * @return string
 */
function str_slug(string $title, string $separator = '-'): string
{
    // تحويل للأحرف الصغيرة
    $title = mb_strtolower($title);
    
    // استبدال المسافات
    $title = preg_replace('/\s+/', $separator, $title);
    
    // إزالة الأحرف الخاصة
    $title = preg_replace('/[^a-z0-9' . preg_quote($separator) . ']/', '', $title);
    
    // إزالة المكرر
    $title = preg_replace('/' . preg_quote($separator) . '+/', $separator, $title);
    
    return trim($title, $separator);
}

/**
 * توليد نص عشوائي
 * 
 * @param int $length
 * @return string
 */
function str_random(int $length = 16): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * تحويل الحرف الأول لكبير
 * 
 * @param string $value
 * @return string
 */
function str_title(string $value): string
{
    return mb_convert_case($value, MB_CASE_TITLE);
}

/**
 * تحويل لأحرف كبيرة
 * 
 * @param string $value
 * @return string
 */
function str_upper(string $value): string
{
    return mb_strtoupper($value);
}

/**
 * تحويل لأحرف صغيرة
 * 
 * @param string $value
 * @return string
 */
function str_lower(string $value): string
{
    return mb_strtolower($value);
}

// ============================================================================
// Array Helpers
// ============================================================================

/**
 * الحصول على قيمة من array
 * 
 * @param array $array
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function array_get(array $array, string $key, $default = null)
{
    if (isset($array[$key])) {
        return $array[$key];
    }
    
    // دعم dot notation
    if (strpos($key, '.') !== false) {
        $keys = explode('.', $key);
        foreach ($keys as $k) {
            if (!isset($array[$k])) {
                return $default;
            }
            $array = $array[$k];
        }
        return $array;
    }
    
    return $default;
}

/**
 * التحقق من وجود مفتاح
 * 
 * @param array $array
 * @param string $key
 * @return bool
 */
function array_has(array $array, string $key): bool
{
    return array_get($array, $key) !== null;
}

/**
 * الحصول على مفاتيح محددة فقط
 * 
 * @param array $array
 * @param array $keys
 * @return array
 */
function array_only(array $array, array $keys): array
{
    return array_intersect_key($array, array_flip($keys));
}

/**
 * استبعاد مفاتيح محددة
 * 
 * @param array $array
 * @param array $keys
 * @return array
 */
function array_except(array $array, array $keys): array
{
    return array_diff_key($array, array_flip($keys));
}

/**
 * تسطيح array متعدد الأبعاد
 * 
 * @param array $array
 * @return array
 */
function array_flatten(array $array): array
{
    $result = [];
    
    array_walk_recursive($array, function($value) use (&$result) {
        $result[] = $value;
    });
    
    return $result;
}

// ============================================================================
// Date Helpers
// ============================================================================

/**
 * التاريخ والوقت الحالي
 * 
 * @param string $format
 * @return string
 */
function now(string $format = 'Y-m-d H:i:s'): string
{
    return date($format);
}

/**
 * تاريخ اليوم
 * 
 * @param string $format
 * @return string
 */
function today(string $format = 'Y-m-d'): string
{
    return date($format);
}

/**
 * تنسيق تاريخ
 * 
 * @param string $date
 * @param string $format
 * @return string
 */
function date_format_custom(string $date, string $format = 'Y-m-d'): string
{
    return date($format, strtotime($date));
}

/**
 * الفرق بين تاريخين
 * 
 * @param string $date1
 * @param string $date2
 * @return int
 */
function date_diff_days(string $date1, string $date2): int
{
    $d1 = strtotime($date1);
    $d2 = strtotime($date2);
    
    return floor(($d2 - $d1) / (60 * 60 * 24));
}

// ============================================================================
// Translation Helpers
// ============================================================================

/**
 * ترجمة نص
 * 
 * @param string $key
 * @param array $replace
 * @return string
 */
function trans(string $key, array $replace = []): string
{
    return Core\Language::trans($key, $replace);
}

/**
 * ترجمة مع جمع
 * 
 * @param string $key
 * @param int $number
 * @param array $replace
 * @return string
 */
function trans_choice(string $key, int $number, array $replace = []): string
{
    return Core\Language::transChoice($key, $number, $replace);
}

// ============================================================================
// Form Helpers
// ============================================================================

/**
 * قيمة قديمة من النموذج
 * 
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function old(string $key, $default = null)
{
    return Core\Session::getFlash('old_' . $key, $default);
}

/**
 * حقل CSRF
 * 
 * @return string
 */
function csrf_field(): string
{
    $token = Core\Security::generateCsrfToken();
    return '<input type="hidden" name="_token" value="' . $token . '">';
}

/**
 * حقل Method
 * 
 * @param string $method
 * @return string
 */
function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
}

// ============================================================================
// Validation Helpers
// ============================================================================

/**
 * عرض أخطاء التحقق
 * 
 * @param string $field
 * @return string
 */
function error(string $field): string
{
    $errors = Core\Session::getFlash('errors', []);
    return $errors[$field] ?? '';
}

/**
 * التحقق من وجود أخطاء
 * 
 * @param string|null $field
 * @return bool
 */
function has_error(?string $field = null): bool
{
    $errors = Core\Session::getFlash('errors', []);
    
    if ($field) {
        return isset($errors[$field]);
    }
    
    return !empty($errors);
}

// ============================================================================
// File Helpers
// ============================================================================

/**
 * حجم ملف بصيغة قابلة للقراءة
 * 
 * @param int $bytes
 * @param int $precision
 * @return string
 */
function format_bytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// ============================================================================
// Debug Helpers
// ============================================================================

/**
 * طباعة متغير وإيقاف التنفيذ
 * 
 * @param mixed $var
 * @return void
 */
function dd($var): void
{
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    exit;
}

/**
 * طباعة متغير
 * 
 * @param mixed $var
 * @return void
 */
function dump($var): void
{
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
}

// ============================================================================
// Security Helpers
// ============================================================================

/**
 * تنظيف HTML
 * 
 * @param string $value
 * @return string
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * تنظيف من XSS
 * 
 * @param mixed $data
 * @return mixed
 */
function clean($data)
{
    return Core\Security::xssClean($data);
}

// ============================================================================
// Number Helpers
// ============================================================================

/**
 * تنسيق رقم
 * 
 * @param float $number
 * @param int $decimals
 * @return string
 */
function number_format_custom(float $number, int $decimals = 2): string
{
    return number_format($number, $decimals, '.', ',');
}

/**
 * تنسيق مبلغ مالي
 * 
 * @param float $amount
 * @param string $currency
 * @return string
 */
function money(float $amount, string $currency = 'SAR'): string
{
    return number_format_custom($amount, 2) . ' ' . $currency;
}

// ============================================================================
// Misc Helpers
// ============================================================================

/**
 * توليد معرف فريد
 * 
 * @return string
 */
function unique_id(): string
{
    return uniqid('', true);
}

/**
 * التحقق من JSON
 * 
 * @param string $string
 * @return bool
 */
function is_json(string $string): bool
{
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * إعادة التوجيه
 * 
 * @param string $url
 * @return void
 */
function redirect(string $url): void
{
    Core\Response::redirect($url);
}

/**
 * العودة للخلف
 * 
 * @return void
 */
function back(): void
{
    Core\Response::back();
}

/**
 * إنشاء استجابة JSON
 * 
 * @param array $data
 * @param int $status
 * @return void
 */
function json_response(array $data, int $status = 200): void
{
    Core\Response::json($data, $status)->send();
}
