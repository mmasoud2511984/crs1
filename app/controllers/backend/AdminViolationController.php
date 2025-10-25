<?php
/**
 * File: AdminViolationController.php
 * Path: /app/controllers/backend/AdminViolationController.php
 * Purpose: إدارة المخالفات في لوحة التحكم
 * Dependencies: Violation Model, Car Model, Rental Model, Customer Model
 * Phase: Phase 8 - Violations & Reviews
 * Created: 2025-10-24
 */

namespace App\Controllers\Backend;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Security;
use Core\Validator;
use Core\Uploader;
use Core\FileTracker;
use App\Models\Violation;
use App\Models\Car;
use App\Models\Rental;
use App\Models\Customer;

class AdminViolationController extends Controller
{
    /**
     * عرض قائمة المخالفات
     */
    public function index(Request $request)
    {
        // التحقق من الصلاحيات
        if (!auth()->check() || !auth()->hasPermission('violations.view')) {
            return redirect('/admin/login');
        }

        $page = $request->get('page', 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // الفلاتر
        $filters = [
            'status' => $request->get('status'),
            'car_id' => $request->get('car_id'),
            'customer_id' => $request->get('customer_id'),
            'paid_by' => $request->get('paid_by'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'search' => $request->get('search'),
            'limit' => $perPage,
            'offset' => $offset
        ];

        // الحصول على البيانات
        $violations = Violation::getAll($filters);
        $total = Violation::count($filters);
        $totalPages = ceil($total / $perPage);

        // الإحصائيات
        $statistics = Violation::getStatistics();

        // السيارات للفلتر
        $cars = Car::getActive();

        FileTracker::logModify(__FILE__, 55, FileTracker::countLines(__FILE__), 'Phase 8');

        return $this->view('backend/violations/index', [
            'violations' => $violations,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'filters' => $filters,
            'statistics' => $statistics,
            'cars' => $cars
        ]);
    }

    /**
     * عرض نموذج إضافة مخالفة
     */
    public function create(Request $request)
    {
        if (!auth()->check() || !auth()->hasPermission('violations.create')) {
            return redirect('/admin/login');
        }

        // الحصول على السيارات النشطة
        $cars = Car::getActive();

        // الحصول على العملاء
        $customers = Customer::getActive();

        // إذا كان هناك rental_id في الرابط، نجلب بيانات الإيجار
        $rentalId = $request->get('rental_id');
        $rental = null;
        if ($rentalId) {
            $rental = Rental::getById($rentalId);
        }

        return $this->view('backend/violations/create', [
            'cars' => $cars,
            'customers' => $customers,
            'rental' => $rental
        ]);
    }

    /**
     * حفظ مخالفة جديدة
     */
    public function store(Request $request)
    {
        if (!auth()->check() || !auth()->hasPermission('violations.create')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        // التحقق من CSRF
        if (!Security::validateCSRF($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
        }

        // التحقق من البيانات
        $rules = [
            'car_id' => 'required|integer',
            'violation_date' => 'required|date',
            'violation_type' => 'required|min:3',
            'fine_amount' => 'required|numeric|min:0',
            'paid_by' => 'required|in:company,customer,pending',
            'status' => 'required|in:pending,paid,disputed,cancelled'
        ];

        $validator = new Validator($request->all(), $rules);
        if (!$validator->validate()) {
            return Response::json([
                'success' => false,
                'message' => trans('error.validation_failed'),
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $data = [
                'car_id' => $request->post('car_id'),
                'rental_id' => $request->post('rental_id') ?: null,
                'customer_id' => $request->post('customer_id') ?: null,
                'violation_number' => $request->post('violation_number') ?: Violation::generateViolationNumber(),
                'violation_date' => $request->post('violation_date'),
                'violation_type' => Security::sanitize($request->post('violation_type')),
                'violation_location' => Security::sanitize($request->post('violation_location')),
                'fine_amount' => $request->post('fine_amount'),
                'paid_by' => $request->post('paid_by'),
                'status' => $request->post('status'),
                'notes' => Security::sanitize($request->post('notes')),
                'created_by' => auth()->id()
            ];

            // إذا كانت مدفوعة
            if ($data['status'] === 'paid') {
                $data['payment_date'] = $request->post('payment_date') ?: date('Y-m-d H:i:s');
                $data['payment_reference'] = Security::sanitize($request->post('payment_reference'));
            }

            // رفع المستند
            if ($request->hasFile('document')) {
                $uploader = new Uploader('document');
                $uploader->setAllowedTypes(['pdf', 'jpg', 'jpeg', 'png']);
                $uploader->setMaxSize(5 * 1024 * 1024); // 5MB
                $uploader->setUploadPath('uploads/violations/');

                if ($uploader->upload()) {
                    $data['document_path'] = $uploader->getFilePath();
                }
            }

            $violationId = Violation::create($data);

            // إرسال إشعار (إن وجد العميل)
            if (!empty($data['customer_id'])) {
                // TODO: إرسال إشعار للعميل
            }

            return Response::json([
                'success' => true,
                'message' => trans('violation.created_successfully'),
                'redirect' => '/admin/violations/' . $violationId
            ]);

        } catch (\Exception $e) {
            error_log("Error creating violation: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => trans('error.something_went_wrong')
            ], 500);
        }
    }

    /**
     * عرض تفاصيل مخالفة
     */
    public function show(Request $request, $id)
    {
        if (!auth()->check() || !auth()->hasPermission('violations.view')) {
            return redirect('/admin/login');
        }

        $violation = Violation::getById($id);

        if (!$violation) {
            session()->setFlash('error', trans('violation.not_found'));
            return redirect('/admin/violations');
        }

        return $this->view('backend/violations/view', [
            'violation' => $violation
        ]);
    }

    /**
     * عرض نموذج تعديل مخالفة
     */
    public function edit(Request $request, $id)
    {
        if (!auth()->check() || !auth()->hasPermission('violations.edit')) {
            return redirect('/admin/login');
        }

        $violation = Violation::getById($id);

        if (!$violation) {
            session()->setFlash('error', trans('violation.not_found'));
            return redirect('/admin/violations');
        }

        $cars = Car::getActive();
        $customers = Customer::getActive();

        return $this->view('backend/violations/edit', [
            'violation' => $violation,
            'cars' => $cars,
            'customers' => $customers
        ]);
    }

    /**
     * تحديث مخالفة
     */
    public function update(Request $request, $id)
    {
        if (!auth()->check() || !auth()->hasPermission('violations.edit')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        // التحقق من CSRF
        if (!Security::validateCSRF($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
        }

        // التحقق من وجود المخالفة
        $violation = Violation::find($id);
        if (!$violation) {
            return Response::json(['success' => false, 'message' => trans('violation.not_found')], 404);
        }

        // التحقق من البيانات
        $rules = [
            'car_id' => 'required|integer',
            'violation_date' => 'required|date',
            'violation_type' => 'required|min:3',
            'fine_amount' => 'required|numeric|min:0',
            'paid_by' => 'required|in:company,customer,pending',
            'status' => 'required|in:pending,paid,disputed,cancelled'
        ];

        $validator = new Validator($request->all(), $rules);
        if (!$validator->validate()) {
            return Response::json([
                'success' => false,
                'message' => trans('error.validation_failed'),
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $data = [
                'car_id' => $request->post('car_id'),
                'rental_id' => $request->post('rental_id') ?: null,
                'customer_id' => $request->post('customer_id') ?: null,
                'violation_number' => $request->post('violation_number'),
                'violation_date' => $request->post('violation_date'),
                'violation_type' => Security::sanitize($request->post('violation_type')),
                'violation_location' => Security::sanitize($request->post('violation_location')),
                'fine_amount' => $request->post('fine_amount'),
                'paid_by' => $request->post('paid_by'),
                'status' => $request->post('status'),
                'notes' => Security::sanitize($request->post('notes'))
            ];

            // إذا كانت مدفوعة
            if ($data['status'] === 'paid') {
                $data['payment_date'] = $request->post('payment_date') ?: date('Y-m-d H:i:s');
                $data['payment_reference'] = Security::sanitize($request->post('payment_reference'));
            } else {
                $data['payment_date'] = null;
                $data['payment_reference'] = null;
            }

            // رفع المستند الجديد
            if ($request->hasFile('document')) {
                $uploader = new Uploader('document');
                $uploader->setAllowedTypes(['pdf', 'jpg', 'jpeg', 'png']);
                $uploader->setMaxSize(5 * 1024 * 1024); // 5MB
                $uploader->setUploadPath('uploads/violations/');

                if ($uploader->upload()) {
                    // حذف المستند القديم
                    if (!empty($violation['document_path']) && file_exists($violation['document_path'])) {
                        unlink($violation['document_path']);
                    }
                    $data['document_path'] = $uploader->getFilePath();
                }
            }

            Violation::updateById($id, $data);

            return Response::json([
                'success' => true,
                'message' => trans('violation.updated_successfully'),
                'redirect' => '/admin/violations/' . $id
            ]);

        } catch (\Exception $e) {
            error_log("Error updating violation: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => trans('error.something_went_wrong')
            ], 500);
        }
    }

    /**
     * حذف مخالفة
     */
    public function destroy(Request $request, $id)
    {
        if (!auth()->check() || !auth()->hasPermission('violations.delete')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        // التحقق من CSRF
        if (!Security::validateCSRF($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
        }

        try {
            $deleted = Violation::deleteById($id);

            if ($deleted) {
                return Response::json([
                    'success' => true,
                    'message' => trans('violation.deleted_successfully')
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => trans('violation.not_found')
                ], 404);
            }

        } catch (\Exception $e) {
            error_log("Error deleting violation: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => trans('error.something_went_wrong')
            ], 500);
        }
    }

    /**
     * تحديث حالة المخالفة
     */
    public function updateStatus(Request $request, $id)
    {
        if (!auth()->check() || !auth()->hasPermission('violations.edit')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        // التحقق من CSRF
        if (!Security::validateCSRF($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
        }

        $status = $request->post('status');
        if (!in_array($status, ['pending', 'paid', 'disputed', 'cancelled'])) {
            return Response::json(['success' => false, 'message' => trans('error.invalid_status')], 400);
        }

        try {
            $data = ['status' => $status];

            // إذا كانت مدفوعة
            if ($status === 'paid') {
                $data['payment_date'] = $request->post('payment_date') ?: date('Y-m-d H:i:s');
                $data['payment_reference'] = Security::sanitize($request->post('payment_reference'));
                $data['paid_by'] = $request->post('paid_by', 'company');
            }

            Violation::updateById($id, $data);

            return Response::json([
                'success' => true,
                'message' => trans('violation.status_updated_successfully')
            ]);

        } catch (\Exception $e) {
            error_log("Error updating violation status: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => trans('error.something_went_wrong')
            ], 500);
        }
    }

    /**
     * تحميل مستند المخالفة
     */
    public function uploadDocument(Request $request, $id)
    {
        if (!auth()->check() || !auth()->hasPermission('violations.edit')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        if (!$request->hasFile('document')) {
            return Response::json(['success' => false, 'message' => trans('error.no_file_uploaded')], 400);
        }

        try {
            $violation = Violation::find($id);
            if (!$violation) {
                return Response::json(['success' => false, 'message' => trans('violation.not_found')], 404);
            }

            $uploader = new Uploader('document');
            $uploader->setAllowedTypes(['pdf', 'jpg', 'jpeg', 'png']);
            $uploader->setMaxSize(5 * 1024 * 1024); // 5MB
            $uploader->setUploadPath('uploads/violations/');

            if ($uploader->upload()) {
                // حذف المستند القديم
                if (!empty($violation['document_path']) && file_exists($violation['document_path'])) {
                    unlink($violation['document_path']);
                }

                Violation::uploadDocument($id, $uploader->getFilePath());

                return Response::json([
                    'success' => true,
                    'message' => trans('violation.document_uploaded_successfully'),
                    'file_path' => $uploader->getFilePath()
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => $uploader->getError()
                ], 400);
            }

        } catch (\Exception $e) {
            error_log("Error uploading violation document: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => trans('error.something_went_wrong')
            ], 500);
        }
    }

    /**
     * إحصائيات المخالفات
     */
    public function statistics(Request $request)
    {
        if (!auth()->check() || !auth()->hasPermission('violations.view')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        $filters = [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'car_id' => $request->get('car_id')
        ];

        $statistics = Violation::getStatistics($filters);

        return Response::json([
            'success' => true,
            'data' => $statistics
        ]);
    }

    /**
     * تصدير المخالفات (Excel/PDF)
     */
    public function export(Request $request)
    {
        if (!auth()->check() || !auth()->hasPermission('violations.view')) {
            return redirect('/admin/login');
        }

        $format = $request->get('format', 'excel'); // excel or pdf
        
        $filters = [
            'status' => $request->get('status'),
            'car_id' => $request->get('car_id'),
            'customer_id' => $request->get('customer_id'),
            'paid_by' => $request->get('paid_by'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'search' => $request->get('search')
        ];

        $violations = Violation::getAll($filters);

        // TODO: تنفيذ التصدير حسب الصيغة المطلوبة
        // سيتم تنفيذه في مرحلة التقارير

        session()->setFlash('info', trans('feature.coming_soon'));
        return redirect('/admin/violations');
    }
}

// File Tracking
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 8');
