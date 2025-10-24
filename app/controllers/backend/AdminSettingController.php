<?php
/**
 * File: AdminSettingController.php
 * Path: /app/controllers/backend/AdminSettingController.php
 * Purpose: التحكم في إعدادات النظام
 * Dependencies: Core\Controller, App\Models\Setting, App\Models\Branch
 * Phase: Phase 3 - Settings & Administration
 * Created: 2025-10-24
 */

namespace App\Controllers\Backend;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\FileTracker;
use App\Models\Setting;
use App\Models\Branch;

/**
 * Class AdminSettingController
 * 
 * التحكم في إعدادات النظام
 * - الإعدادات العامة
 * - معلومات الشركة
 * - إعدادات التأجير
 * - إعدادات الإشعارات
 * - إعدادات البريد الإلكتروني
 * - إعدادات الدفع
 * - إعدادات واتساب
 * - وضع الصيانة
 * 
 * @package App\Controllers\Backend
 */
class AdminSettingController extends Controller
{
    /**
     * نموذج الإعدادات
     */
    private Setting $settingModel;
    
    /**
     * نموذج الفروع
     */
    private Branch $branchModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        // التحقق من صلاحيات الإدارة
        $this->requireAuth();
        $this->requirePermission('manage_settings');
        
        $this->settingModel = new Setting();
        $this->branchModel = new Branch();
        
        // تسجيل الملف في FileTracker
        FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 3');
    }

    // ========================================
    // Main Settings Page
    // ========================================

    /**
     * عرض صفحة الإعدادات الرئيسية
     */
    public function index(): void
    {
        $data = [
            'title' => trans('settings.page_title'),
            'activeTab' => Request::get('tab', 'general'),
            'settings' => $this->settingModel->getAll(),
            'branches' => $this->branchModel->getAll(true)
        ];
        
        $this->view('backend/settings/index', $data);
    }

    // ========================================
    // General Settings
    // ========================================

    /**
     * عرض الإعدادات العامة
     */
    public function general(): void
    {
        $data = [
            'title' => trans('settings.general.title'),
            'settings' => $this->settingModel->getByCategory('general')
        ];
        
        $this->view('backend/settings/general', $data);
    }

    /**
     * تحديث الإعدادات العامة
     */
    public function updateGeneral(): void
    {
        // التحقق من CSRF
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $data = Request::all();
        
        // حذف التوكن من البيانات
        unset($data['csrf_token']);
        
        try {
            // تحديث الإعدادات
            $updated = $this->settingModel->setMultiple($data, 'general');
            
            if ($updated) {
                // حفظ في Audit Log
                $this->log('update_general_settings', 'settings', null, $data);
                
                Response::json([
                    'success' => true,
                    'message' => trans('settings.updated_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.update_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Update general settings error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    // ========================================
    // Company Settings
    // ========================================

    /**
     * عرض معلومات الشركة
     */
    public function company(): void
    {
        $data = [
            'title' => trans('settings.company.title'),
            'settings' => $this->settingModel->getByCategory('company')
        ];
        
        $this->view('backend/settings/company', $data);
    }

    /**
     * تحديث معلومات الشركة
     */
    public function updateCompany(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $data = Request::all();
        unset($data['csrf_token']);
        
        // معالجة رفع الشعار
        if (Request::hasFile('company_logo')) {
            $logoPath = $this->uploadLogo('company_logo');
            if ($logoPath) {
                $data['company_logo'] = $logoPath;
            }
        }
        
        // معالجة رفع الأيقونة
        if (Request::hasFile('company_favicon')) {
            $faviconPath = $this->uploadLogo('company_favicon');
            if ($faviconPath) {
                $data['company_favicon'] = $faviconPath;
            }
        }
        
        try {
            $updated = $this->settingModel->setMultiple($data, 'company');
            
            if ($updated) {
                $this->log('update_company_info', 'settings', null, $data);
                
                Response::json([
                    'success' => true,
                    'message' => trans('settings.company.updated_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.update_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Update company settings error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * رفع الشعار أو الأيقونة
     * 
     * @param string $fieldName اسم الحقل
     * @return string|null مسار الملف
     */
    private function uploadLogo(string $fieldName): ?string
    {
        $upload = $this->upload($fieldName, [
            'allowed_types' => ['jpg', 'jpeg', 'png', 'svg', 'ico'],
            'max_size' => 2048, // 2MB
            'path' => 'uploads/company/'
        ]);
        
        return $upload['success'] ? $upload['file_path'] : null;
    }

    // ========================================
    // Rental Settings
    // ========================================

    /**
     * عرض إعدادات التأجير
     */
    public function rental(): void
    {
        $data = [
            'title' => trans('settings.rental.title'),
            'settings' => $this->settingModel->getByCategory('rental')
        ];
        
        $this->view('backend/settings/rental', $data);
    }

    /**
     * تحديث إعدادات التأجير
     */
    public function updateRental(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $data = Request::all();
        unset($data['csrf_token']);
        
        // التحقق من صحة البيانات
        $errors = $this->validateRentalSettings($data);
        
        if (!empty($errors)) {
            Response::json([
                'success' => false,
                'message' => trans('error.validation_failed'),
                'errors' => $errors
            ], 422);
            return;
        }
        
        try {
            $updated = $this->settingModel->setMultiple($data, 'rental');
            
            if ($updated) {
                $this->log('update_rental_settings', 'settings', null, $data);
                
                Response::json([
                    'success' => true,
                    'message' => trans('settings.rental.updated_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.update_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Update rental settings error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * التحقق من صحة إعدادات التأجير
     * 
     * @param array $data البيانات
     * @return array الأخطاء
     */
    private function validateRentalSettings(array $data): array
    {
        $errors = [];
        
        // نسبة العربون
        if (isset($data['rental_deposit_percentage'])) {
            $deposit = floatval($data['rental_deposit_percentage']);
            if ($deposit < 0 || $deposit > 100) {
                $errors['rental_deposit_percentage'] = trans('settings.rental.error.invalid_deposit');
            }
        }
        
        // الحد الأدنى لأيام التأجير
        if (isset($data['rental_min_days'])) {
            $minDays = intval($data['rental_min_days']);
            if ($minDays < 1) {
                $errors['rental_min_days'] = trans('settings.rental.error.invalid_min_days');
            }
        }
        
        // رسوم التأخير
        if (isset($data['rental_late_fee_per_day'])) {
            $lateFee = floatval($data['rental_late_fee_per_day']);
            if ($lateFee < 0) {
                $errors['rental_late_fee_per_day'] = trans('settings.rental.error.invalid_late_fee');
            }
        }
        
        return $errors;
    }

    // ========================================
    // Notification Settings
    // ========================================

    /**
     * عرض إعدادات الإشعارات
     */
    public function notifications(): void
    {
        $data = [
            'title' => trans('settings.notifications.title'),
            'settings' => $this->settingModel->getByCategory('notifications')
        ];
        
        $this->view('backend/settings/notifications', $data);
    }

    /**
     * تحديث إعدادات الإشعارات
     */
    public function updateNotifications(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $data = Request::all();
        unset($data['csrf_token']);
        
        try {
            $updated = $this->settingModel->setMultiple($data, 'notifications');
            
            if ($updated) {
                $this->log('update_notification_settings', 'settings', null, $data);
                
                Response::json([
                    'success' => true,
                    'message' => trans('settings.notifications.updated_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.update_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Update notification settings error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    // ========================================
    // Mail Settings
    // ========================================

    /**
     * عرض إعدادات البريد الإلكتروني
     */
    public function mail(): void
    {
        $data = [
            'title' => trans('settings.mail.title'),
            'settings' => $this->settingModel->getByCategory('mail')
        ];
        
        $this->view('backend/settings/mail', $data);
    }

    /**
     * تحديث إعدادات البريد الإلكتروني
     */
    public function updateMail(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $data = Request::all();
        unset($data['csrf_token']);
        
        try {
            $updated = $this->settingModel->setMultiple($data, 'mail');
            
            if ($updated) {
                $this->log('update_mail_settings', 'settings', null, $data);
                
                Response::json([
                    'success' => true,
                    'message' => trans('settings.mail.updated_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.update_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Update mail settings error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * اختبار اتصال البريد الإلكتروني
     */
    public function testMail(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $testEmail = Request::post('test_email');
        
        if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            Response::json([
                'success' => false,
                'message' => trans('settings.mail.error.invalid_email')
            ], 422);
            return;
        }
        
        try {
            // إرسال بريد تجريبي
            $sent = $this->sendTestEmail($testEmail);
            
            if ($sent) {
                Response::json([
                    'success' => true,
                    'message' => trans('settings.mail.test_sent_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('settings.mail.test_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Test mail error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error'),
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إرسال بريد تجريبي
     * 
     * @param string $email البريد الإلكتروني
     * @return bool
     */
    private function sendTestEmail(string $email): bool
    {
        // TODO: استخدام EmailService لإرسال البريد
        // هذه دالة placeholder، سيتم تنفيذها في مرحلة Services
        return true;
    }

    // ========================================
    // Payment Settings
    // ========================================

    /**
     * عرض إعدادات الدفع
     */
    public function payment(): void
    {
        $data = [
            'title' => trans('settings.payment.title'),
            'settings' => $this->settingModel->getByCategory('payment')
        ];
        
        $this->view('backend/settings/payment', $data);
    }

    /**
     * تحديث إعدادات الدفع
     */
    public function updatePayment(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $data = Request::all();
        unset($data['csrf_token']);
        
        try {
            $updated = $this->settingModel->setMultiple($data, 'payment');
            
            if ($updated) {
                $this->log('update_payment_settings', 'settings', null, $data);
                
                Response::json([
                    'success' => true,
                    'message' => trans('settings.payment.updated_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.update_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Update payment settings error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    // ========================================
    // WhatsApp Settings
    // ========================================

    /**
     * عرض إعدادات واتساب
     */
    public function whatsapp(): void
    {
        $data = [
            'title' => trans('settings.whatsapp.title'),
            'settings' => $this->settingModel->getByCategory('whatsapp')
        ];
        
        $this->view('backend/settings/whatsapp', $data);
    }

    /**
     * تحديث إعدادات واتساب
     */
    public function updateWhatsapp(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $data = Request::all();
        unset($data['csrf_token']);
        
        try {
            $updated = $this->settingModel->setMultiple($data, 'whatsapp');
            
            if ($updated) {
                $this->log('update_whatsapp_settings', 'settings', null, $data);
                
                Response::json([
                    'success' => true,
                    'message' => trans('settings.whatsapp.updated_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.update_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Update whatsapp settings error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * اختبار اتصال واتساب
     */
    public function testWhatsapp(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $testPhone = Request::post('test_phone');
        
        if (empty($testPhone)) {
            Response::json([
                'success' => false,
                'message' => trans('settings.whatsapp.error.invalid_phone')
            ], 422);
            return;
        }
        
        try {
            // إرسال رسالة تجريبية
            $sent = $this->sendTestWhatsapp($testPhone);
            
            if ($sent) {
                Response::json([
                    'success' => true,
                    'message' => trans('settings.whatsapp.test_sent_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('settings.whatsapp.test_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Test whatsapp error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error'),
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إرسال رسالة واتساب تجريبية
     * 
     * @param string $phone رقم الهاتف
     * @return bool
     */
    private function sendTestWhatsapp(string $phone): bool
    {
        // TODO: استخدام WhatsAppService لإرسال الرسالة
        // هذه دالة placeholder، سيتم تنفيذها في مرحلة Services
        return true;
    }

    // ========================================
    // Maintenance Mode
    // ========================================

    /**
     * عرض إعدادات وضع الصيانة
     */
    public function maintenance(): void
    {
        $data = [
            'title' => trans('settings.maintenance.title'),
            'settings' => $this->settingModel->getByCategory('maintenance')
        ];
        
        $this->view('backend/settings/maintenance', $data);
    }

    /**
     * تحديث إعدادات وضع الصيانة
     */
    public function updateMaintenance(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $data = Request::all();
        unset($data['csrf_token']);
        
        try {
            $updated = $this->settingModel->setMultiple($data, 'maintenance');
            
            if ($updated) {
                $this->log('update_maintenance_settings', 'settings', null, $data);
                
                Response::json([
                    'success' => true,
                    'message' => trans('settings.maintenance.updated_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.update_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Update maintenance settings error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * تفعيل وضع الصيانة
     */
    public function enableMaintenance(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        try {
            $updated = $this->settingModel->set('maintenance_mode', true, 'boolean', 'maintenance');
            
            if ($updated) {
                $this->log('enable_maintenance_mode', 'settings', null);
                
                Response::json([
                    'success' => true,
                    'message' => trans('settings.maintenance.enabled_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.update_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Enable maintenance error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    /**
     * تعطيل وضع الصيانة
     */
    public function disableMaintenance(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        try {
            $updated = $this->settingModel->set('maintenance_mode', false, 'boolean', 'maintenance');
            
            if ($updated) {
                $this->log('disable_maintenance_mode', 'settings', null);
                
                Response::json([
                    'success' => true,
                    'message' => trans('settings.maintenance.disabled_successfully')
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => trans('error.update_failed')
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("Disable maintenance error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }

    // ========================================
    // Cache Management
    // ========================================

    /**
     * مسح cache الإعدادات
     */
    public function clearCache(): void
    {
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        try {
            $this->settingModel->clearCache();
            
            Response::json([
                'success' => true,
                'message' => trans('settings.cache_cleared_successfully')
            ]);
        } catch (\Exception $e) {
            error_log("Clear cache error: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('error.server_error')
            ], 500);
        }
    }
}
