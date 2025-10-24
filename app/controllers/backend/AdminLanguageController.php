<?php
/**
 * File: AdminLanguageController.php
 * Path: /app/controllers/backend/AdminLanguageController.php
 * Purpose: التحكم في اللغات والترجمات
 * Dependencies: Core\Controller, App\Models\Language, App\Models\Translation
 * Phase: Phase 3 - Settings & Administration
 * Created: 2025-10-24
 */

namespace App\Controllers\Backend;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\FileTracker;
use App\Models\Language;
use App\Models\Translation;

/**
 * Class AdminLanguageController
 * 
 * التحكم في اللغات والترجمات
 * - إدارة اللغات (CRUD)
 * - إدارة الترجمات
 * - استيراد وتصدير الترجمات
 * - تعيين اللغة الافتراضية
 * 
 * @package App\Controllers\Backend
 */
class AdminLanguageController extends Controller
{
    /**
     * نموذج اللغات
     */
    private Language $languageModel;
    
    /**
     * نموذج الترجمات
     */
    private Translation $translationModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        // التحقق من صلاحيات الإدارة
        $this->requireAuth();
        $this->requirePermission('manage_languages');
        
        $this->languageModel = new Language();
        $this->translationModel = new Translation();
        
        // تسجيل الملف في FileTracker
        FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 3');
    }

    // ========================================
    // Language Management
    // ========================================

    /**
     * عرض قائمة اللغات
     */
    public function index(): void
    {
        $data = [
            'title' => trans('languages.page_title'),
            'languages' => $this->languageModel->getWithTranslationCount(),
            'stats' => $this->languageModel->getStatistics()
        ];
        
        $this->view('backend/languages/index', $data);
    }

    /**
     * عرض صفحة إضافة لغة جديدة
     */
    public function create(): void
    {
        $data = [
            'title' => trans('languages.create_title'),
            'currencies' => $this->getCurrencies(),
            'dateFormats' => $this->getDateFormats(),
            'timeFormats' => $this->getTimeFormats()
        ];
        
        $this->view('backend/languages/create', $data);
    }

    /**
     * حفظ لغة جديدة
     */
    public function store(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $data = [
            'name' => Request::post('name'),
            'code' => strtolower(Request::post('code')),
            'direction' => Request::post('direction', 'ltr'),
            'currency_symbol' => Request::post('currency_symbol'),
            'currency_code' => strtoupper(Request::post('currency_code')),
            'date_format' => Request::post('date_format', 'Y-m-d'),
            'time_format' => Request::post('time_format', 'H:i:s'),
            'is_active' => Request::post('is_active', 1),
            'flag_icon' => Request::post('flag_icon')
        ];
        
        // التحقق من الصحة
        $errors = $this->languageModel->validate($data);
        
        if (!empty($errors)) {
            Response::json([
                'success' => false,
                'message' => trans('error.validation_failed'),
                'errors' => $errors
            ], 422);
            return;
        }
        
        try {
            $languageId = $this->languageModel->create($data);
            
            if ($languageId) {
                // نسخ الترجمات من اللغة الافتراضية (اختياري)
                $copyFromLang = Request::post('copy_translations_from');
                if ($copyFromLang) {
                    $this->translationModel->copyTranslations($copyFromLang, $data['code']);
                }
                
                $this->log('create_language', 'languages', $languageId, $data);
                
                Response::json([
                    'success' => true,
                    'message' => trans('languages.created_successfully'),
                    'redirect' => '/admin/languages'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.create_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Create language error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * عرض صفحة تعديل لغة
     */
    public function edit(int $id): void
    {
        $language = $this->languageModel->findById($id);
        
        if (!$language) {
            Session::setFlash('error', trans('languages.not_found'));
            Response::redirect('/admin/languages');
            return;
        }
        
        $data = [
            'title' => trans('languages.edit_title'),
            'language' => $language,
            'currencies' => $this->getCurrencies(),
            'dateFormats' => $this->getDateFormats(),
            'timeFormats' => $this->getTimeFormats()
        ];
        
        $this->view('backend/languages/edit', $data);
    }

    /**
     * تحديث لغة
     */
    public function update(int $id): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $language = $this->languageModel->findById($id);
        
        if (!$language) {
            Response::json([
                'success' => false,
                'message' => trans('languages.not_found')
            ], 404);
            return;
        }
        
        $data = [
            'name' => Request::post('name'),
            'direction' => Request::post('direction', 'ltr'),
            'currency_symbol' => Request::post('currency_symbol'),
            'currency_code' => strtoupper(Request::post('currency_code')),
            'date_format' => Request::post('date_format', 'Y-m-d'),
            'time_format' => Request::post('time_format', 'H:i:s'),
            'is_active' => Request::post('is_active', 1),
            'flag_icon' => Request::post('flag_icon')
        ];
        
        // التحقق من الصحة
        $errors = $this->languageModel->validate($data, true);
        
        if (!empty($errors)) {
            Response::json([
                'success' => false,
                'message' => trans('error.validation_failed'),
                'errors' => $errors
            ], 422);
            return;
        }
        
        try {
            $updated = $this->languageModel->update($id, $data);
            
            if ($updated) {
                $this->log('update_language', 'languages', $id, $data);
                
                Response::json([
                    'success' => true,
                    'message' => trans('languages.updated_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.update_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Update language error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * حذف لغة
     */
    public function delete(int $id): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        try {
            $deleted = $this->languageModel->delete($id);
            
            if ($deleted) {
                $this->log('delete_language', 'languages', $id);
                
                Response::json([
                    'success' => true,
                    'message' => trans('languages.deleted_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('languages.cannot_delete')
                ], 400);
            }
        } catch (\Exception $e) {
            error_log("Delete language error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * تفعيل لغة
     */
    public function activate(int $id): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        try {
            $activated = $this->languageModel->activate($id);
            
            if ($activated) {
                $this->log('activate_language', 'languages', $id);
                
                Response::json([
                    'success' => true,
                    'message' => trans('languages.activated_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.update_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Activate language error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * تعطيل لغة
     */
    public function deactivate(int $id): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        try {
            $deactivated = $this->languageModel->deactivate($id);
            
            if ($deactivated) {
                $this->log('deactivate_language', 'languages', $id);
                
                Response::json([
                    'success' => true,
                    'message' => trans('languages.deactivated_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('languages.cannot_deactivate')
                ], 400);
            }
        } catch (\Exception $e) {
            error_log("Deactivate language error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * تعيين لغة كافتراضية
     */
    public function setDefault(int $id): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        try {
            $setDefault = $this->languageModel->setAsDefault($id);
            
            if ($setDefault) {
                $this->log('set_default_language', 'languages', $id);
                
                Response::json([
                    'success' => true,
                    'message' => trans('languages.set_default_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.update_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Set default language error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    // ========================================
    // Translation Management
    // ========================================

    /**
     * عرض صفحة إدارة الترجمات للغة معينة
     */
    public function translations(string $langCode): void
    {
        $language = $this->languageModel->getByCode($langCode);
        
        if (!$language) {
            Session::setFlash('error', trans('languages.not_found'));
            Response::redirect('/admin/languages');
            return;
        }
        
        $category = Request::get('category', 'general');
        $search = Request::get('search', '');
        
        // الحصول على الترجمات
        if (!empty($search)) {
            $translations = $this->translationModel->search($search, $langCode);
        } else {
            $translations = $this->translationModel->getAll($langCode, $category);
        }
        
        $data = [
            'title' => trans('languages.translations_title', ['lang' => $language['name']]),
            'language' => $language,
            'translations' => $translations,
            'categories' => $this->translationModel->getCategories($langCode),
            'currentCategory' => $category,
            'search' => $search,
            'stats' => $this->translationModel->getStatistics($langCode)
        ];
        
        $this->view('backend/languages/translations', $data);
    }

    /**
     * إضافة/تحديث ترجمة
     */
    public function saveTranslation(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $data = [
            'lang_code' => Request::post('lang_code'),
            'translation_key' => Request::post('translation_key'),
            'translation_value' => Request::post('translation_value'),
            'category' => Request::post('category', 'general')
        ];
        
        // التحقق من البيانات
        if (empty($data['lang_code']) || empty($data['translation_key'])) {
            Response::json([
                'success' => false,
                'message' => trans('error.required_fields')
            ], 422);
            return;
        }
        
        try {
            $saved = $this->translationModel->createOrUpdate($data);
            
            if ($saved) {
                $this->log('save_translation', 'translations', null, $data);
                
                Response::json([
                    'success' => true,
                    'message' => trans('translations.saved_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.save_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Save translation error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * حذف ترجمة
     */
    public function deleteTranslation(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $langCode = Request::post('lang_code');
        $key = Request::post('translation_key');
        
        try {
            $deleted = $this->translationModel->deleteTranslation($langCode, $key);
            
            if ($deleted) {
                $this->log('delete_translation', 'translations', null, [
                    'lang_code' => $langCode,
                    'key' => $key
                ]);
                
                Response::json([
                    'success' => true,
                    'message' => trans('translations.deleted_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.delete_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Delete translation error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * نسخ ترجمات من لغة إلى أخرى
     */
    public function copyTranslations(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $fromLang = Request::post('from_lang');
        $toLang = Request::post('to_lang');
        $overwrite = Request::post('overwrite', false);
        
        try {
            $count = $this->translationModel->copyTranslations($fromLang, $toLang, $overwrite);
            
            if ($count > 0) {
                $this->log('copy_translations', 'translations', null, [
                    'from' => $fromLang,
                    'to' => $toLang,
                    'count' => $count
                ]);
                
                Response::json([
                    'success' => true,
                    'message' => trans('translations.copied_successfully', ['count' => $count]),
                    'count' => $count
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('translations.no_translations_to_copy')
                ], 400);
            }
        } catch (\Exception $e) {
            error_log("Copy translations error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * البحث عن المفاتيح المفقودة
     */
    public function findMissingKeys(): void
    {
        $referenceLang = Request::get('reference', 'ar');
        $targetLang = Request::get('target');
        
        if (empty($targetLang)) {
            Response::json([
                'success' => false,
                'message' => trans('error.required_fields')
            ], 422);
            return;
        }
        
        try {
            $missing = $this->translationModel->getMissingKeys($referenceLang, $targetLang);
            
            Response::json([
                'success' => true,
                'missing_keys' => $missing,
                'count' => count($missing)
            ]);
        } catch (\Exception $e) {
            error_log("Find missing keys error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    // ========================================
    // Import/Export
    // ========================================

    /**
     * تصدير ترجمات لغة
     */
    public function exportTranslations(string $langCode): void
    {
        $language = $this->languageModel->getByCode($langCode);
        
        if (!$language) {
            Response::json([
                'success' => false,
                'message' => trans('languages.not_found')
            ], 404);
            return;
        }
        
        $format = Request::get('format', 'json');
        $grouped = Request::get('grouped', false);
        
        try {
            if ($format === 'php') {
                $content = $this->translationModel->exportToPhp($langCode);
                $filename = "translations_{$langCode}.php";
                $contentType = 'text/x-php';
            } else {
                $content = $this->translationModel->exportToJson($langCode, $grouped);
                $filename = "translations_{$langCode}.json";
                $contentType = 'application/json';
            }
            
            $this->log('export_translations', 'translations', null, [
                'lang_code' => $langCode,
                'format' => $format
            ]);
            
            // إرسال الملف للتنزيل
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($content));
            echo $content;
            exit;
        } catch (\Exception $e) {
            error_log("Export translations error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * استيراد ترجمات
     */
    public function importTranslations(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $langCode = Request::post('lang_code');
        $overwrite = Request::post('overwrite', false);
        
        if (!Request::hasFile('import_file')) {
            Response::json([
                'success' => false,
                'message' => trans('translations.import.no_file')
            ], 422);
            return;
        }
        
        $file = $_FILES['import_file'];
        
        // التحقق من نوع الملف
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'json') {
            Response::json([
                'success' => false,
                'message' => trans('translations.import.invalid_format')
            ], 422);
            return;
        }
        
        try {
            $json = file_get_contents($file['tmp_name']);
            $result = $this->translationModel->importFromJson($langCode, $json, $overwrite);
            
            if (empty($result['errors'])) {
                $this->log('import_translations', 'translations', null, [
                    'lang_code' => $langCode,
                    'imported' => $result['imported'],
                    'updated' => $result['updated']
                ]);
                
                Response::json([
                    'success' => true,
                    'message' => trans('translations.import.success'),
                    'imported' => $result['imported'],
                    'updated' => $result['updated']
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('translations.import.failed'),
                    'errors' => $result['errors']
                ], 400);
            }
        } catch (\Exception $e) {
            error_log("Import translations error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * مسح cache الترجمات
     */
    public function clearTranslationsCache(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $langCode = Request::post('lang_code');
        
        try {
            $this->translationModel->clearCache($langCode);
            $this->languageModel->clearCache();
            
            Response::json([
                'success' => true,
                'message' => trans('translations.cache_cleared')
            ]);
        } catch (\Exception $e) {
            error_log("Clear translations cache error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * الحصول على قائمة العملات
     * 
     * @return array
     */
    private function getCurrencies(): array
    {
        return [
            'SAR' => ['name' => 'ريال سعودي', 'symbol' => 'ر.س'],
            'USD' => ['name' => 'دولار أمريكي', 'symbol' => '$'],
            'EUR' => ['name' => 'يورو', 'symbol' => '€'],
            'GBP' => ['name' => 'جنيه إسترليني', 'symbol' => '£'],
            'AED' => ['name' => 'درهم إماراتي', 'symbol' => 'د.إ'],
            'EGP' => ['name' => 'جنيه مصري', 'symbol' => 'ج.م'],
            'KWD' => ['name' => 'دينار كويتي', 'symbol' => 'د.ك'],
            'QAR' => ['name' => 'ريال قطري', 'symbol' => 'ر.ق']
        ];
    }

    /**
     * الحصول على تنسيقات التاريخ
     * 
     * @return array
     */
    private function getDateFormats(): array
    {
        return [
            'Y-m-d' => date('Y-m-d'),
            'd-m-Y' => date('d-m-Y'),
            'm/d/Y' => date('m/d/Y'),
            'd/m/Y' => date('d/m/Y'),
            'Y/m/d' => date('Y/m/d')
        ];
    }

    /**
     * الحصول على تنسيقات الوقت
     * 
     * @return array
     */
    private function getTimeFormats(): array
    {
        return [
            'H:i:s' => date('H:i:s'),
            'H:i' => date('H:i'),
            'h:i A' => date('h:i A'),
            'h:i:s A' => date('h:i:s A')
        ];
    }
}
