<?php
/**
 * File: AdminMaintenanceController.php
 * Path: /app/controllers/backend/AdminMaintenanceController.php
 * Purpose: متحكم إدارة الصيانة - CRUD كامل وإدارة التنبيهات
 * Dependencies: Controller.php, CarMaintenance.php, Car.php, Uploader.php
 * Phase: Phase 5 - Maintenance System
 * Created: 2025-10-24
 */

namespace App\Controllers\Backend;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Validator;
use Core\Uploader;
use Core\FileTracker;
use App\Models\CarMaintenance;
use App\Models\Car;

/**
 * Class AdminMaintenanceController
 * المتحكم الرئيسي لإدارة الصيانة
 * 
 * @package App\Controllers\Backend
 */
class AdminMaintenanceController extends Controller
{
    private CarMaintenance $maintenanceModel;
    private Car $carModel;

    public function __construct()
    {
        parent::__construct();
        
        // التحقق من تسجيل الدخول
        if (!Session::has('user_id')) {
            Response::redirect('/admin/login');
        }

        $this->maintenanceModel = new CarMaintenance();
        $this->carModel = new Car();

        // تسجيل الملف
        FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 5');
    }

    /**
     * عرض قائمة سجلات الصيانة
     * GET /admin/maintenance
     */
    public function index(): void
    {
        try {
            $page = Request::get('page', 1);
            $perPage = Request::get('per_page', 20);
            
            $filters = [
                'car_id' => Request::get('car_id'),
                'maintenance_type' => Request::get('type'),
                'date_from' => Request::get('date_from'),
                'date_to' => Request::get('date_to'),
                'search' => Request::get('search')
            ];

            $maintenanceRecords = $this->maintenanceModel->getAll($filters, $page, $perPage);
            $totalRecords = $this->maintenanceModel->count($filters);
            $totalPages = ceil($totalRecords / $perPage);

            // الحصول على الإحصائيات
            $statistics = $this->maintenanceModel->getStatistics();

            // الحصول على قائمة السيارات للفلترة
            $cars = $this->carModel->getAll(['status' => 'all'], 1, 1000);

            $this->view('backend/maintenance/index', [
                'maintenanceRecords' => $maintenanceRecords,
                'statistics' => $statistics,
                'cars' => $cars,
                'filters' => $filters,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $totalRecords,
                    'per_page' => $perPage
                ]
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في maintenance index: " . $e->getMessage());
            Session::flash('error', trans('maintenance.error.loading'));
            Response::redirect('/admin/dashboard');
        }
    }

    /**
     * عرض صفحة إضافة سجل صيانة جديد
     * GET /admin/maintenance/create
     */
    public function create(): void
    {
        try {
            // الحصول على معرف السيارة من الرابط
            $carId = Request::get('car_id');
            
            // الحصول على جميع السيارات النشطة
            $cars = $this->carModel->getAll(['status' => 'all'], 1, 1000);

            // إذا كان هناك معرف سيارة، جلب تفاصيلها
            $selectedCar = null;
            if ($carId) {
                $selectedCar = $this->carModel->find($carId);
            }

            $this->view('backend/maintenance/create', [
                'cars' => $cars,
                'selectedCar' => $selectedCar,
                'maintenanceTypes' => CarMaintenance::getTypes()
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في maintenance create: " . $e->getMessage());
            Session::flash('error', trans('maintenance.error.loading'));
            Response::redirect('/admin/maintenance');
        }
    }

    /**
     * حفظ سجل صيانة جديد
     * POST /admin/maintenance/store
     */
    public function store(): void
    {
        try {
            // التحقق من CSRF token
            if (!$this->validateCsrfToken()) {
                Session::flash('error', trans('error.csrf_invalid'));
                Response::redirect('/admin/maintenance/create');
                return;
            }

            $data = Request::all();

            // التحقق من صحة البيانات
            $validator = new Validator($data, [
                'car_id' => 'required|integer',
                'maintenance_type' => 'required|in:periodic,repair,accident,inspection,other',
                'description' => 'required|min:10|max:1000',
                'maintenance_date' => 'required|date',
                'cost' => 'nullable|numeric|min:0',
                'odometer_reading' => 'nullable|integer|min:0',
                'service_center' => 'nullable|max:100',
                'technician_name' => 'nullable|max:100',
                'next_maintenance_date' => 'nullable|date|after:maintenance_date',
                'parts_replaced' => 'nullable|max:1000',
                'notes' => 'nullable|max:1000'
            ]);

            if ($validator->fails()) {
                Session::flash('error', $validator->errors()[0]);
                Session::flash('old', $data);
                Response::redirect('/admin/maintenance/create?car_id=' . $data['car_id']);
                return;
            }

            // التحقق من وجود السيارة
            $car = $this->carModel->find($data['car_id']);
            if (!$car) {
                Session::flash('error', trans('maintenance.error.car_not_found'));
                Response::redirect('/admin/maintenance/create');
                return;
            }

            // رفع الإيصال إن وجد
            $receiptPath = null;
            if (Request::hasFile('receipt')) {
                $uploader = new Uploader();
                $upload = $uploader->upload(
                    Request::file('receipt'),
                    'maintenance/receipts',
                    ['jpg', 'jpeg', 'png', 'pdf'],
                    5 * 1024 * 1024 // 5MB
                );

                if ($upload['success']) {
                    $receiptPath = $upload['path'];
                } else {
                    Session::flash('warning', $upload['error']);
                }
            }

            // إضافة معلومات إضافية
            $data['receipt_path'] = $receiptPath;
            $data['created_by'] = Session::get('user_id');

            // إنشاء السجل
            $maintenanceId = $this->maintenanceModel->create($data);

            if ($maintenanceId) {
                Session::flash('success', trans('maintenance.created_successfully'));
                Response::redirect('/admin/maintenance/view/' . $maintenanceId);
            } else {
                Session::flash('error', trans('maintenance.error.creating'));
                Session::flash('old', $data);
                Response::redirect('/admin/maintenance/create?car_id=' . $data['car_id']);
            }
        } catch (\Exception $e) {
            error_log("خطأ في maintenance store: " . $e->getMessage());
            Session::flash('error', trans('maintenance.error.creating'));
            Response::redirect('/admin/maintenance/create');
        }
    }

    /**
     * عرض تفاصيل سجل صيانة
     * GET /admin/maintenance/view/{id}
     */
    public function view(int $id): void
    {
        try {
            $maintenance = $this->maintenanceModel->find($id);

            if (!$maintenance) {
                Session::flash('error', trans('maintenance.not_found'));
                Response::redirect('/admin/maintenance');
                return;
            }

            // الحصول على سجلات الصيانة السابقة لنفس السيارة
            $previousMaintenance = $this->maintenanceModel->getByCarId($maintenance['car_id'], 5);

            $this->view('backend/maintenance/view', [
                'maintenance' => $maintenance,
                'previousMaintenance' => $previousMaintenance
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في maintenance view: " . $e->getMessage());
            Session::flash('error', trans('maintenance.error.loading'));
            Response::redirect('/admin/maintenance');
        }
    }

    /**
     * عرض صفحة تعديل سجل صيانة
     * GET /admin/maintenance/edit/{id}
     */
    public function edit(int $id): void
    {
        try {
            $maintenance = $this->maintenanceModel->find($id);

            if (!$maintenance) {
                Session::flash('error', trans('maintenance.not_found'));
                Response::redirect('/admin/maintenance');
                return;
            }

            // الحصول على جميع السيارات النشطة
            $cars = $this->carModel->getAll(['status' => 'all'], 1, 1000);

            $this->view('backend/maintenance/edit', [
                'maintenance' => $maintenance,
                'cars' => $cars,
                'maintenanceTypes' => CarMaintenance::getTypes()
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في maintenance edit: " . $e->getMessage());
            Session::flash('error', trans('maintenance.error.loading'));
            Response::redirect('/admin/maintenance');
        }
    }

    /**
     * تحديث سجل صيانة
     * POST /admin/maintenance/update/{id}
     */
    public function update(int $id): void
    {
        try {
            // التحقق من CSRF token
            if (!$this->validateCsrfToken()) {
                Session::flash('error', trans('error.csrf_invalid'));
                Response::redirect('/admin/maintenance/edit/' . $id);
                return;
            }

            $maintenance = $this->maintenanceModel->find($id);
            if (!$maintenance) {
                Session::flash('error', trans('maintenance.not_found'));
                Response::redirect('/admin/maintenance');
                return;
            }

            $data = Request::all();

            // التحقق من صحة البيانات
            $validator = new Validator($data, [
                'car_id' => 'required|integer',
                'maintenance_type' => 'required|in:periodic,repair,accident,inspection,other',
                'description' => 'required|min:10|max:1000',
                'maintenance_date' => 'required|date',
                'cost' => 'nullable|numeric|min:0',
                'odometer_reading' => 'nullable|integer|min:0',
                'service_center' => 'nullable|max:100',
                'technician_name' => 'nullable|max:100',
                'next_maintenance_date' => 'nullable|date|after:maintenance_date',
                'parts_replaced' => 'nullable|max:1000',
                'notes' => 'nullable|max:1000'
            ]);

            if ($validator->fails()) {
                Session::flash('error', $validator->errors()[0]);
                Session::flash('old', $data);
                Response::redirect('/admin/maintenance/edit/' . $id);
                return;
            }

            // رفع إيصال جديد إن وجد
            if (Request::hasFile('receipt')) {
                $uploader = new Uploader();
                $upload = $uploader->upload(
                    Request::file('receipt'),
                    'maintenance/receipts',
                    ['jpg', 'jpeg', 'png', 'pdf'],
                    5 * 1024 * 1024 // 5MB
                );

                if ($upload['success']) {
                    // حذف الإيصال القديم
                    if (!empty($maintenance['receipt_path']) && file_exists($maintenance['receipt_path'])) {
                        unlink($maintenance['receipt_path']);
                    }
                    $data['receipt_path'] = $upload['path'];
                }
            }

            // تحديث السجل
            if ($this->maintenanceModel->update($id, $data)) {
                Session::flash('success', trans('maintenance.updated_successfully'));
                Response::redirect('/admin/maintenance/view/' . $id);
            } else {
                Session::flash('error', trans('maintenance.error.updating'));
                Session::flash('old', $data);
                Response::redirect('/admin/maintenance/edit/' . $id);
            }
        } catch (\Exception $e) {
            error_log("خطأ في maintenance update: " . $e->getMessage());
            Session::flash('error', trans('maintenance.error.updating'));
            Response::redirect('/admin/maintenance/edit/' . $id);
        }
    }

    /**
     * حذف سجل صيانة
     * POST /admin/maintenance/delete/{id}
     */
    public function delete(int $id): void
    {
        try {
            // التحقق من CSRF token
            if (!$this->validateCsrfToken()) {
                Session::flash('error', trans('error.csrf_invalid'));
                Response::redirect('/admin/maintenance');
                return;
            }

            if ($this->maintenanceModel->delete($id)) {
                Session::flash('success', trans('maintenance.deleted_successfully'));
            } else {
                Session::flash('error', trans('maintenance.error.deleting'));
            }

            Response::redirect('/admin/maintenance');
        } catch (\Exception $e) {
            error_log("خطأ في maintenance delete: " . $e->getMessage());
            Session::flash('error', trans('maintenance.error.deleting'));
            Response::redirect('/admin/maintenance');
        }
    }

    /**
     * عرض صفحة التنبيهات
     * GET /admin/maintenance/alerts
     */
    public function alerts(): void
    {
        try {
            // السيارات التي تحتاج صيانة فوراً
            $carsNeedingMaintenance = $this->maintenanceModel->getCarsNeedingMaintenance(100);

            // السيارات القريبة من موعد الصيانة (500 كم)
            $carsNearingMaintenance = $this->maintenanceModel->getCarsNearingMaintenance(500, 100);

            // الإحصائيات
            $statistics = [
                'overdue' => count($carsNeedingMaintenance),
                'upcoming' => count($carsNearingMaintenance),
                'total_overdue_km' => array_sum(array_column($carsNeedingMaintenance, 'overdue_km')),
            ];

            $this->view('backend/maintenance/alerts', [
                'carsNeedingMaintenance' => $carsNeedingMaintenance,
                'carsNearingMaintenance' => $carsNearingMaintenance,
                'statistics' => $statistics
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في maintenance alerts: " . $e->getMessage());
            Session::flash('error', trans('maintenance.error.loading'));
            Response::redirect('/admin/dashboard');
        }
    }

    /**
     * الحصول على سجلات الصيانة لسيارة معينة (AJAX)
     * GET /admin/maintenance/car/{carId}
     */
    public function getCarMaintenance(int $carId): void
    {
        try {
            $maintenanceRecords = $this->maintenanceModel->getByCarId($carId);
            $totalCost = $this->maintenanceModel->getTotalCostForCar($carId);

            Response::json([
                'success' => true,
                'data' => [
                    'records' => $maintenanceRecords,
                    'total_cost' => $totalCost,
                    'total_records' => count($maintenanceRecords)
                ]
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في getCarMaintenance: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('maintenance.error.loading')
            ], 500);
        }
    }

    /**
     * تصدير سجلات الصيانة (CSV)
     * GET /admin/maintenance/export
     */
    public function export(): void
    {
        try {
            $filters = [
                'car_id' => Request::get('car_id'),
                'maintenance_type' => Request::get('type'),
                'date_from' => Request::get('date_from'),
                'date_to' => Request::get('date_to'),
                'search' => Request::get('search')
            ];

            $maintenanceRecords = $this->maintenanceModel->getAll($filters, 1, 10000);

            // إنشاء ملف CSV
            $filename = 'maintenance_records_' . date('Y-m-d_H-i-s') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');

            // BOM للتوافق مع Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // العناوين
            fputcsv($output, [
                trans('maintenance.id'),
                trans('maintenance.car'),
                trans('maintenance.type'),
                trans('maintenance.date'),
                trans('maintenance.odometer'),
                trans('maintenance.cost'),
                trans('maintenance.service_center'),
                trans('maintenance.description')
            ]);

            // البيانات
            foreach ($maintenanceRecords as $record) {
                fputcsv($output, [
                    $record['id'],
                    $record['plate_number'] . ' - ' . $record['brand_name'] . ' ' . $record['model_name'],
                    trans('maintenance.type.' . $record['maintenance_type']),
                    $record['maintenance_date'],
                    $record['odometer_reading'] ?? '-',
                    $record['cost'] ?? '-',
                    $record['service_center'] ?? '-',
                    $record['description']
                ]);
            }

            fclose($output);
            exit;
        } catch (\Exception $e) {
            error_log("خطأ في maintenance export: " . $e->getMessage());
            Session::flash('error', trans('maintenance.error.exporting'));
            Response::redirect('/admin/maintenance');
        }
    }

    /**
     * الحصول على إحصائيات الصيانة (AJAX)
     * GET /admin/maintenance/statistics
     */
    public function statistics(): void
    {
        try {
            $dateFrom = Request::get('date_from', date('Y-01-01'));
            $dateTo = Request::get('date_to', date('Y-m-d'));

            $statistics = $this->maintenanceModel->getStatistics();
            $recordsByType = [];
            
            foreach (CarMaintenance::getTypes() as $type => $label) {
                $recordsByType[$type] = $this->maintenanceModel->getByType($type, 1000);
            }

            Response::json([
                'success' => true,
                'data' => [
                    'general' => $statistics,
                    'by_type' => $recordsByType
                ]
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في maintenance statistics: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => trans('maintenance.error.loading')
            ], 500);
        }
    }
}

// تسجيل الملف في FileTracker
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 5');
