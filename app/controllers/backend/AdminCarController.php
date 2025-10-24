<?php
/**
 * File: AdminCarController.php  
 * Path: /app/controllers/backend/AdminCarController.php
 * Purpose: التحكم الكامل بإدارة السيارات في لوحة التحكم
 * Dependencies: Controller.php, All Car Models, Uploader.php, Session.php
 * Phase: Phase 4 - Car Management
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
use App\Models\Car;
use App\Models\CarBrand;
use App\Models\CarModel;
use App\Models\CarFeature;
use App\Models\CarImage;

/**
 * Class AdminCarController
 * المتحكم الرئيسي لإدارة السيارات - CRUD كامل + إدارة الصور + المميزات
 * 
 * @package App\Controllers\Backend
 */
class AdminCarController extends Controller
{
    private Car $carModel;
    private CarBrand $brandModel;
    private CarModel $modelModel;
    private CarFeature $featureModel;
    private CarImage $imageModel;

    public function __construct()
    {
        parent::__construct();
        
        // التحقق من تسجيل الدخول والصلاحيات
        $this->middleware('auth');
        $this->middleware('permission:cars.view');
        
        $this->carModel = new Car();
        $this->brandModel = new CarBrand();
        $this->modelModel = new CarModel();
        $this->featureModel = new CarFeature();
        $this->imageModel = new CarImage();
    }

    /**
     * عرض قائمة السيارات
     */
    public function index(): void
    {
        try {
            $request = new Request();
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 20);
            
            // المصفيات
            $filters = [
                'status' => $request->get('status'),
                'brand_id' => $request->get('brand_id'),
                'model_id' => $request->get('model_id'),
                'branch_id' => $request->get('branch_id'),
                'is_featured' => $request->get('is_featured'),
                'is_with_driver' => $request->get('is_with_driver'),
                'fuel_type' => $request->get('fuel_type'),
                'transmission' => $request->get('transmission'),
                'search' => $request->get('search'),
                'order_by' => $request->get('order_by', 'c.id'),
                'order_dir' => $request->get('order_dir', 'DESC')
            ];

            // الحصول على السيارات
            $result = $this->carModel->getAll($filters, $page, $perPage);
            $cars = $result['data'];
            $pagination = $result['pagination'];

            // الحصول على البيانات للمصفيات
            $brands = $this->brandModel->getActiveBrands();
            $statistics = $this->carModel->getStatistics();

            $this->view('backend/cars/index', [
                'cars' => $cars,
                'pagination' => $pagination,
                'brands' => $brands,
                'filters' => $filters,
                'statistics' => $statistics,
                'page_title' => trans('cars.list'),
                'breadcrumbs' => [
                    ['title' => trans('dashboard'), 'url' => '/admin'],
                    ['title' => trans('cars.management'), 'url' => '/admin/cars'],
                ]
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في cars index: " . $e->getMessage());
            Session::setFlash('error', trans('errors.general'));
            Response::redirect('/admin');
        }
    }

    /**
     * عرض صفحة إضافة سيارة جديدة
     */
    public function create(): void
    {
        try {
            $this->checkPermission('cars.create');

            // الحصول على البيانات المطلوبة
            $brands = $this->brandModel->getActiveBrands();
            $features = $this->featureModel->getActive();
            $branches = $this->getBranches(); // من جدول branches

            $this->view('backend/cars/create', [
                'brands' => $brands,
                'features' => $features,
                'branches' => $branches,
                'page_title' => trans('cars.add_new'),
                'breadcrumbs' => [
                    ['title' => trans('dashboard'), 'url' => '/admin'],
                    ['title' => trans('cars.management'), 'url' => '/admin/cars'],
                    ['title' => trans('cars.add_new'), 'url' => ''],
                ]
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في cars create: " . $e->getMessage());
            Session::setFlash('error', trans('errors.general'));
            Response::redirect('/admin/cars');
        }
    }

    /**
     * حفظ سيارة جديدة
     */
    public function store(): void
    {
        try {
            $this->checkPermission('cars.create');
            $this->validateCSRF();

            $request = new Request();
            $data = $request->all();

            // التحقق من البيانات
            $validation = $this->validateCarData($data);
            if (!$validation['valid']) {
                Session::setFlash('error', $validation['message']);
                Session::set('old_input', $data);
                Response::redirect('/admin/cars/create');
                return;
            }

            // معالجة البيانات
            $carData = $this->prepareCarData($data);

            // إنشاء السيارة
            $carId = $this->carModel->create($carData);

            if (!$carId) {
                Session::setFlash('error', trans('cars.create_failed'));
                Session::set('old_input', $data);
                Response::redirect('/admin/cars/create');
                return;
            }

            // معالجة الصور
            if (!empty($_FILES['images']['name'][0])) {
                $this->handleImageUpload($carId, $_FILES['images']);
            }

            // معالجة المميزات
            if (!empty($data['features'])) {
                $this->handleFeatures($carId, $data['features']);
            }

            Session::setFlash('success', trans('cars.created_successfully'));
            Response::redirect('/admin/cars/' . $carId);
        } catch (\Exception $e) {
            error_log("خطأ في cars store: " . $e->getMessage());
            Session::setFlash('error', trans('errors.general'));
            Response::redirect('/admin/cars/create');
        }
    }

    /**
     * عرض تفاصيل سيارة
     */
    public function show(int $id): void
    {
        try {
            $car = $this->carModel->findWithDetails($id);

            if (!$car) {
                Session::setFlash('error', trans('cars.not_found'));
                Response::redirect('/admin/cars');
                return;
            }

            // الحصول على سجل الصيانة
            $maintenanceHistory = $this->getMaintenanceHistory($id);

            // الحصول على تاريخ التأجير
            $rentalHistory = $this->getRentalHistory($id);

            $this->view('backend/cars/show', [
                'car' => $car,
                'maintenance_history' => $maintenanceHistory,
                'rental_history' => $rentalHistory,
                'page_title' => $car['nickname'] ?: ($car['brand_name'] . ' ' . $car['model_name']),
                'breadcrumbs' => [
                    ['title' => trans('dashboard'), 'url' => '/admin'],
                    ['title' => trans('cars.management'), 'url' => '/admin/cars'],
                    ['title' => trans('cars.details'), 'url' => ''],
                ]
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في cars show: " . $e->getMessage());
            Session::setFlash('error', trans('errors.general'));
            Response::redirect('/admin/cars');
        }
    }

    /**
     * عرض صفحة تعديل سيارة
     */
    public function edit(int $id): void
    {
        try {
            $this->checkPermission('cars.edit');

            $car = $this->carModel->findWithDetails($id);

            if (!$car) {
                Session::setFlash('error', trans('cars.not_found'));
                Response::redirect('/admin/cars');
                return;
            }

            // الحصول على البيانات المطلوبة
            $brands = $this->brandModel->getActiveBrands();
            $models = $this->modelModel->getActiveByBrand($car['brand_id']);
            $features = $this->featureModel->getActive();
            $branches = $this->getBranches();

            $this->view('backend/cars/edit', [
                'car' => $car,
                'brands' => $brands,
                'models' => $models,
                'features' => $features,
                'branches' => $branches,
                'page_title' => trans('cars.edit') . ': ' . $car['plate_number'],
                'breadcrumbs' => [
                    ['title' => trans('dashboard'), 'url' => '/admin'],
                    ['title' => trans('cars.management'), 'url' => '/admin/cars'],
                    ['title' => trans('cars.edit'), 'url' => ''],
                ]
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في cars edit: " . $e->getMessage());
            Session::setFlash('error', trans('errors.general'));
            Response::redirect('/admin/cars');
        }
    }

    /**
     * تحديث بيانات سيارة
     */
    public function update(int $id): void
    {
        try {
            $this->checkPermission('cars.edit');
            $this->validateCSRF();

            $request = new Request();
            $data = $request->all();

            // التحقق من البيانات
            $validation = $this->validateCarData($data, $id);
            if (!$validation['valid']) {
                Session::setFlash('error', $validation['message']);
                Session::set('old_input', $data);
                Response::redirect('/admin/cars/' . $id . '/edit');
                return;
            }

            // معالجة البيانات
            $carData = $this->prepareCarData($data);

            // تحديث السيارة
            $success = $this->carModel->update($id, $carData);

            if (!$success) {
                Session::setFlash('error', trans('cars.update_failed'));
                Session::set('old_input', $data);
                Response::redirect('/admin/cars/' . $id . '/edit');
                return;
            }

            // معالجة الصور الجديدة
            if (!empty($_FILES['images']['name'][0])) {
                $this->handleImageUpload($id, $_FILES['images']);
            }

            // معالجة المميزات
            if (isset($data['features'])) {
                $this->handleFeatures($id, $data['features']);
            }

            Session::setFlash('success', trans('cars.updated_successfully'));
            Response::redirect('/admin/cars/' . $id);
        } catch (\Exception $e) {
            error_log("خطأ في cars update: " . $e->getMessage());
            Session::setFlash('error', trans('errors.general'));
            Response::redirect('/admin/cars/' . $id . '/edit');
        }
    }
    /**
     * حذف سيارة
     */
    public function delete(int $id): void
    {
        try {
            $this->checkPermission('cars.delete');
            $this->validateCSRF();

            $success = $this->carModel->delete($id);

            if (!$success) {
                Session::setFlash('error', trans('cars.delete_failed'));
            } else {
                Session::setFlash('success', trans('cars.deleted_successfully'));
            }

            Response::redirect('/admin/cars');
        } catch (\Exception $e) {
            error_log("خطأ في cars delete: " . $e->getMessage());
            Session::setFlash('error', trans('errors.general'));
            Response::redirect('/admin/cars');
        }
    }

    /**
     * استعادة سيارة محذوفة
     */
    public function restore(int $id): void
    {
        try {
            $this->checkPermission('cars.delete');
            $this->validateCSRF();

            $success = $this->carModel->restore($id);

            if (!$success) {
                Session::setFlash('error', trans('cars.restore_failed'));
            } else {
                Session::setFlash('success', trans('cars.restored_successfully'));
            }

            Response::redirect('/admin/cars/' . $id);
        } catch (\Exception $e) {
            error_log("خطأ في cars restore: " . $e->getMessage());
            Session::setFlash('error', trans('errors.general'));
            Response::redirect('/admin/cars');
        }
    }

    /**
     * تغيير حالة السيارة
     */
    public function changeStatus(int $id): void
    {
        try {
            $this->checkPermission('cars.edit');
            $this->validateCSRF();

            $request = new Request();
            $status = $request->post('status');

            $success = $this->carModel->changeStatus($id, $status);

            if (!$success) {
                Response::json(['success' => false, 'message' => trans('cars.status_change_failed')]);
                return;
            }

            Response::json([
                'success' => true,
                'message' => trans('cars.status_changed_successfully')
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في changeStatus: " . $e->getMessage());
            Response::json(['success' => false, 'message' => trans('errors.general')], 500);
        }
    }

    /**
     * تبديل حالة التمييز
     */
    public function toggleFeatured(int $id): void
    {
        try {
            $this->checkPermission('cars.edit');
            $this->validateCSRF();

            $success = $this->carModel->toggleFeatured($id);

            if (!$success) {
                Response::json(['success' => false, 'message' => trans('cars.featured_toggle_failed')]);
                return;
            }

            Response::json([
                'success' => true,
                'message' => trans('cars.featured_toggled_successfully')
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في toggleFeatured: " . $e->getMessage());
            Response::json(['success' => false, 'message' => trans('errors.general')], 500);
        }
    }

    // ====== إدارة الصور ======

    /**
     * رفع صور جديدة
     */
    public function uploadImages(int $carId): void
    {
        try {
            $this->checkPermission('cars.edit');
            $this->validateCSRF();

            if (empty($_FILES['images']['name'][0])) {
                Response::json(['success' => false, 'message' => trans('errors.no_files')]);
                return;
            }

            $uploadedCount = $this->handleImageUpload($carId, $_FILES['images']);

            Response::json([
                'success' => true,
                'message' => trans('cars.images_uploaded', ['count' => $uploadedCount]),
                'uploaded_count' => $uploadedCount
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في uploadImages: " . $e->getMessage());
            Response::json(['success' => false, 'message' => trans('errors.general')], 500);
        }
    }

    /**
     * حذف صورة
     */
    public function deleteImage(int $imageId): void
    {
        try {
            $this->checkPermission('cars.edit');
            $this->validateCSRF();

            $success = $this->imageModel->delete($imageId, true);

            if (!$success) {
                Response::json(['success' => false, 'message' => trans('cars.image_delete_failed')]);
                return;
            }

            Response::json([
                'success' => true,
                'message' => trans('cars.image_deleted_successfully')
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في deleteImage: " . $e->getMessage());
            Response::json(['success' => false, 'message' => trans('errors.general')], 500);
        }
    }

    /**
     * تعيين صورة كرئيسية
     */
    public function setPrimaryImage(int $imageId): void
    {
        try {
            $this->checkPermission('cars.edit');
            $this->validateCSRF();

            $success = $this->imageModel->setPrimary($imageId);

            if (!$success) {
                Response::json(['success' => false, 'message' => trans('cars.set_primary_failed')]);
                return;
            }

            Response::json([
                'success' => true,
                'message' => trans('cars.primary_image_set_successfully')
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في setPrimaryImage: " . $e->getMessage());
            Response::json(['success' => false, 'message' => trans('errors.general')], 500);
        }
    }

    /**
     * إعادة ترتيب الصور
     */
    public function reorderImages(int $carId): void
    {
        try {
            $this->checkPermission('cars.edit');
            $this->validateCSRF();

            $request = new Request();
            $order = $request->post('order', []);

            $success = $this->imageModel->reorder($carId, $order);

            if (!$success) {
                Response::json(['success' => false, 'message' => trans('cars.reorder_failed')]);
                return;
            }

            Response::json([
                'success' => true,
                'message' => trans('cars.images_reordered_successfully')
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في reorderImages: " . $e->getMessage());
            Response::json(['success' => false, 'message' => trans('errors.general')], 500);
        }
    }

    // ====== إدارة العلامات التجارية والموديلات ======

    /**
     * عرض قائمة العلامات التجارية
     */
    public function brands(): void
    {
        try {
            $this->checkPermission('cars.manage_brands');

            $brands = $this->brandModel->getAllWithModelsCount();

            $this->view('backend/cars/brands', [
                'brands' => $brands,
                'page_title' => trans('cars.brands'),
                'breadcrumbs' => [
                    ['title' => trans('dashboard'), 'url' => '/admin'],
                    ['title' => trans('cars.management'), 'url' => '/admin/cars'],
                    ['title' => trans('cars.brands'), 'url' => ''],
                ]
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في brands: " . $e->getMessage());
            Session::setFlash('error', trans('errors.general'));
            Response::redirect('/admin/cars');
        }
    }

    /**
     * حفظ علامة تجارية جديدة
     */
    public function storeBrand(): void
    {
        try {
            $this->checkPermission('cars.manage_brands');
            $this->validateCSRF();

            $request = new Request();
            $data = [
                'name' => $request->post('name'),
                'is_active' => $request->post('is_active', 1)
            ];

            // التحقق
            if (empty($data['name'])) {
                Response::json(['success' => false, 'message' => trans('validation.required', ['field' => 'name'])]);
                return;
            }

            // رفع الشعار
            if (!empty($_FILES['logo']['name'])) {
                $uploader = new Uploader();
                $logoPath = $uploader->upload($_FILES['logo'], 'brands', ['jpg', 'jpeg', 'png', 'webp']);
                
                if ($logoPath) {
                    $data['logo'] = $logoPath;
                }
            }

            $brandId = $this->brandModel->create($data);

            if (!$brandId) {
                Response::json(['success' => false, 'message' => trans('cars.brand_create_failed')]);
                return;
            }

            Response::json([
                'success' => true,
                'message' => trans('cars.brand_created_successfully'),
                'brand_id' => $brandId
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في storeBrand: " . $e->getMessage());
            Response::json(['success' => false, 'message' => trans('errors.general')], 500);
        }
    }

    /**
     * تحديث علامة تجارية
     */
    public function updateBrand(int $id): void
    {
        try {
            $this->checkPermission('cars.manage_brands');
            $this->validateCSRF();

            $request = new Request();
            $data = [
                'name' => $request->post('name'),
                'is_active' => $request->post('is_active', 1)
            ];

            // رفع الشعار الجديد
            if (!empty($_FILES['logo']['name'])) {
                $uploader = new Uploader();
                $logoPath = $uploader->upload($_FILES['logo'], 'brands', ['jpg', 'jpeg', 'png', 'webp']);
                
                if ($logoPath) {
                    // حذف الشعار القديم
                    $oldBrand = $this->brandModel->find($id);
                    if ($oldBrand && $oldBrand['logo'] && file_exists($oldBrand['logo'])) {
                        @unlink($oldBrand['logo']);
                    }
                    
                    $data['logo'] = $logoPath;
                }
            }

            $success = $this->brandModel->update($id, $data);

            if (!$success) {
                Response::json(['success' => false, 'message' => trans('cars.brand_update_failed')]);
                return;
            }

            Response::json([
                'success' => true,
                'message' => trans('cars.brand_updated_successfully')
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في updateBrand: " . $e->getMessage());
            Response::json(['success' => false, 'message' => trans('errors.general')], 500);
        }
    }

    /**
     * حذف علامة تجارية
     */
    public function deleteBrand(int $id): void
    {
        try {
            $this->checkPermission('cars.manage_brands');
            $this->validateCSRF();

            $success = $this->brandModel->delete($id);

            if (!$success) {
                Response::json(['success' => false, 'message' => trans('cars.brand_delete_failed')]);
                return;
            }

            Response::json([
                'success' => true,
                'message' => trans('cars.brand_deleted_successfully')
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في deleteBrand: " . $e->getMessage());
            Response::json(['success' => false, 'message' => trans('errors.general')], 500);
        }
    }

    /**
     * الحصول على موديلات علامة تجارية (AJAX)
     */
    public function getModels(int $brandId): void
    {
        try {
            $models = $this->modelModel->getActiveByBrand($brandId);

            Response::json([
                'success' => true,
                'models' => $models
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في getModels: " . $e->getMessage());
            Response::json(['success' => false, 'message' => trans('errors.general')], 500);
        }
    }
    // ====== Helper Methods (Private) ======

    /**
     * التحقق من بيانات السيارة
     */
    private function validateCarData(array $data, ?int $carId = null): array
    {
        $validator = new Validator();

        // حقول مطلوبة
        $required = ['brand_id', 'model_id', 'plate_number', 'daily_rate'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'valid' => false,
                    'message' => trans('validation.required', ['field' => trans('cars.' . $field)])
                ];
            }
        }

        // التحقق من رقم اللوحة
        if ($this->carModel->plateNumberExists($data['plate_number'], $carId)) {
            return [
                'valid' => false,
                'message' => trans('cars.plate_number_exists')
            ];
        }

        // التحقق من رقم الشاسيه
        if (!empty($data['vin_number']) && $this->carModel->vinNumberExists($data['vin_number'], $carId)) {
            return [
                'valid' => false,
                'message' => trans('cars.vin_number_exists')
            ];
        }

        // التحقق من الأسعار
        if (!empty($data['daily_rate']) && !is_numeric($data['daily_rate'])) {
            return [
                'valid' => false,
                'message' => trans('validation.numeric', ['field' => trans('cars.daily_rate')])
            ];
        }

        return ['valid' => true];
    }

    /**
     * تحضير بيانات السيارة للحفظ
     */
    private function prepareCarData(array $data): array
    {
        $carData = [
            'branch_id' => $data['branch_id'] ?? null,
            'brand_id' => $data['brand_id'],
            'model_id' => $data['model_id'],
            'nickname' => $data['nickname'] ?? null,
            'vin_number' => $data['vin_number'] ?? null,
            'plate_number' => $data['plate_number'],
            'color' => $data['color'] ?? null,
            'manufacturing_year' => $data['manufacturing_year'] ?? null,
            'purchase_date' => $data['purchase_date'] ?? null,
            'purchase_price' => $data['purchase_price'] ?? null,
            'purchase_odometer' => $data['purchase_odometer'] ?? null,
            'current_odometer' => $data['current_odometer'] ?? $data['purchase_odometer'] ?? 0,
            'odometer_unit' => $data['odometer_unit'] ?? 'km',
            'previous_owner' => $data['previous_owner'] ?? null,
            'fuel_type' => $data['fuel_type'] ?? null,
            'vehicle_type' => $data['vehicle_type'] ?? 'private',
            'transmission' => $data['transmission'] ?? 'automatic',
            'engine_capacity' => $data['engine_capacity'] ?? null,
            'cylinders' => $data['cylinders'] ?? null,
            'seats' => $data['seats'] ?? 5,
            'doors' => $data['doors'] ?? 4,
            'tire_production_date' => $data['tire_production_date'] ?? null,
            'tire_front_size' => $data['tire_front_size'] ?? null,
            'tire_rear_size' => $data['tire_rear_size'] ?? null,
            'daily_rate' => $data['daily_rate'],
            'weekly_rate' => $data['weekly_rate'] ?? null,
            'monthly_rate' => $data['monthly_rate'] ?? null,
            'driver_daily_rate' => $data['driver_daily_rate'] ?? null,
            'insurance_company' => $data['insurance_company'] ?? null,
            'insurance_policy_number' => $data['insurance_policy_number'] ?? null,
            'insurance_expiry_date' => $data['insurance_expiry_date'] ?? null,
            'registration_expiry_date' => $data['registration_expiry_date'] ?? null,
            'last_maintenance_date' => $data['last_maintenance_date'] ?? null,
            'last_maintenance_odometer' => $data['last_maintenance_odometer'] ?? null,
            'maintenance_interval' => $data['maintenance_interval'] ?? 5000,
            'status' => $data['status'] ?? Car::STATUS_AVAILABLE,
            'is_featured' => isset($data['is_featured']) ? 1 : 0,
            'is_with_driver' => isset($data['is_with_driver']) ? 1 : 0,
            'notes' => $data['notes'] ?? null
        ];

        return $carData;
    }

    /**
     * معالجة رفع الصور
     */
    private function handleImageUpload(int $carId, array $files): int
    {
        $uploadedCount = 0;
        $uploader = new Uploader();

        // الحصول على عدد الصور الحالية
        $currentImagesCount = $this->imageModel->countCarImages($carId);
        $isPrimary = ($currentImagesCount == 0); // أول صورة تكون رئيسية

        // معالجة صفيفة الملفات
        $fileCount = count($files['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if (empty($files['name'][$i])) {
                continue;
            }

            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];

            $imagePath = $uploader->upload($file, 'cars/' . $carId, ['jpg', 'jpeg', 'png', 'webp']);

            if ($imagePath) {
                $imageData = [
                    'car_id' => $carId,
                    'image_path' => $imagePath,
                    'image_type' => CarImage::TYPE_EXTERIOR,
                    'is_primary' => $isPrimary ? 1 : 0,
                    'display_order' => $uploadedCount
                ];

                $imageId = $this->imageModel->create($imageData);
                
                if ($imageId) {
                    $uploadedCount++;
                    $isPrimary = false; // فقط الصورة الأولى رئيسية
                }
            }
        }

        return $uploadedCount;
    }

    /**
     * معالجة المميزات
     */
    private function handleFeatures(int $carId, array $features): void
    {
        $featureData = [];

        foreach ($features as $featureId) {
            $featureData[$featureId] = [
                'has_feature' => 1,
                'show_in_listing' => 1
            ];
        }

        $this->featureModel->attachToCar($carId, $featureData);
    }

    /**
     * الحصول على الفروع
     */
    private function getBranches(): array
    {
        try {
            $query = "SELECT id, name, address FROM branches WHERE is_active = 1 ORDER BY name ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getBranches: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على سجل الصيانة
     */
    private function getMaintenanceHistory(int $carId): array
    {
        try {
            $query = "SELECT * FROM car_maintenance 
                     WHERE car_id = ? 
                     ORDER BY maintenance_date DESC 
                     LIMIT 10";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$carId]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getMaintenanceHistory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على تاريخ التأجير
     */
    private function getRentalHistory(int $carId): array
    {
        try {
            $query = "SELECT r.*, c.full_name as customer_name, c.phone as customer_phone
                     FROM rentals r
                     INNER JOIN customers c ON r.customer_id = c.id
                     WHERE r.car_id = ? 
                     ORDER BY r.start_date DESC 
                     LIMIT 10";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$carId]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("خطأ في getRentalHistory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * التحقق من الصلاحية
     */
    private function checkPermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            Session::setFlash('error', trans('errors.no_permission'));
            Response::redirect('/admin');
            exit;
        }
    }

    /**
     * التحقق من CSRF Token
     */
    private function validateCSRF(): void
    {
        $request = new Request();
        $token = $request->post('csrf_token');
        
        if (!Session::validateCSRF($token)) {
            Session::setFlash('error', trans('errors.invalid_csrf'));
            Response::redirect($_SERVER['HTTP_REFERER'] ?? '/admin');
            exit;
        }
    }

    // ====== تقارير وإحصائيات ======

    /**
     * تقرير السيارات
     */
    public function report(): void
    {
        try {
            $this->checkPermission('reports.cars');

            $statistics = $this->carModel->getStatistics();
            $needsMaintenance = $this->carModel->getCarsNeedingMaintenance(20);
            $expiringDocuments = $this->carModel->getCarsWithExpiringDocuments(30);

            $this->view('backend/cars/report', [
                'statistics' => $statistics,
                'needs_maintenance' => $needsMaintenance,
                'expiring_documents' => $expiringDocuments,
                'page_title' => trans('reports.cars'),
                'breadcrumbs' => [
                    ['title' => trans('dashboard'), 'url' => '/admin'],
                    ['title' => trans('reports.reports'), 'url' => '/admin/reports'],
                    ['title' => trans('reports.cars'), 'url' => ''],
                ]
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في cars report: " . $e->getMessage());
            Session::setFlash('error', trans('errors.general'));
            Response::redirect('/admin');
        }
    }

    /**
     * تصدير قائمة السيارات (Excel/PDF)
     */
    public function export(): void
    {
        try {
            $this->checkPermission('cars.export');

            $request = new Request();
            $format = $request->get('format', 'excel');
            
            // الحصول على جميع السيارات
            $cars = $this->carModel->getAll([], 1, 9999)['data'];

            if ($format === 'pdf') {
                $this->exportToPDF($cars);
            } else {
                $this->exportToExcel($cars);
            }
        } catch (\Exception $e) {
            error_log("خطأ في export: " . $e->getMessage());
            Session::setFlash('error', trans('errors.general'));
            Response::redirect('/admin/cars');
        }
    }

    /**
     * التصدير إلى Excel
     */
    private function exportToExcel(array $cars): void
    {
        // سيتم تنفيذ هذا في Phase 14 (Reports System)
        // باستخدام PhpSpreadsheet
        Response::json(['message' => 'Excel export coming in Phase 14']);
    }

    /**
     * التصدير إلى PDF
     */
    private function exportToPDF(array $cars): void
    {
        // سيتم تنفيذ هذا في Phase 14 (Reports System)
        // باستخدام TCPDF أو similar
        Response::json(['message' => 'PDF export coming in Phase 14']);
    }

    /**
     * البحث المتقدم (AJAX)
     */
    public function search(): void
    {
        try {
            $request = new Request();
            
            $criteria = [
                'keyword' => $request->get('keyword'),
                'brand_id' => $request->get('brand_id'),
                'model_id' => $request->get('model_id'),
                'price_min' => $request->get('price_min'),
                'price_max' => $request->get('price_max'),
                'year_min' => $request->get('year_min'),
                'year_max' => $request->get('year_max'),
                'seats' => $request->get('seats'),
                'features' => $request->get('features', [])
            ];

            $cars = $this->carModel->advancedSearch($criteria);

            Response::json([
                'success' => true,
                'cars' => $cars,
                'count' => count($cars)
            ]);
        } catch (\Exception $e) {
            error_log("خطأ في search: " . $e->getMessage());
            Response::json(['success' => false, 'message' => trans('errors.general')], 500);
        }
    }
}

// تسجيل الملف في FileTracker
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 4');
