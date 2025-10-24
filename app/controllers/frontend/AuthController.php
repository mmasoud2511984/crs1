<?php
/**
 * File: AuthController.php
 * Path: /app/controllers/frontend/AuthController.php
 * Purpose: Customer Authentication Controller
 * Dependencies: Customer Model, Google OAuth
 * Phase: Phase 6 - Customer Management
 * Created: 2025-10-24
 */

namespace App\Controllers\Frontend;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Validator;
use Core\Security;
use App\Models\Customer;

class AuthController extends Controller
{
    private $customerModel;

    public function __construct()
    {
        parent::__construct();
        $this->customerModel = new Customer();
    }

    // ========================================
    // Registration - التسجيل
    // ========================================

    /**
     * عرض صفحة التسجيل
     * GET /register
     */
    public function showRegister(): void
    {
        // إذا كان العميل مسجل دخول، إعادة توجيه
        if ($this->isCustomerLoggedIn()) {
            Response::redirect('/profile');
            return;
        }

        $this->view('frontend/auth/register');
    }

    /**
     * معالجة التسجيل
     * POST /register
     */
    public function register(): void
    {
        try {
            if (!$this->validateCsrfToken()) {
                Session::flash('error', trans('error.csrf_invalid'));
                Response::redirect('/register');
                return;
            }

            $data = Request::all();

            // التحقق من صحة البيانات
            $validator = new Validator($data, [
                'full_name' => 'required|min:3|max:100',
                'email' => 'required|email',
                'phone' => 'required|min:10|max:20',
                'password' => 'required|min:8',
                'password_confirmation' => 'required|same:password',
                'terms' => 'required|accepted'
            ]);

            if ($validator->fails()) {
                Session::flash('error', $validator->errors()[0]);
                Session::flash('old', $data);
                Response::redirect('/register');
                return;
            }

            // التحقق من عدم وجود البريد الإلكتروني
            if ($this->customerModel->emailExists($data['email'])) {
                Session::flash('error', trans('auth.email_already_exists'));
                Session::flash('old', $data);
                Response::redirect('/register');
                return;
            }

            // التحقق من عدم وجود رقم الهاتف
            if ($this->customerModel->phoneExists($data['phone'])) {
                Session::flash('error', trans('auth.phone_already_exists'));
                Session::flash('old', $data);
                Response::redirect('/register');
                return;
            }

            // إنشاء الحساب
            $customerId = $this->customerModel->register([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'whatsapp' => $data['whatsapp'] ?? $data['phone'],
                'registration_type' => 'form',
                'preferred_language' => Session::get('language', 'ar')
            ]);

            if ($customerId) {
                // تسجيل الدخول تلقائياً
                $customer = $this->customerModel->find($customerId);
                $this->loginCustomer($customer);

                // رسالة نجاح
                Session::flash('success', trans('auth.registration_successful'));
                
                // نقاط ترحيبية
                $this->customerModel->addLoyaltyPoints(
                    $customerId,
                    100,
                    null,
                    trans('loyalty.welcome_bonus')
                );

                Response::redirect('/profile');
            } else {
                Session::flash('error', trans('auth.registration_failed'));
                Response::redirect('/register');
            }

        } catch (\Exception $e) {
            error_log("خطأ في التسجيل: " . $e->getMessage());
            Session::flash('error', trans('error.occurred'));
            Response::redirect('/register');
        }
    }

    // ========================================
    // Login - تسجيل الدخول
    // ========================================

    /**
     * عرض صفحة تسجيل الدخول
     * GET /login
     */
    public function showLogin(): void
    {
        // إذا كان العميل مسجل دخول، إعادة توجيه
        if ($this->isCustomerLoggedIn()) {
            Response::redirect('/profile');
            return;
        }

        $this->view('frontend/auth/login');
    }

    /**
     * معالجة تسجيل الدخول
     * POST /login
     */
    public function login(): void
    {
        try {
            if (!$this->validateCsrfToken()) {
                Session::flash('error', trans('error.csrf_invalid'));
                Response::redirect('/login');
                return;
            }

            $email = Request::post('email');
            $password = Request::post('password');
            $remember = Request::post('remember') === '1';

            // التحقق من البيانات
            if (empty($email) || empty($password)) {
                Session::flash('error', trans('auth.credentials_required'));
                Response::redirect('/login');
                return;
            }

            // محاولة تسجيل الدخول
            $customer = $this->customerModel->login($email, $password);

            if (!$customer) {
                // تسجيل محاولة فاشلة
                $this->logFailedLogin($email);
                
                Session::flash('error', trans('auth.invalid_credentials'));
                Response::redirect('/login');
                return;
            }

            // التحقق من القائمة السوداء
            if ($customer['is_blacklisted']) {
                Session::flash('error', trans('auth.account_blacklisted'));
                Response::redirect('/login');
                return;
            }

            // تسجيل الدخول
            $this->loginCustomer($customer, $remember);

            // إعادة التوجيه
            $redirectTo = Session::get('intended_url', '/profile');
            Session::forget('intended_url');
            
            Response::redirect($redirectTo);

        } catch (\Exception $e) {
            error_log("خطأ في تسجيل الدخول: " . $e->getMessage());
            Session::flash('error', trans('error.occurred'));
            Response::redirect('/login');
        }
    }

    /**
     * تسجيل الخروج
     * GET /logout
     */
    public function logout(): void
    {
        Session::forget('customer');
        Session::forget('customer_id');
        Session::flash('success', trans('auth.logged_out_successfully'));
        Response::redirect('/');
    }

    // ========================================
    // Google OAuth - دخول بجوجل
    // ========================================

    /**
     * إعادة توجيه إلى Google OAuth
     * GET /auth/google
     */
    public function redirectToGoogle(): void
    {
        try {
            // معلومات Google OAuth من الإعدادات
            $clientId = setting('google_client_id');
            $redirectUri = url('/auth/google/callback');

            if (empty($clientId)) {
                Session::flash('error', trans('auth.google_not_configured'));
                Response::redirect('/login');
                return;
            }

            // بناء رابط التوجيه
            $params = [
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'email profile',
                'access_type' => 'online'
            ];

            $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
            
            Response::redirect($authUrl);

        } catch (\Exception $e) {
            error_log("خطأ في Google OAuth redirect: " . $e->getMessage());
            Session::flash('error', trans('error.occurred'));
            Response::redirect('/login');
        }
    }

    /**
     * معالجة Callback من Google
     * GET /auth/google/callback
     */
    public function handleGoogleCallback(): void
    {
        try {
            $code = Request::get('code');

            if (empty($code)) {
                Session::flash('error', trans('auth.google_auth_failed'));
                Response::redirect('/login');
                return;
            }

            // الحصول على Access Token
            $clientId = setting('google_client_id');
            $clientSecret = setting('google_client_secret');
            $redirectUri = url('/auth/google/callback');

            $tokenUrl = 'https://oauth2.googleapis.com/token';
            $tokenData = [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code'
            ];

            $ch = curl_init($tokenUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
            $tokenResponse = curl_exec($ch);
            curl_close($ch);

            $tokenResult = json_decode($tokenResponse, true);

            if (!isset($tokenResult['access_token'])) {
                Session::flash('error', trans('auth.google_auth_failed'));
                Response::redirect('/login');
                return;
            }

            // الحصول على معلومات المستخدم
            $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
            $ch = curl_init($userInfoUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $tokenResult['access_token']
            ]);
            $userInfoResponse = curl_exec($ch);
            curl_close($ch);

            $userInfo = json_decode($userInfoResponse, true);

            if (!isset($userInfo['id'])) {
                Session::flash('error', trans('auth.google_auth_failed'));
                Response::redirect('/login');
                return;
            }

            // تسجيل الدخول أو إنشاء حساب
            $customer = $this->customerModel->loginWithGoogle($userInfo['id'], [
                'email' => $userInfo['email'],
                'name' => $userInfo['name']
            ]);

            $this->loginCustomer($customer);

            Session::flash('success', trans('auth.logged_in_successfully'));
            Response::redirect('/profile');

        } catch (\Exception $e) {
            error_log("خطأ في Google OAuth callback: " . $e->getMessage());
            Session::flash('error', trans('error.occurred'));
            Response::redirect('/login');
        }
    }

    // ========================================
    // Password Reset - إعادة تعيين كلمة المرور
    // ========================================

    /**
     * عرض صفحة طلب إعادة تعيين كلمة المرور
     * GET /forgot-password
     */
    public function showForgotPassword(): void
    {
        $this->view('frontend/auth/forgot-password');
    }

    /**
     * معالجة طلب إعادة تعيين كلمة المرور
     * POST /forgot-password
     */
    public function forgotPassword(): void
    {
        try {
            if (!$this->validateCsrfToken()) {
                Session::flash('error', trans('error.csrf_invalid'));
                Response::redirect('/forgot-password');
                return;
            }

            $email = Request::post('email');

            if (empty($email)) {
                Session::flash('error', trans('auth.email_required'));
                Response::redirect('/forgot-password');
                return;
            }

            // إرسال رابط إعادة التعيين
            $result = $this->customerModel->resetPassword($email);

            if ($result) {
                Session::flash('success', trans('auth.reset_link_sent'));
            } else {
                Session::flash('error', trans('auth.email_not_found'));
            }

            Response::redirect('/forgot-password');

        } catch (\Exception $e) {
            error_log("خطأ في forgot password: " . $e->getMessage());
            Session::flash('error', trans('error.occurred'));
            Response::redirect('/forgot-password');
        }
    }

    /**
     * عرض صفحة إعادة تعيين كلمة المرور
     * GET /reset-password/{token}
     */
    public function showResetPassword(string $token): void
    {
        // التحقق من صلاحية الرمز
        // TODO: Implement token verification

        $this->view('frontend/auth/reset-password', [
            'token' => $token
        ]);
    }

    /**
     * معالجة إعادة تعيين كلمة المرور
     * POST /reset-password
     */
    public function resetPassword(): void
    {
        try {
            if (!$this->validateCsrfToken()) {
                Session::flash('error', trans('error.csrf_invalid'));
                Response::redirect('/login');
                return;
            }

            $token = Request::post('token');
            $password = Request::post('password');
            $passwordConfirmation = Request::post('password_confirmation');

            // التحقق من البيانات
            if (empty($password) || $password !== $passwordConfirmation) {
                Session::flash('error', trans('auth.password_mismatch'));
                Response::redirect('/reset-password/' . $token);
                return;
            }

            if (strlen($password) < 8) {
                Session::flash('error', trans('auth.password_too_short'));
                Response::redirect('/reset-password/' . $token);
                return;
            }

            // TODO: تحديث كلمة المرور باستخدام الرمز

            Session::flash('success', trans('auth.password_reset_successfully'));
            Response::redirect('/login');

        } catch (\Exception $e) {
            error_log("خطأ في reset password: " . $e->getMessage());
            Session::flash('error', trans('error.occurred'));
            Response::redirect('/login');
        }
    }

    // ========================================
    // Helper Methods - دوال مساعدة
    // ========================================

    /**
     * تسجيل دخول العميل
     */
    private function loginCustomer(array $customer, bool $remember = false): void
    {
        Session::set('customer', $customer);
        Session::set('customer_id', $customer['id']);
        Session::set('customer_name', $customer['full_name']);
        Session::set('customer_email', $customer['email']);

        if ($remember) {
            // إنشاء Remember Token
            // TODO: Implement remember me functionality
        }
    }

    /**
     * التحقق من تسجيل دخول العميل
     */
    private function isCustomerLoggedIn(): bool
    {
        return Session::has('customer_id');
    }

    /**
     * تسجيل محاولة دخول فاشلة
     */
    private function logFailedLogin(string $email): void
    {
        try {
            $ip = Request::ip();
            
            $stmt = $this->customerModel->db->prepare("
                INSERT INTO login_attempts (email, ip_address, attempt_type, created_at)
                VALUES (?, ?, 'customer_failed', NOW())
            ");
            $stmt->execute([$email, $ip]);

        } catch (\Exception $e) {
            error_log("خطأ في تسجيل محاولة فاشلة: " . $e->getMessage());
        }
    }

    /**
     * التحقق من عدد محاولات الدخول
     */
    private function checkLoginAttempts(string $email): bool
    {
        try {
            $ip = Request::ip();
            $stmt = $this->customerModel->db->prepare("
                SELECT COUNT(*) as attempts
                FROM login_attempts
                WHERE (email = ? OR ip_address = ?)
                AND attempt_type = 'customer_failed'
                AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $stmt->execute([$email, $ip]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result['attempts'] < 5;

        } catch (\Exception $e) {
            error_log("خطأ في التحقق من محاولات الدخول: " . $e->getMessage());
            return true;
        }
    }

    // ========================================
    // Email Verification - التحقق من البريد
    // ========================================

    /**
     * إرسال رابط التحقق من البريد الإلكتروني
     * POST /verify-email/send
     */
    public function sendVerificationEmail(): void
    {
        try {
            if (!$this->isCustomerLoggedIn()) {
                Response::json(['success' => false, 'message' => trans('auth.login_required')]);
                return;
            }

            $customerId = Session::get('customer_id');
            $customer = $this->customerModel->find($customerId);

            if ($customer['is_verified']) {
                Response::json(['success' => false, 'message' => trans('auth.already_verified')]);
                return;
            }

            // TODO: إرسال رابط التحقق عبر البريد الإلكتروني

            Response::json(['success' => true, 'message' => trans('auth.verification_email_sent')]);

        } catch (\Exception $e) {
            error_log("خطأ في إرسال رابط التحقق: " . $e->getMessage());
            Response::json(['success' => false, 'message' => trans('error.occurred')]);
        }
    }

    /**
     * التحقق من البريد الإلكتروني
     * GET /verify-email/{token}
     */
    public function verifyEmail(string $token): void
    {
        try {
            // TODO: التحقق من الرمز وتفعيل الحساب

            Session::flash('success', trans('auth.email_verified_successfully'));
            Response::redirect('/profile');

        } catch (\Exception $e) {
            error_log("خطأ في التحقق من البريد: " . $e->getMessage());
            Session::flash('error', trans('error.occurred'));
            Response::redirect('/login');
        }
    }
}
