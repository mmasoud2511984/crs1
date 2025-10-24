<?php
/**
 * File: AdminRentalController.php
 * Path: /app/controllers/backend/AdminRentalController.php
 * Purpose: Admin rental management controller - CRUD for rentals
 * Dependencies: Controller.php, Rental.php, RentalPayment.php, RentalExtension.php
 * Phase: Phase 7 - Rental System
 * Created: 2025-10-24
 */

namespace App\Controllers\Backend;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Auth;
use Core\Validator;
use Core\Security;
use Core\Uploader;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\RentalExtension;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Branch;
use App\Models\PaymentMethod;

class AdminRentalController extends Controller
{
    private Rental $rentalModel;
    private RentalPayment $paymentModel;
    private RentalExtension $extensionModel;
    private Car $carModel;
    private Customer $customerModel;
    private Branch $branchModel;
    private PaymentMethod $paymentMethodModel;

    public function __construct()
    {
        parent::__construct();
        
        // التحقق من تسجيل الدخول
        if (!Auth::check()) {
            Response::redirect('/admin/login');
        }
        
        // التحقق من الصلاحيات
        if (!Auth::can('view_rentals')) {
            Response::forbidden();
        }
        
        $this->rentalModel = new Rental();
        $this->paymentModel = new RentalPayment();
        $this->extensionModel = new RentalExtension();
        $this->carModel = new Car();
        $this->customerModel = new Customer();
        $this->branchModel = new Branch();
        $this->paymentMethodModel = new PaymentMethod();
    }

    // ========================================
    // قائمة الإيجارات
    // ========================================

    /**
     * عرض قائمة الإيجارات
     */
    public function index(): void
    {
        $page = Request::get('page', 1);
        $perPage = Request::get('per_page', 20);
        
        // الفلاتر
        $filters = [
            'status' => Request::get('status'),
            'payment_status' => Request::get('payment_status'),
            'branch_id' => Request::get('branch_id'),
            'customer_id' => Request::get('customer_id'),
            'car_id' => Request::get('car_id'),
            'date_from' => Request::get('date_from'),
            'date_to' => Request::get('date_to'),
            'search' => Request::get('search')
        ];
        
        // إزالة الفلاتر الفارغة
        $filters = array_filter($filters);
        
        // الحصول على البيانات
        $result = $this->rentalModel->getAllWithDetails($filters, $page, $perPage);
        
        // البيانات الإضافية للفلاتر
        $branches = $this->branchModel->all();
        $stats = $this->rentalModel->getRentalStats($filters);
        
        $data = [
            'title' => trans('rental.list.title'),
            'rentals' => $result['data'],
            'pagination' => [
                'current_page' => $result['page'],
                'total_pages' => $result['totalPages'],
                'per_page' => $result['perPage'],
                'total' => $result['total']
            ],
            'filters' => $filters,
            'branches' => $branches,
            'stats' => $stats
        ];
        
        $this->view('backend/rentals/index', $data);
    }

    // ========================================
    // عرض التفاصيل
    // ========================================

    /**
     * عرض تفاصيل إيجار محدد
     * 
     * @param int $id معرف الإيجار
     */
    public function show(int $id): void
    {
        $rental = $this->rentalModel->getWithFullDetails($id);
        
        if (!$rental) {
            Response::notFound(trans('rental.not_found'));
        }
        
        // الحصول على الدفعات
        $payments = $this->paymentModel->getByRentalId($id);
        
        // الحصول على التمديدات
        $extensions = $this->extensionModel->getByRentalId($id);
        
        $data = [
            'title' => trans('rental.view.title'),
            'rental' => $rental,
            'payments' => $payments,
            'extensions' => $extensions
        ];
        
        $this->view('backend/rentals/show', $data);
    }

    // ========================================
    // إنشاء إيجار جديد
    // ========================================

    /**
     * عرض صفحة إنشاء إيجار جديد
     */
    public function create(): void
    {
        if (!Auth::can('create_rentals')) {
            Response::forbidden();
        }
        
        // الحصول على البيانات اللازمة
        $customers = $this->customerModel->getActive();
        $cars = $this->carModel->getAvailableCars();
        $branches = $this->branchModel->getActive();
        $paymentMethods = $this->paymentMethodModel->getActive();
        
        $data = [
            'title' => trans('rental.create.title'),
            'customers' => $customers,
            'cars' => $cars,
            'branches' => $branches,
            'paymentMethods' => $paymentMethods,
            'settings' => [
                'deposit_percentage' => setting('rental_deposit_percentage', 20),
                'min_days' => setting('rental_min_days', 1),
                'max_days' => setting('rental_max_days', 90)
            ]
        ];
        
        $this->view('backend/rentals/create', $data);
    }

    /**
     * حفظ إيجار جديد
     */
    public function store(): void
    {
        if (!Auth::can('create_rentals')) {
            Response::json(['success' => false, 'message' => trans('error.no_permission')], 403);
            return;
        }
        
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $data = Request::all();
        
        // التحقق من صحة البيانات
        $errors = $this->validateRentalData($data);
        
        if (!empty($errors)) {
            Response::json([
                'success' => false,
                'message' => trans('error.validation_failed'),
                'errors' => $errors
            ], 422);
            return;
        }
        
        // إضافة بيانات المستخدم
        $data['created_by'] = Auth::id();
        $data['branch_id'] = Auth::user()['branch_id'] ?? null;
        
        // إنشاء الإيجار
        $rentalId = $this->rentalModel->createRental($data);
        
        if ($rentalId) {
            // إضافة الدفعة الأولى إذا وُجدت
            if (!empty($data['initial_payment']) && $data['initial_payment'] > 0) {
                $this->paymentModel->addPayment([
                    'rental_id' => $rentalId,
                    'payment_method_id' => $data['payment_method_id'] ?? null,
                    'amount' => $data['initial_payment'],
                    'payment_type' => 'rental',
                    'payment_date' => date('Y-m-d H:i:s'),
                    'notes' => trans('rental.initial_payment'),
                    'created_by' => Auth::id()
                ]);
            }
            
            // إضافة دفعة العربون إذا وُجدت
            if (!empty($data['deposit_payment']) && $data['deposit_payment'] > 0) {
                $this->paymentModel->addPayment([
                    'rental_id' => $rentalId,
                    'payment_method_id' => $data['payment_method_id'] ?? null,
                    'amount' => $data['deposit_payment'],
                    'payment_type' => 'deposit',
                    'payment_date' => date('Y-m-d H:i:s'),
                    'notes' => trans('rental.deposit_payment'),
                    'created_by' => Auth::id()
                ]);
            }
            
            $this->log('create_rental', 'rentals', $rentalId, $data);
            
            Response::json([
                'success' => true,
                'message' => trans('rental.created_successfully'),
                'data' => ['id' => $rentalId]
            ]);
        } else {
            Response::json([
                'success' => false,
                'message' => trans('error.create_failed')
            ], 500);
        }
    }

    // ========================================
    // تعديل إيجار
    // ========================================

    /**
     * عرض صفحة تعديل إيجار
     * 
     * @param int $id معرف الإيجار
     */
    public function edit(int $id): void
    {
        if (!Auth::can('edit_rentals')) {
            Response::forbidden();
        }
        
        $rental = $this->rentalModel->getWithFullDetails($id);
        
        if (!$rental) {
            Response::notFound(trans('rental.not_found'));
        }
        
        // لا يمكن تعديل الإيجارات المكتملة أو الملغاة
        if (in_array($rental['status'], ['completed', 'cancelled'])) {
            Response::forbidden(trans('rental.cannot_edit'));
        }
        
        $customers = $this->customerModel->getActive();
        $cars = $this->carModel->getAvailableCars();
        $branches = $this->branchModel->getActive();
        
        $data = [
            'title' => trans('rental.edit.title'),
            'rental' => $rental,
            'customers' => $customers,
            'cars' => $cars,
            'branches' => $branches
        ];
        
        $this->view('backend/rentals/edit', $data);
    }

    /**
     * تحديث إيجار
     * 
     * @param int $id معرف الإيجار
     */
    public function update(int $id): void
    {
        if (!Auth::can('edit_rentals')) {
            Response::json(['success' => false, 'message' => trans('error.no_permission')], 403);
            return;
        }
        
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $rental = $this->rentalModel->find($id);
        
        if (!$rental) {
            Response::json(['success' => false, 'message' => trans('rental.not_found')], 404);
            return;
        }
        
        // لا يمكن تعديل الإيجارات المكتملة أو الملغاة
        if (in_array($rental['status'], ['completed', 'cancelled'])) {
            Response::json(['success' => false, 'message' => trans('rental.cannot_edit')], 403);
            return;
        }
        
        $data = Request::all();
        
        // التحقق من صحة البيانات
        $errors = $this->validateRentalData($data, $id);
        
        if (!empty($errors)) {
            Response::json([
                'success' => false,
                'message' => trans('error.validation_failed'),
                'errors' => $errors
            ], 422);
            return;
        }
        
        // تحديث الإيجار
        $updated = $this->rentalModel->updateRental($id, $data);
        
        if ($updated) {
            $this->log('update_rental', 'rentals', $id, $data);
            
            Response::json([
                'success' => true,
                'message' => trans('rental.updated_successfully')
            ]);
        } else {
            Response::json([
                'success' => false,
                'message' => trans('error.update_failed')
            ], 500);
        }
    }

    // ========================================
    // حذف إيجار
    // ========================================

    /**
     * حذف إيجار
     * 
     * @param int $id معرف الإيجار
     */
    public function delete(int $id): void
    {
        if (!Auth::can('delete_rentals')) {
            Response::json(['success' => false, 'message' => trans('error.no_permission')], 403);
            return;
        }
        
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $rental = $this->rentalModel->find($id);
        
        if (!$rental) {
            Response::json(['success' => false, 'message' => trans('rental.not_found')], 404);
            return;
        }
        
        // لا يمكن حذف الإيجارات النشطة
        if ($rental['status'] === 'active') {
            Response::json(['success' => false, 'message' => trans('rental.cannot_delete_active')], 403);
            return;
        }
        
        $deleted = $this->rentalModel->delete($id);
        
        if ($deleted) {
            $this->log('delete_rental', 'rentals', $id, $rental);
            
            Response::json([
                'success' => true,
                'message' => trans('rental.deleted_successfully')
            ]);
        } else {
            Response::json([
                'success' => false,
                'message' => trans('error.delete_failed')
            ], 500);
        }
    }

    // ========================================
    // التحقق من صحة البيانات
    // ========================================

    /**
     * التحقق من صحة بيانات الإيجار
     * 
     * @param array $data البيانات
     * @param int|null $id معرف الإيجار (للتعديل)
     * @return array الأخطاء
     */
    private function validateRentalData(array $data, ?int $id = null): array
    {
        $errors = [];
        
        // العميل
        if (empty($data['customer_id'])) {
            $errors['customer_id'] = trans('rental.error.customer_required');
        }
        
        // السيارة
        if (empty($data['car_id'])) {
            $errors['car_id'] = trans('rental.error.car_required');
        } else {
            // التحقق من توفر السيارة
            $isAvailable = $this->carModel->isAvailableForRental(
                $data['car_id'],
                $data['start_date'],
                $data['end_date'],
                $id
            );
            
            if (!$isAvailable) {
                $errors['car_id'] = trans('rental.error.car_not_available');
            }
        }
        
        // التواريخ
        if (empty($data['start_date'])) {
            $errors['start_date'] = trans('rental.error.start_date_required');
        }
        
        if (empty($data['end_date'])) {
            $errors['end_date'] = trans('rental.error.end_date_required');
        }
        
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            $start = new \DateTime($data['start_date']);
            $end = new \DateTime($data['end_date']);
            
            if ($end < $start) {
                $errors['end_date'] = trans('rental.error.end_date_before_start');
            }
            
            $days = $start->diff($end)->days + 1;
            $minDays = intval(setting('rental_min_days', 1));
            $maxDays = intval(setting('rental_max_days', 90));
            
            if ($days < $minDays) {
                $errors['end_date'] = trans('rental.error.min_days', ['days' => $minDays]);
            }
            
            if ($days > $maxDays) {
                $errors['end_date'] = trans('rental.error.max_days', ['days' => $maxDays]);
            }
        }
        
        // السعر اليومي
        if (empty($data['daily_rate']) || $data['daily_rate'] <= 0) {
            $errors['daily_rate'] = trans('rental.error.daily_rate_required');
        }
        
        // بيانات السائق
        if (!empty($data['with_driver'])) {
            if (empty($data['driver_name'])) {
                $errors['driver_name'] = trans('rental.error.driver_name_required');
            }
            
            if (empty($data['driver_phone'])) {
                $errors['driver_phone'] = trans('rental.error.driver_phone_required');
            }
            
            if (empty($data['driver_daily_rate']) || $data['driver_daily_rate'] <= 0) {
                $errors['driver_daily_rate'] = trans('rental.error.driver_rate_required');
            }
        }
        
        return $errors;
    }
}

// تسجيل الملف
use Core\FileTracker;
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 7');
    // ========================================

    /**
     * تأكيد الإيجار
     * 
     * @param int $id معرف الإيجار
     */
    public function confirm(int $id): void
    {
        if (!Auth::can('manage_rentals')) {
            Response::json(['success' => false, 'message' => trans('error.no_permission')], 403);
            return;
        }
        
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $rental = $this->rentalModel->find($id);
        
        if (!$rental) {
            Response::json(['success' => false, 'message' => trans('rental.not_found')], 404);
            return;
        }
        
        if ($rental['status'] !== 'pending') {
            Response::json(['success' => false, 'message' => trans('rental.cannot_confirm')], 403);
            return;
        }
        
        $confirmed = $this->rentalModel->confirmRental($id, Auth::id());
        
        if ($confirmed) {
            $this->log('confirm_rental', 'rentals', $id);
            
            Response::json([
                'success' => true,
                'message' => trans('rental.confirmed_successfully')
            ]);
        } else {
            Response::json([
                'success' => false,
                'message' => trans('error.update_failed')
            ], 500);
        }
    }

    /**
     * تنشيط الإيجار (بدء الإيجار)
     * 
     * @param int $id معرف الإيجار
     */
    public function activate(int $id): void
    {
        if (!Auth::can('manage_rentals')) {
            Response::json(['success' => false, 'message' => trans('error.no_permission')], 403);
            return;
        }
        
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $rental = $this->rentalModel->find($id);
        
        if (!$rental) {
            Response::json(['success' => false, 'message' => trans('rental.not_found')], 404);
            return;
        }
        
        if ($rental['status'] !== 'confirmed') {
            Response::json(['success' => false, 'message' => trans('rental.cannot_activate')], 403);
            return;
        }
        
        $data = Request::all();
        
        // التحقق من صحة البيانات
        $errors = [];
        
        if (empty($data['odometer_start'])) {
            $errors['odometer_start'] = trans('rental.error.odometer_required');
        }
        
        if (empty($data['fuel_level_start'])) {
            $errors['fuel_level_start'] = trans('rental.error.fuel_level_required');
        }
        
        if (!empty($errors)) {
            Response::json([
                'success' => false,
                'message' => trans('error.validation_failed'),
                'errors' => $errors
            ], 422);
            return;
        }
        
        $activated = $this->rentalModel->activateRental($id, $data);
        
        if ($activated) {
            $this->log('activate_rental', 'rentals', $id, $data);
            
            Response::json([
                'success' => true,
                'message' => trans('rental.activated_successfully')
            ]);
        } else {
            Response::json([
                'success' => false,
                'message' => trans('error.update_failed')
            ], 500);
        }
    }

    /**
     * إنهاء الإيجار (استلام السيارة)
     * 
     * @param int $id معرف الإيجار
     */
    public function complete(int $id): void
    {
        if (!Auth::can('manage_rentals')) {
            Response::json(['success' => false, 'message' => trans('error.no_permission')], 403);
            return;
        }
        
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $rental = $this->rentalModel->find($id);
        
        if (!$rental) {
            Response::json(['success' => false, 'message' => trans('rental.not_found')], 404);
            return;
        }
        
        if (!in_array($rental['status'], ['active', 'extended'])) {
            Response::json(['success' => false, 'message' => trans('rental.cannot_complete')], 403);
            return;
        }
        
        $data = Request::all();
        
        // التحقق من صحة البيانات
        $errors = [];
        
        if (empty($data['odometer_end'])) {
            $errors['odometer_end'] = trans('rental.error.odometer_required');
        }
        
        if (empty($data['fuel_level_end'])) {
            $errors['fuel_level_end'] = trans('rental.error.fuel_level_required');
        }
        
        if (!empty($data['odometer_end']) && !empty($rental['odometer_start'])) {
            if ($data['odometer_end'] < $rental['odometer_start']) {
                $errors['odometer_end'] = trans('rental.error.odometer_invalid');
            }
        }
        
        if (!empty($errors)) {
            Response::json([
                'success' => false,
                'message' => trans('error.validation_failed'),
                'errors' => $errors
            ], 422);
            return;
        }
        
        $data['actual_return_date'] = date('Y-m-d H:i:s');
        
        $completed = $this->rentalModel->completeRental($id, $data, Auth::id());
        
        if ($completed) {
            $this->log('complete_rental', 'rentals', $id, $data);
            
            Response::json([
                'success' => true,
                'message' => trans('rental.completed_successfully')
            ]);
        } else {
            Response::json([
                'success' => false,
                'message' => trans('error.update_failed')
            ], 500);
        }
    }

    /**
     * إلغاء الإيجار
     * 
     * @param int $id معرف الإيجار
     */
    public function cancel(int $id): void
    {
        if (!Auth::can('manage_rentals')) {
            Response::json(['success' => false, 'message' => trans('error.no_permission')], 403);
            return;
        }
        
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $rental = $this->rentalModel->find($id);
        
        if (!$rental) {
            Response::json(['success' => false, 'message' => trans('rental.not_found')], 404);
            return;
        }
        
        if (in_array($rental['status'], ['completed', 'cancelled'])) {
            Response::json(['success' => false, 'message' => trans('rental.cannot_cancel')], 403);
            return;
        }
        
        $reason = Request::post('cancellation_reason', '');
        
        if (empty($reason)) {
            Response::json([
                'success' => false,
                'message' => trans('rental.error.cancellation_reason_required')
            ], 422);
            return;
        }
        
        $cancelled = $this->rentalModel->cancelRental($id, $reason);
        
        if ($cancelled) {
            $this->log('cancel_rental', 'rentals', $id, ['reason' => $reason]);
            
            Response::json([
                'success' => true,
                'message' => trans('rental.cancelled_successfully')
            ]);
        } else {
            Response::json([
                'success' => false,
                'message' => trans('error.update_failed')
            ], 500);
        }
    }

    // ========================================
    // إدارة الدفعات
    // ========================================

    /**
     * إضافة دفعة
     * 
     * @param int $rentalId معرف الإيجار
     */
    public function addPayment(int $rentalId): void
    {
        if (!Auth::can('manage_payments')) {
            Response::json(['success' => false, 'message' => trans('error.no_permission')], 403);
            return;
        }
        
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $rental = $this->rentalModel->find($rentalId);
        
        if (!$rental) {
            Response::json(['success' => false, 'message' => trans('rental.not_found')], 404);
            return;
        }
        
        $data = Request::all();
        
        // التحقق من صحة البيانات
        $errors = [];
        
        if (empty($data['amount']) || $data['amount'] <= 0) {
            $errors['amount'] = trans('rental.error.amount_required');
        }
        
        if (empty($data['payment_type'])) {
            $errors['payment_type'] = trans('rental.error.payment_type_required');
        }
        
        if (empty($data['payment_date'])) {
            $errors['payment_date'] = trans('rental.error.payment_date_required');
        }
        
        if (!empty($errors)) {
            Response::json([
                'success' => false,
                'message' => trans('error.validation_failed'),
                'errors' => $errors
            ], 422);
            return;
        }
        
        // رفع الإيصال إن وُجد
        if (!empty($_FILES['receipt']['name'])) {
            $upload = Uploader::upload($_FILES['receipt'], 'receipts', [
                'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf'],
                'max_size' => 5 * 1024 * 1024
            ]);
            
            if ($upload['success']) {
                $data['receipt_path'] = $upload['file_path'];
            }
        }
        
        $data['rental_id'] = $rentalId;
        $data['created_by'] = Auth::id();
        
        $paymentId = $this->paymentModel->addPayment($data);
        
        if ($paymentId) {
            $this->log('add_payment', 'rental_payments', $paymentId, $data);
            
            Response::json([
                'success' => true,
                'message' => trans('rental.payment_added_successfully')
            ]);
        } else {
            Response::json([
                'success' => false,
                'message' => trans('error.create_failed')
            ], 500);
        }
    }

    /**
     * حذف دفعة
     * 
     * @param int $paymentId معرف الدفعة
     */
    public function deletePayment(int $paymentId): void
    {
        if (!Auth::can('manage_payments')) {
            Response::json(['success' => false, 'message' => trans('error.no_permission')], 403);
            return;
        }
        
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $payment = $this->paymentModel->find($paymentId);
        
        if (!$payment) {
            Response::json(['success' => false, 'message' => trans('rental.payment_not_found')], 404);
            return;
        }
        
        $deleted = $this->paymentModel->deletePayment($paymentId);
        
        if ($deleted) {
            $this->log('delete_payment', 'rental_payments', $paymentId, $payment);
            
            Response::json([
                'success' => true,
                'message' => trans('rental.payment_deleted_successfully')
            ]);
        } else {
            Response::json([
                'success' => false,
                'message' => trans('error.delete_failed')
            ], 500);
        }
    }

    // ========================================
    // إدارة التمديدات
    // ========================================

    /**
     * إنشاء تمديد
     * 
     * @param int $rentalId معرف الإيجار
     */
    public function extend(int $rentalId): void
    {
        if (!Auth::can('manage_rentals')) {
            Response::json(['success' => false, 'message' => trans('error.no_permission')], 403);
            return;
        }
        
        if (!$this->validateCsrf()) {
            Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
            return;
        }
        
        $rental = $this->rentalModel->find($rentalId);
        
        if (!$rental) {
            Response::json(['success' => false, 'message' => trans('rental.not_found')], 404);
            return;
        }
        
        $newEndDate = Request::post('new_end_date');
        
        if (empty($newEndDate)) {
            Response::json([
                'success' => false,
                'message' => trans('rental.error.new_end_date_required')
            ], 422);
            return;
        }
        
        // التحقق من إمكانية التمديد
        $canExtend = $this->extensionModel->canExtend($rentalId, $newEndDate);
        
        if (!$canExtend['can_extend']) {
            Response::json([
                'success' => false,
                'message' => trans('rental.error.' . $canExtend['reason'])
            ], 403);
            return;
        }
        
        $data = [
            'rental_id' => $rentalId,
            'original_end_date' => $rental['end_date'],
            'new_end_date' => $newEndDate,
            'approved_by' => Auth::id()
        ];
        
        $extensionId = $this->extensionModel->createExtension($data);
        
        if ($extensionId) {
            $this->log('extend_rental', 'rental_extensions', $extensionId, $data);
            
            Response::json([
                'success' => true,
                'message' => trans('rental.extended_successfully')
            ]);
        } else {
            Response::json([
                'success' => false,
                'message' => trans('error.create_failed')
            ], 500);
        }
    }

    // ========================================
    // التقويم
    // ========================================

    /**
     * عرض تقويم الإيجارات
     */
    public function calendar(): void
    {
        $data = [
            'title' => trans('rental.calendar.title')
        ];
        
        $this->view('backend/rentals/calendar', $data);
    }

    /**
     * الحصول على بيانات التقويم (AJAX)
     */
    public function getCalendarData(): void
    {
        $start = Request::get('start');
        $end = Request::get('end');
        
        if (empty($start) || empty($end)) {
            Response::json(['success' => false, 'message' => 'Invalid date range'], 400);
            return;
        }
        
        $rentals = $this->rentalModel->getRentalsForCalendar($start, $end);
        
        // تحويل البيانات لصيغة FullCalendar
        $events = array_map(function($rental) {
            $colors = [
                'pending' => '#ffc107',
                'confirmed' => '#17a2b8',
                'active' => '#28a745',
                'extended' => '#6f42c1',
                'completed' => '#6c757d',
                'cancelled' => '#dc3545'
            ];
            
            return [
                'id' => $rental['id'],
                'title' => $rental['customer_name'] . ' - ' . $rental['car_info'],
                'start' => $rental['start_date'],
                'end' => $rental['end_date'],
                'backgroundColor' => $colors[$rental['status']] ?? '#007bff',
                'borderColor' => $colors[$rental['status']] ?? '#007bff',
                'url' => '/admin/rentals/show/' . $rental['id']
            ];
        }, $rentals);
        
        Response::json($events);
    }

    // ========================================
    // توليد عقد PDF
    // ========================================

    /**
     * توليد عقد PDF
     * 
     * @param int $id معرف الإيجار
     */
    public function generateContract(int $id): void
    {
        $rental = $this->rentalModel->getWithFullDetails($id);
        
        if (!$rental) {
            Response::notFound(trans('rental.not_found'));
        }
        
        // إنشاء PDF
        require_once ROOT . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // معلومات المستند
        $pdf->SetCreator(setting('company_name'));
        $pdf->SetAuthor(setting('company_name'));
        $pdf->SetTitle(trans('rental.contract.title') . ' - ' . $rental['rental_number']);
        
        // إزالة الهيدر والفوتر الافتراضي
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // تعيين الخط
        $pdf->SetFont('dejavusans', '', 11);
        
        // إضافة صفحة
        $pdf->AddPage();
        
        // محتوى العقد
        $html = $this->getContractHTML($rental);
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // حفظ الملف
        $filename = 'contract_' . $rental['rental_number'] . '.pdf';
        $filepath = ROOT . '/public/uploads/contracts/' . $filename;
        
        // إنشاء المجلد إن لم يكن موجوداً
        if (!is_dir(ROOT . '/public/uploads/contracts')) {
            mkdir(ROOT . '/public/uploads/contracts', 0755, true);
        }
        
        $pdf->Output($filepath, 'F');
        
        // تحديث مسار العقد في قاعدة البيانات
        $this->rentalModel->update($id, [
            'contract_pdf_path' => 'contracts/' . $filename
        ]);
        
        // إرسال الملف للمتصفح
        $pdf->Output($filename, 'I');
    }

    /**
     * الحصول على HTML للعقد
     * 
     * @param array $rental بيانات الإيجار
     * @return string HTML
     */
    private function getContractHTML(array $rental): string
    {
        $lang = currentLang();
        $dir = $lang['direction'] ?? 'rtl';
        
        $html = '
        <style>
            body { direction: ' . $dir . '; font-family: dejavusans; }
            .header { text-align: center; margin-bottom: 30px; }
            .company-name { font-size: 20px; font-weight: bold; color: #333; }
            .contract-title { font-size: 16px; margin-top: 10px; }
            .section { margin: 20px 0; }
            .section-title { font-size: 14px; font-weight: bold; background: #f5f5f5; padding: 8px; margin-bottom: 10px; }
            .field { margin: 8px 0; }
            .field-label { display: inline-block; width: 150px; font-weight: bold; }
            .field-value { display: inline-block; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            table th, table td { border: 1px solid #ddd; padding: 8px; text-align: ' . ($dir === 'rtl' ? 'right' : 'left') . '; }
            table th { background: #f5f5f5; font-weight: bold; }
            .signature { margin-top: 50px; }
            .signature-box { display: inline-block; width: 45%; text-align: center; border-top: 1px solid #000; padding-top: 10px; }
            .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
        </style>
        
        <div class="header">
            <div class="company-name">' . setting('company_name') . '</div>
            <div>' . setting('company_address') . '</div>
            <div>' . trans('rental.contract.phone') . ': ' . setting('company_phone') . '</div>
            <div class="contract-title">' . trans('rental.contract.title') . '</div>
            <div>' . trans('rental.contract.number') . ': ' . $rental['rental_number'] . '</div>
            <div>' . trans('rental.contract.date') . ': ' . date('Y-m-d') . '</div>
        </div>
        
        <div class="section">
            <div class="section-title">' . trans('rental.contract.customer_info') . '</div>
            <div class="field">
                <span class="field-label">' . trans('customer.name') . ':</span>
                <span class="field-value">' . $rental['customer_name'] . '</span>
            </div>
            <div class="field">
                <span class="field-label">' . trans('customer.phone') . ':</span>
                <span class="field-value">' . $rental['customer_phone'] . '</span>
            </div>
            <div class="field">
                <span class="field-label">' . trans('customer.id_number') . ':</span>
                <span class="field-value">' . $rental['customer_id_number'] . '</span>
            </div>
            <div class="field">
                <span class="field-label">' . trans('customer.license_number') . ':</span>
                <span class="field-value">' . $rental['customer_license_number'] . '</span>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">' . trans('rental.contract.car_info') . '</div>
            <div class="field">
                <span class="field-label">' . trans('car.brand') . ':</span>
                <span class="field-value">' . $rental['brand_name'] . '</span>
            </div>
            <div class="field">
                <span class="field-label">' . trans('car.model') . ':</span>
                <span class="field-value">' . $rental['model_name'] . ' (' . $rental['year'] . ')</span>
            </div>
            <div class="field">
                <span class="field-label">' . trans('car.plate_number') . ':</span>
                <span class="field-value">' . $rental['plate_number'] . '</span>
            </div>
            <div class="field">
                <span class="field-label">' . trans('car.color') . ':</span>
                <span class="field-value">' . $rental['color'] . '</span>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">' . trans('rental.contract.rental_details') . '</div>
            <table>
                <tr>
                    <th>' . trans('rental.start_date') . '</th>
                    <td>' . date('Y-m-d H:i', strtotime($rental['start_date'])) . '</td>
                    <th>' . trans('rental.end_date') . '</th>
                    <td>' . date('Y-m-d H:i', strtotime($rental['end_date'])) . '</td>
                </tr>
                <tr>
                    <th>' . trans('rental.duration') . '</th>
                    <td>' . $rental['rental_duration_days'] . ' ' . trans('common.days') . '</td>
                    <th>' . trans('rental.daily_rate') . '</th>
                    <td>' . number_format($rental['daily_rate'], 2) . ' ' . trans('common.currency') . '</td>
                </tr>';
        
        if ($rental['with_driver']) {
            $html .= '
                <tr>
                    <th>' . trans('rental.driver_name') . '</th>
                    <td>' . $rental['driver_name'] . '</td>
                    <th>' . trans('rental.driver_rate') . '</th>
                    <td>' . number_format($rental['driver_daily_rate'], 2) . ' ' . trans('common.currency') . '</td>
                </tr>';
        }
        
        $html .= '
            </table>
        </div>
        
        <div class="section">
            <div class="section-title">' . trans('rental.contract.financial_details') . '</div>
            <table>
                <tr>
                    <th>' . trans('rental.total_amount') . '</th>
                    <td>' . number_format($rental['total_amount'], 2) . ' ' . trans('common.currency') . '</td>
                </tr>
                <tr>
                    <th>' . trans('rental.deposit_amount') . '</th>
                    <td>' . number_format($rental['deposit_amount'], 2) . ' ' . trans('common.currency') . '</td>
                </tr>
                <tr>
                    <th>' . trans('rental.paid_amount') . '</th>
                    <td>' . number_format($rental['paid_amount'], 2) . ' ' . trans('common.currency') . '</td>
                </tr>
                <tr>
                    <th>' . trans('rental.remaining_amount') . '</th>
                    <td style="font-weight: bold;">' . number_format($rental['remaining_amount'], 2) . ' ' . trans('common.currency') . '</td>
                </tr>
            </table>
        </div>
        
        <div class="section">
            <div class="section-title">' . trans('rental.contract.terms') . '</div>
            <div>' . nl2br(setting('rental_terms_conditions')) . '</div>
        </div>
        
        <div class="signature">
            <div class="signature-box">
                <div>' . trans('rental.contract.customer_signature') . '</div>
            </div>
            <div class="signature-box" style="float: ' . ($dir === 'rtl' ? 'left' : 'right') . ';">
                <div>' . trans('rental.contract.company_signature') . '</div>
            </div>
        </div>
        
        <div class="footer">
            <div>' . setting('company_name') . ' - ' . setting('company_phone') . '</div>
            <div>' . setting('company_website') . '</div>
        </div>';
        
        return $html;
    }

    // ========================================
    // التقارير السريعة
    // ========================================

    /**
     * تصدير الإيجارات إلى Excel
     */
    public function exportExcel(): void
    {
        if (!Auth::can('view_rentals')) {
            Response::forbidden();
        }
        
        // الفلاتر
        $filters = [
            'status' => Request::get('status'),
            'payment_status' => Request::get('payment_status'),
            'branch_id' => Request::get('branch_id'),
            'date_from' => Request::get('date_from'),
            'date_to' => Request::get('date_to')
        ];
        
        $filters = array_filter($filters);
        
        // الحصول على البيانات (بدون pagination)
        $rentals = $this->rentalModel->getAllWithDetails($filters, 1, 999999)['data'];
        
        // إنشاء CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=rentals_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // العناوين
        fputcsv($output, [
            trans('rental.rental_number'),
            trans('customer.name'),
            trans('car.info'),
            trans('rental.start_date'),
            trans('rental.end_date'),
            trans('rental.duration'),
            trans('rental.total_amount'),
            trans('rental.paid_amount'),
            trans('rental.remaining_amount'),
            trans('rental.status'),
            trans('rental.payment_status')
        ]);
        
        // البيانات
        foreach ($rentals as $rental) {
            fputcsv($output, [
                $rental['rental_number'],
                $rental['customer_name'],
                $rental['brand_name'] . ' ' . $rental['model_name'] . ' (' . $rental['plate_number'] . ')',
                $rental['start_date'],
                $rental['end_date'],
                $rental['rental_duration_days'],
                $rental['total_amount'],
                $rental['paid_amount'],
                $rental['remaining_amount'],
                trans('rental.status.' . $rental['status']),
                trans('rental.payment_status.' . $rental['payment_status'])
            ]);
        }
        
        fclose($output);
        exit;
    }
}

// تسجيل الملف
use Core\FileTracker;
FileTracker::logModify(__FILE__, 0, FileTracker::countLines(__FILE__), 'Phase 7');
