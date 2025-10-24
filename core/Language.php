<?php
/**
 * File: Language.php
 * Path: /core/Language.php
 * Purpose: Multi-language support system
 * Dependencies: Database.php, Session.php
 */

namespace Core;

/**
 * Language Class
 * نظام اللغات المتعددة
 */
class Language
{
    /** @var string اللغة الحالية */
    private static string $currentLanguage = 'ar';
    
    /** @var array ذاكرة التخزين المؤقت للترجمات */
    private static array $translations = [];
    
    /** @var array اللغات المتاحة */
    private static array $availableLanguages = [];
    
    /**
     * تهيئة نظام اللغات
     * 
     * @return void
     */
    public static function init(): void
    {
        // تحميل اللغات المتاحة
        self::loadAvailableLanguages();
        
        // تحديد اللغة الحالية
        self::detectLanguage();
    }
    
    /**
     * تعيين اللغة
     * 
     * @param string $code
     * @return void
     */
    public static function setLanguage(string $code): void
    {
        if (isset(self::$availableLanguages[$code])) {
            self::$currentLanguage = $code;
            Session::set('language', $code);
            self::$translations = []; // إعادة تعيين الكاش
        }
    }
    
    /**
     * الحصول على اللغة الحالية
     * 
     * @return string
     */
    public static function getLanguage(): string
    {
        return self::$currentLanguage;
    }
    
    /**
     * ترجمة نص
     * 
     * @param string $key
     * @param array $replace
     * @param string|null $lang
     * @return string
     */
    public static function trans(string $key, array $replace = [], ?string $lang = null): string
    {
        $lang = $lang ?? self::$currentLanguage;
        
        // البحث في الكاش
        if (isset(self::$translations[$lang][$key])) {
            return self::replaceParameters(self::$translations[$lang][$key], $replace);
        }
        
        // تحميل من قاعدة البيانات
        $translation = Database::selectOne(
            "SELECT translation_value FROM translations WHERE lang_code = ? AND translation_key = ?",
            [$lang, $key]
        );
        
        $value = $translation['translation_value'] ?? $key;
        
        // حفظ في الكاش
        self::$translations[$lang][$key] = $value;
        
        return self::replaceParameters($value, $replace);
    }
    
    /**
     * ترجمة مع اختيار الجمع
     * 
     * @param string $key
     * @param int $number
     * @param array $replace
     * @param string|null $lang
     * @return string
     */
    public static function transChoice(string $key, int $number, array $replace = [], ?string $lang = null): string
    {
        $translation = self::trans($key, $replace, $lang);
        
        // دعم صيغ الجمع (مثال بسيط)
        $parts = explode('|', $translation);
        
        if (count($parts) === 1) {
            return $parts[0];
        }
        
        // صيغة الجمع العربية
        if ($number == 0) {
            return $parts[0] ?? $translation;
        } elseif ($number == 1) {
            return $parts[1] ?? $translation;
        } elseif ($number == 2) {
            return $parts[2] ?? $translation;
        } elseif ($number <= 10) {
            return $parts[3] ?? $translation;
        } else {
            return $parts[4] ?? $translation;
        }
    }
    
    /**
     * التحقق من وجود ترجمة
     * 
     * @param string $key
     * @param string|null $lang
     * @return bool
     */
    public static function has(string $key, ?string $lang = null): bool
    {
        $lang = $lang ?? self::$currentLanguage;
        
        if (isset(self::$translations[$lang][$key])) {
            return true;
        }
        
        $translation = Database::selectOne(
            "SELECT translation_value FROM translations WHERE lang_code = ? AND translation_key = ?",
            [$lang, $key]
        );
        
        return $translation !== null;
    }
    
    /**
     * هل اللغة الحالية RTL؟
     * 
     * @return bool
     */
    public static function isRTL(): bool
    {
        return self::$availableLanguages[self::$currentLanguage]['direction'] ?? 'ltr' === 'rtl';
    }
    
    /**
     * الحصول على اتجاه النص
     * 
     * @return string
     */
    public static function getDirection(): string
    {
        return self::isRTL() ? 'rtl' : 'ltr';
    }
    
    /**
     * الحصول على جميع اللغات
     * 
     * @return array
     */
    public static function getAllLanguages(): array
    {
        return self::$availableLanguages;
    }
    
    /**
     * تحميل اللغات المتاحة
     * 
     * @return void
     */
    private static function loadAvailableLanguages(): void
    {
        $languages = Database::select("SELECT * FROM languages WHERE is_active = 1");
        
        foreach ($languages as $lang) {
            self::$availableLanguages[$lang['code']] = $lang;
        }
    }
    
    /**
     * اكتشاف اللغة
     * 
     * @return void
     */
    private static function detectLanguage(): void
    {
        // من الجلسة
        if (Session::has('language')) {
            $lang = Session::get('language');
            if (isset(self::$availableLanguages[$lang])) {
                self::$currentLanguage = $lang;
                return;
            }
        }
        
        // من المتصفح
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if (isset(self::$availableLanguages[$browserLang])) {
                self::$currentLanguage = $browserLang;
                return;
            }
        }
        
        // اللغة الافتراضية
        foreach (self::$availableLanguages as $code => $lang) {
            if ($lang['is_default']) {
                self::$currentLanguage = $code;
                return;
            }
        }
    }
    
    /**
     * استبدال المعاملات
     * 
     * @param string $text
     * @param array $replace
     * @return string
     */
    private static function replaceParameters(string $text, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $text = str_replace(':' . $key, $value, $text);
        }
        
        return $text;
    }
}
