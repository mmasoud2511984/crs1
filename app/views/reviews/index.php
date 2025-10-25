<?php
/**
 * File: index.php
 * Path: /app/views/backend/reviews/index.php
 * Purpose: صفحة قائمة التقييمات
 * Phase: Phase 8 - Violations & Reviews
 * Created: 2025-10-24
 */

use Core\Security;
use Core\FileTracker;

$pageTitle = trans('reviews.reviews_management');
$breadcrumbs = [
    ['text' => trans('dashboard'), 'url' => '/admin'],
    ['text' => trans('reviews.reviews'), 'url' => '']
];
?>

<?php include viewPath('backend/layouts/header.php'); ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?= trans('reviews.reviews_management') ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-left">
                        <?php foreach ($breadcrumbs as $crumb): ?>
                            <?php if (!empty($crumb['url'])): ?>
                                <li class="breadcrumb-item"><a href="<?= $crumb['url'] ?>"><?= $crumb['text'] ?></a></li>
                            <?php else: ?>
                                <li class="breadcrumb-item active"><?= $crumb['text'] ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= $statistics['total_reviews'] ?? 0 ?></h3>
                            <p><?= trans('reviews.total_reviews') ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= $statistics['pending'] ?? 0 ?></h3>
                            <p><?= trans('reviews.pending_approval') ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= $statistics['approved'] ?? 0 ?></h3>
                            <p><?= trans('reviews.approved') ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3><?= number_format($statistics['average_rating'] ?? 0, 1) ?></h3>
                            <p><?= trans('reviews.average_rating') ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= trans('reviews.filter_search') ?></h3>
                    <div class="card-tools">
                        <?php if (auth()->hasPermission('reviews.moderate')): ?>
                            <a href="/admin/reviews/moderate" class="btn btn-warning btn-sm">
                                <i class="fas fa-tasks"></i> <?= trans('reviews.moderate_reviews') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="/admin/reviews" id="filterForm">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label><?= trans('reviews.approval_status') ?></label>
                                    <select name="is_approved" class="form-control form-control-sm">
                                        <option value=""><?= trans('common.all') ?></option>
                                        <option value="1" <?= ($filters['is_approved'] ?? null) === 1 ? 'selected' : '' ?>><?= trans('reviews.approved') ?></option>
                                        <option value="0" <?= ($filters['is_approved'] ?? null) === 0 ? 'selected' : '' ?>><?= trans('reviews.pending') ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label><?= trans('reviews.rating') ?></label>
                                    <select name="rating" class="form-control form-control-sm">
                                        <option value=""><?= trans('common.all') ?></option>
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <option value="<?= $i ?>" <?= ($filters['rating'] ?? '') == $i ? 'selected' : '' ?>>
                                                <?= $i ?> <?= trans('reviews.stars') ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label><?= trans('reviews.response_status') ?></label>
                                    <select name="has_response" class="form-control form-control-sm">
                                        <option value=""><?= trans('common.all') ?></option>
                                        <option value="1" <?= ($filters['has_response'] ?? null) === true ? 'selected' : '' ?>><?= trans('reviews.with_response') ?></option>
                                        <option value="0" <?= ($filters['has_response'] ?? null) === false ? 'selected' : '' ?>><?= trans('reviews.without_response') ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?= trans('common.search') ?></label>
                                    <input type="text" name="search" class="form-control form-control-sm" 
                                           placeholder="<?= trans('reviews.search_placeholder') ?>" 
                                           value="<?= Security::escape($filters['search'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-sm btn-block">
                                        <i class="fas fa-search"></i> <?= trans('common.search') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Reviews List -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= trans('reviews.reviews_list') ?> (<?= $total ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($reviews)): ?>
                        <p class="text-center text-muted"><?= trans('reviews.no_reviews_found') ?></p>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h5 class="mb-1"><?= Security::escape($review['review_title'] ?? trans('reviews.no_title')) ?></h5>
                                                    <div class="text-warning mb-2">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?= $i <= $review['rating'] ? '' : '-o' ?>"></i>
                                                        <?php endfor; ?>
                                                        <span class="ml-2"><?= $review['rating'] ?>/5</span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <?php if ($review['is_approved']): ?>
                                                        <span class="badge badge-success"><?= trans('reviews.approved') ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning"><?= trans('reviews.pending') ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <p class="mb-2"><?= nl2br(Security::escape($review['review_text'])) ?></p>

                                            <small class="text-muted">
                                                <strong><?= Security::escape($review['customer_name']) ?></strong>
                                                <?= trans('reviews.for_car') ?> 
                                                <strong><?= Security::escape($review['car_full_name']) ?></strong>
                                                (<?= Security::escape($review['plate_number']) ?>)
                                                <br>
                                                <?= date('Y-m-d H:i', strtotime($review['created_at'])) ?>
                                            </small>

                                            <?php if (!empty($review['response_text'])): ?>
                                                <div class="alert alert-light mt-3 mb-0">
                                                    <strong><?= trans('reviews.management_response') ?>:</strong><br>
                                                    <?= nl2br(Security::escape($review['response_text'])) ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?= trans('common.by') ?> <?= Security::escape($review['responded_by_name']) ?> -
                                                        <?= date('Y-m-d H:i', strtotime($review['responded_at'])) ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="col-md-4 text-right">
                                            <div class="btn-group-vertical btn-block">
                                                <?php if (!$review['is_approved'] && auth()->hasPermission('reviews.moderate')): ?>
                                                    <button type="button" class="btn btn-success btn-sm" onclick="approveReview(<?= $review['id'] ?>)">
                                                        <i class="fas fa-check"></i> <?= trans('reviews.approve') ?>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($review['is_approved'] && auth()->hasPermission('reviews.moderate')): ?>
                                                    <button type="button" class="btn btn-warning btn-sm" onclick="rejectReview(<?= $review['id'] ?>)">
                                                        <i class="fas fa-times"></i> <?= trans('reviews.unapprove') ?>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if (auth()->hasPermission('reviews.respond')): ?>
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="showResponseModal(<?= $review['id'] ?>)">
                                                        <i class="fas fa-reply"></i> <?= trans('reviews.respond') ?>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if (auth()->hasPermission('reviews.delete')): ?>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteReview(<?= $review['id'] ?>)">
                                                        <i class="fas fa-trash"></i> <?= trans('common.delete') ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="card-footer clearfix">
                        <?php include viewPath('backend/layouts/pagination.php'); ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </section>
</div>

<!-- Response Modal -->
<div class="modal fade" id="responseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= trans('reviews.add_response') ?></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="responseForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= Security::generateCSRF() ?>">
                    <input type="hidden" name="review_id" id="responseReviewId">
                    <div class="form-group">
                        <label><?= trans('reviews.response_text') ?> <span class="text-danger">*</span></label>
                        <textarea name="response_text" class="form-control" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= trans('common.cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= trans('common.save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveReview(id) {
    if (!confirm('<?= trans('reviews.confirm_approve') ?>')) return;

    fetch('/admin/reviews/' + id + '/approve', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= Security::generateCSRF() ?>'
        },
        body: JSON.stringify({
            csrf_token: '<?= Security::generateCSRF() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    });
}

function rejectReview(id) {
    if (!confirm('<?= trans('reviews.confirm_reject') ?>')) return;

    fetch('/admin/reviews/' + id + '/reject', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= Security::generateCSRF() ?>'
        },
        body: JSON.stringify({
            csrf_token: '<?= Security::generateCSRF() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    });
}

function showResponseModal(id) {
    document.getElementById('responseReviewId').value = id;
    $('#responseModal').modal('show');
}

document.getElementById('responseForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const reviewId = document.getElementById('responseReviewId').value;

    fetch('/admin/reviews/' + reviewId + '/respond', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    });
});

function deleteReview(id) {
    if (!confirm('<?= trans('reviews.confirm_delete') ?>')) return;

    fetch('/admin/reviews/' + id, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= Security::generateCSRF() ?>'
        },
        body: JSON.stringify({
            csrf_token: '<?= Security::generateCSRF() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    });
}
</script>

<?php 
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 8');
include viewPath('backend/layouts/footer.php'); 
?>
