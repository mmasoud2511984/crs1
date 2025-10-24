<?php
/**
 * File: Validator.php
 * Path: /core/Validator.php
 * Purpose: Input validation with multiple rules
 * Dependencies: Database.php
 */

namespace Core;

/**
 * Validator Class
 * نظام التحقق من المدخلات
 */
class Validator
{
    /** @var array البيانات */
    private array $data;
    
    /** @var array القواعد */
    private array $rules;
    
    /** @var array الأخطاء */
    private array $errors = [];
    
    /** @var array القواعد المخصصة */
    private static array $customRules = [];
    
    /**
     * Constructor
     * 
     * @param array $data
     * @param array $rules
     */
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }
    
    /**
     * التحقق من البيانات
     * 
     * @return bool
     */
    public function validate(): bool
    {
        foreach ($this->rules as $field => $rulesString) {
            $rules = explode('|', $rulesString);
            
            foreach ($rules as $rule) {
                $this->applyRule($field, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * فشل التحقق؟
     * 
     * @return bool
     */
    public function fails(): bool
    {
        return !$this->validate();
    }
    
    /**
     * الحصول على الأخطاء
     * 
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }
    
    /**
     * الحصول على الأخطاء
     * 
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * إضافة خطأ
     * 
     * @param string $field
     * @param string $message
     * @return void
     */
    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }
    
    /**
     * تطبيق قاعدة
     * 
     * @param string $field
     * @param string $rule
     * @return void
     */
    private function applyRule(string $field, string $rule): void
    {
        // فصل القاعدة والمعاملات
        [$ruleName, $parameter] = $this->parseRule($rule);
        
        // تخطي إذا كان هناك خطأ بالفعل
        if (isset($this->errors[$field])) {
            return;
        }
        
        // قيمة الحقل
        $value = $this->data[$field] ?? null;
        
        // تطبيق القاعدة
        $method = 'validate' . ucfirst($ruleName);
        
        if (method_exists($this, $method)) {
            $this->$method($field, $value, $parameter);
        } elseif (isset(self::$customRules[$ruleName])) {
            $callback = self::$customRules[$ruleName];
            if (!$callback($value, $parameter, $this->data)) {
                $this->addError($field, "الحقل {$field} غير صحيح");
            }
        }
    }
    
    /**
     * فصل القاعدة
     * 
     * @param string $rule
     * @return array
     */
    private function parseRule(string $rule): array
    {
        if (strpos($rule, ':') !== false) {
            return explode(':', $rule, 2);
        }
        
        return [$rule, null];
    }
    
    /**
     * قاعدة: required
     */
    private function validateRequired(string $field, $value, $parameter): void
    {
        if (empty($value) && $value !== '0') {
            $this->addError($field, "الحقل {$field} مطلوب");
        }
    }
    
    /**
     * قاعدة: email
     */
    private function validateEmail(string $field, $value, $parameter): void
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "الحقل {$field} يجب أن يكون بريد إلكتروني صحيح");
        }
    }
    
    /**
     * قاعدة: min
     */
    private function validateMin(string $field, $value, $parameter): void
    {
        if (strlen($value) < (int)$parameter) {
            $this->addError($field, "الحقل {$field} يجب أن يكون على الأقل {$parameter} أحرف");
        }
    }
    
    /**
     * قاعدة: max
     */
    private function validateMax(string $field, $value, $parameter): void
    {
        if (strlen($value) > (int)$parameter) {
            $this->addError($field, "الحقل {$field} يجب ألا يتجاوز {$parameter} حرف");
        }
    }
    
    /**
     * قاعدة: numeric
     */
    private function validateNumeric(string $field, $value, $parameter): void
    {
        if ($value && !is_numeric($value)) {
            $this->addError($field, "الحقل {$field} يجب أن يكون رقماً");
        }
    }
    
    /**
     * قاعدة: integer
     */
    private function validateInteger(string $field, $value, $parameter): void
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, "الحقل {$field} يجب أن يكون عدداً صحيحاً");
        }
    }
    
    /**
     * قاعدة: alpha
     */
    private function validateAlpha(string $field, $value, $parameter): void
    {
        if ($value && !preg_match('/^[a-zA-Z]+$/', $value)) {
            $this->addError($field, "الحقل {$field} يجب أن يحتوي على حروف فقط");
        }
    }
    
    /**
     * قاعدة: alpha_num
     */
    private function validateAlphaNum(string $field, $value, $parameter): void
    {
        if ($value && !preg_match('/^[a-zA-Z0-9]+$/', $value)) {
            $this->addError($field, "الحقل {$field} يجب أن يحتوي على حروف وأرقام فقط");
        }
    }
    
    /**
     * قاعدة: alpha_dash
     */
    private function validateAlphaDash(string $field, $value, $parameter): void
    {
        if ($value && !preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
            $this->addError($field, "الحقل {$field} يجب أن يحتوي على حروف وأرقام و - _ فقط");
        }
    }
    
    /**
     * قاعدة: url
     */
    private function validateUrl(string $field, $value, $parameter): void
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, "الحقل {$field} يجب أن يكون رابط صحيح");
        }
    }
    
    /**
     * قاعدة: ip
     */
    private function validateIp(string $field, $value, $parameter): void
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_IP)) {
            $this->addError($field, "الحقل {$field} يجب أن يكون IP صحيح");
        }
    }
    
    /**
     * قاعدة: date
     */
    private function validateDate(string $field, $value, $parameter): void
    {
        if ($value && strtotime($value) === false) {
            $this->addError($field, "الحقل {$field} يجب أن يكون تاريخ صحيح");
        }
    }
    
    /**
     * قاعدة: between
     */
    private function validateBetween(string $field, $value, $parameter): void
    {
        [$min, $max] = explode(',', $parameter);
        $length = strlen($value);
        
        if ($length < (int)$min || $length > (int)$max) {
            $this->addError($field, "الحقل {$field} يجب أن يكون بين {$min} و {$max} حرف");
        }
    }
    
    /**
     * قاعدة: in
     */
    private function validateIn(string $field, $value, $parameter): void
    {
        $options = explode(',', $parameter);
        
        if ($value && !in_array($value, $options)) {
            $this->addError($field, "الحقل {$field} يجب أن يكون أحد القيم المسموحة");
        }
    }
    
    /**
     * قاعدة: not_in
     */
    private function validateNotIn(string $field, $value, $parameter): void
    {
        $options = explode(',', $parameter);
        
        if ($value && in_array($value, $options)) {
            $this->addError($field, "الحقل {$field} لا يمكن أن يكون أحد القيم الممنوعة");
        }
    }
    
    /**
     * قاعدة: unique
     */
    private function validateUnique(string $field, $value, $parameter): void
    {
        if (!$value) {
            return;
        }
        
        [$table, $column] = explode(',', $parameter . ',' . $field);
        
        $result = Database::selectOne(
            "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?",
            [$value]
        );
        
        if (($result['count'] ?? 0) > 0) {
            $this->addError($field, "القيمة {$value} مستخدمة بالفعل");
        }
    }
    
    /**
     * قاعدة: exists
     */
    private function validateExists(string $field, $value, $parameter): void
    {
        if (!$value) {
            return;
        }
        
        [$table, $column] = explode(',', $parameter . ',' . $field);
        
        $result = Database::selectOne(
            "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?",
            [$value]
        );
        
        if (($result['count'] ?? 0) == 0) {
            $this->addError($field, "القيمة {$value} غير موجودة");
        }
    }
    
    /**
     * قاعدة: confirmed
     */
    private function validateConfirmed(string $field, $value, $parameter): void
    {
        $confirmField = $field . '_confirmation';
        
        if ($value !== ($this->data[$confirmField] ?? null)) {
            $this->addError($field, "تأكيد {$field} غير متطابق");
        }
    }
    
    /**
     * قاعدة: same
     */
    private function validateSame(string $field, $value, $parameter): void
    {
        if ($value !== ($this->data[$parameter] ?? null)) {
            $this->addError($field, "الحقل {$field} يجب أن يطابق {$parameter}");
        }
    }
    
    /**
     * قاعدة: different
     */
    private function validateDifferent(string $field, $value, $parameter): void
    {
        if ($value === ($this->data[$parameter] ?? null)) {
            $this->addError($field, "الحقل {$field} يجب أن يختلف عن {$parameter}");
        }
    }
    
    /**
     * قاعدة: regex
     */
    private function validateRegex(string $field, $value, $parameter): void
    {
        if ($value && !preg_match($parameter, $value)) {
            $this->addError($field, "الحقل {$field} بصيغة غير صحيحة");
        }
    }
    
    /**
     * إضافة قاعدة مخصصة
     * 
     * @param string $name
     * @param callable $callback
     * @return void
     */
    public static function addRule(string $name, callable $callback): void
    {
        self::$customRules[$name] = $callback;
    }
}
