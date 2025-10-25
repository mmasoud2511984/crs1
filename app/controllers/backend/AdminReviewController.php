<?php
/**
 * File: AdminReviewController.php
 * Path: /app/controllers/backend/AdminReviewController.php
 * Purpose: إدارة تقييمات العملاء في لوحة التحكم
 * Dependencies: Review Model, Car Model, Rental Model, Customer Model
 * Phase: Phase 8 - Violations & Reviews
 * Created: 2025-10-24
 */

namespace App\Controllers\Backend;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Security;
use Core\Validator;
use Core\FileTracker;
use App\Models\Review;
use App\Models\Car;
use App\Models\Rental;
use App\Models\Customer;

class AdminReviewController extends Controller
{
    /**
     * عرض قائمة التقييمات
     */
    public function index(Request $request)
    {
        // التحقق من الصلاحيات
        if (!auth()->check() || !auth()->hasPermission('reviews.view')) {
            return redirect('/admin/login');
        }

        $page = $request->get('page', 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // الفلاتر
        $filters = [
            'is_approved' => $request->get('is_approved') !== null ? (int)$request->get('is_approved') : null,
            'rating' => $request->get('rating'),
            'car_id' => $request->get('car_id'),
            'customer_id' => $request->get('customer_id'),
            'has_response' => $request->get('has_response') !== null ? (bool)$request->get('has_response') : null,
            'search' => $request->get('search'),
            'limit' => $perPage,
            'offset' => $offset
        ];

        // الحصول على البيانات
        $reviews = Review::getAll($filters);
        $total = Review::count($filters);
        $totalPages = ceil($total / $perPage);

        // الإحصائيات
        $statistics = Review::getStatistics();

        // السيارات للفلتر
        $cars = Car::getActive();

        FileTracker::logModify(__FILE__, 55, FileTracker::countLines(__FILE__), 'Phase 8');

        return $this->view('backend/reviews/index', [
            'reviews' => $reviews,
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
     * عرض صفحة الموافقة على التقييمات
     */
    public function moderate(Request $request)
    {
        if (!auth()->check() || !auth()->hasPermission('reviews.moderate')) {
            return redirect('/admin/login');
        }

        // التقييمات المعلقة
        $pendingReviews = Review::getPending(50);

        // التقييمات بدون رد
        $reviewsWithoutResponse = Review::getWithoutResponse(50);

        return $this->view('backend/reviews/moderate', [
            'pendingReviews' => $pendingReviews,
            'reviewsWithoutResponse' => $reviewsWithoutResponse
        ]);
    }

    /**
     * عرض تفاصيل تقييم
     */
    public function show(Request $request, $id)
    {
        if (!auth()->check() || !auth()->hasPermission('reviews.view')) {
            return redirect('/admin/login');
        }

        $review = Review::getById($id);

        if (!$review) {
            session()->setFlash('error', trans('review.not_found'));
            return redirect('/admin/reviews');
        }

        return $this->view('backend/reviews/view', [
            'review' => $review
        ]);
    }

    /**
     * الموافقة على تقييم
     */
    public function approve(Request $request, $id)
    {
        if (!auth()->check() || !auth()->hasPermission('reviews.moderate')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        // التحقق من CSRF
        if (!Security::validateCSRF($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
        }

        try {
            $review = Review::find($id);
            if (!$review) {
                return Response::json(['success' => false, 'message' => trans('review.not_found')], 404);
            }

            Review::approve($id, auth()->id());

            // إرسال إشعار للعميل
            // TODO: إرسال إشعار بالموافقة على التقييم

            return Response::json([
                'success' => true,
                'message' => trans('review.approved_successfully')
            ]);

        } catch (\Exception $e) {
            error_log("Error approving review: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => trans('error.something_went_wrong')
            ], 500);
        }
    }

    /**
     * رفض تقييم (إلغاء الموافقة)
     */
    public function reject(Request $request, $id)
    {
        if (!auth()->check() || !auth()->hasPermission('reviews.moderate')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        // التحقق من CSRF
        if (!Security::validateCSRF($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
        }

        try {
            $review = Review::find($id);
            if (!$review) {
                return Response::json(['success' => false, 'message' => trans('review.not_found')], 404);
            }

            Review::reject($id);

            return Response::json([
                'success' => true,
                'message' => trans('review.rejected_successfully')
            ]);

        } catch (\Exception $e) {
            error_log("Error rejecting review: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => trans('error.something_went_wrong')
            ], 500);
        }
    }

    /**
     * إضافة رد على تقييم
     */
    public function respond(Request $request, $id)
    {
        if (!auth()->check() || !auth()->hasPermission('reviews.respond')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        // التحقق من CSRF
        if (!Security::validateCSRF($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
        }

        // التحقق من البيانات
        $rules = [
            'response_text' => 'required|min:10'
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
            $review = Review::find($id);
            if (!$review) {
                return Response::json(['success' => false, 'message' => trans('review.not_found')], 404);
            }

            $responseText = Security::sanitize($request->post('response_text'));
            Review::addResponse($id, $responseText, auth()->id());

            // إرسال إشعار للعميل
            // TODO: إرسال إشعار بالرد على التقييم

            return Response::json([
                'success' => true,
                'message' => trans('review.response_added_successfully')
            ]);

        } catch (\Exception $e) {
            error_log("Error responding to review: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => trans('error.something_went_wrong')
            ], 500);
        }
    }

    /**
     * حذف رد من تقييم
     */
    public function removeResponse(Request $request, $id)
    {
        if (!auth()->check() || !auth()->hasPermission('reviews.respond')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        // التحقق من CSRF
        if (!Security::validateCSRF($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
        }

        try {
            $review = Review::find($id);
            if (!$review) {
                return Response::json(['success' => false, 'message' => trans('review.not_found')], 404);
            }

            Review::removeResponse($id);

            return Response::json([
                'success' => true,
                'message' => trans('review.response_removed_successfully')
            ]);

        } catch (\Exception $e) {
            error_log("Error removing response: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => trans('error.something_went_wrong')
            ], 500);
        }
    }

    /**
     * حذف تقييم
     */
    public function destroy(Request $request, $id)
    {
        if (!auth()->check() || !auth()->hasPermission('reviews.delete')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        // التحقق من CSRF
        if (!Security::validateCSRF($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
        }

        try {
            $deleted = Review::deleteById($id);

            if ($deleted) {
                return Response::json([
                    'success' => true,
                    'message' => trans('review.deleted_successfully')
                ]);
            } else {
                return Response::json([
                    'success' => false,
                    'message' => trans('review.not_found')
                ], 404);
            }

        } catch (\Exception $e) {
            error_log("Error deleting review: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => trans('error.something_went_wrong')
            ], 500);
        }
    }

    /**
     * الموافقة على تقييمات متعددة
     */
    public function bulkApprove(Request $request)
    {
        if (!auth()->check() || !auth()->hasPermission('reviews.moderate')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        // التحقق من CSRF
        if (!Security::validateCSRF($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
        }

        $ids = $request->post('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return Response::json(['success' => false, 'message' => trans('error.no_items_selected')], 400);
        }

        try {
            $approved = 0;
            foreach ($ids as $id) {
                if (Review::find($id)) {
                    Review::approve($id, auth()->id());
                    $approved++;
                }
            }

            return Response::json([
                'success' => true,
                'message' => trans('review.bulk_approved_successfully', ['count' => $approved])
            ]);

        } catch (\Exception $e) {
            error_log("Error bulk approving reviews: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => trans('error.something_went_wrong')
            ], 500);
        }
    }

    /**
     * رفض تقييمات متعددة
     */
    public function bulkReject(Request $request)
    {
        if (!auth()->check() || !auth()->hasPermission('reviews.moderate')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        // التحقق من CSRF
        if (!Security::validateCSRF($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
        }

        $ids = $request->post('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return Response::json(['success' => false, 'message' => trans('error.no_items_selected')], 400);
        }

        try {
            $rejected = 0;
            foreach ($ids as $id) {
                if (Review::find($id)) {
                    Review::reject($id);
                    $rejected++;
                }
            }

            return Response::json([
                'success' => true,
                'message' => trans('review.bulk_rejected_successfully', ['count' => $rejected])
            ]);

        } catch (\Exception $e) {
            error_log("Error bulk rejecting reviews: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => trans('error.something_went_wrong')
            ], 500);
        }
    }

    /**
     * حذف تقييمات متعددة
     */
    public function bulkDelete(Request $request)
    {
        if (!auth()->check() || !auth()->hasPermission('reviews.delete')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        // التحقق من CSRF
        if (!Security::validateCSRF($request->post('csrf_token'))) {
            return Response::json(['success' => false, 'message' => trans('error.invalid_token')], 403);
        }

        $ids = $request->post('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return Response::json(['success' => false, 'message' => trans('error.no_items_selected')], 400);
        }

        try {
            $deleted = 0;
            foreach ($ids as $id) {
                if (Review::deleteById($id)) {
                    $deleted++;
                }
            }

            return Response::json([
                'success' => true,
                'message' => trans('review.bulk_deleted_successfully', ['count' => $deleted])
            ]);

        } catch (\Exception $e) {
            error_log("Error bulk deleting reviews: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => trans('error.something_went_wrong')
            ], 500);
        }
    }

    /**
     * إحصائيات التقييمات
     */
    public function statistics(Request $request)
    {
        if (!auth()->check() || !auth()->hasPermission('reviews.view')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        $filters = [
            'car_id' => $request->get('car_id'),
            'is_approved' => $request->get('is_approved')
        ];

        $statistics = Review::getStatistics($filters);

        return Response::json([
            'success' => true,
            'data' => $statistics
        ]);
    }

    /**
     * توزيع التقييمات
     */
    public function ratingDistribution(Request $request)
    {
        if (!auth()->check() || !auth()->hasPermission('reviews.view')) {
            return Response::json(['success' => false, 'message' => trans('error.unauthorized')], 403);
        }

        $carId = $request->get('car_id');
        $distribution = Review::getRatingDistribution($carId);

        return Response::json([
            'success' => true,
            'data' => $distribution
        ]);
    }

    /**
     * تصدير التقييمات (Excel/PDF)
     */
    public function export(Request $request)
    {
        if (!auth()->check() || !auth()->hasPermission('reviews.view')) {
            return redirect('/admin/login');
        }

        $format = $request->get('format', 'excel'); // excel or pdf
        
        $filters = [
            'is_approved' => $request->get('is_approved'),
            'rating' => $request->get('rating'),
            'car_id' => $request->get('car_id'),
            'customer_id' => $request->get('customer_id'),
            'has_response' => $request->get('has_response'),
            'search' => $request->get('search')
        ];

        $reviews = Review::getAll($filters);

        // TODO: تنفيذ التصدير حسب الصيغة المطلوبة
        // سيتم تنفيذه في مرحلة التقارير

        session()->setFlash('info', trans('feature.coming_soon'));
        return redirect('/admin/reviews');
    }
}

// File Tracking
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 8');
