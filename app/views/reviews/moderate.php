<?php
/**
 * File: moderate.php
 * Path: /app/views/backend/reviews/moderate.php
 * Purpose: صفحة إدارة والموافقة على التقييمات
 * Phase: Phase 8 - Violations & Reviews
 * Created: 2025-10-24
 */

use Core\Security;
use Core\FileTracker;

$pageTitle = trans('reviews.moderate_reviews');
$breadcrumbs = [
    ['text' => trans('dashboard'), 'url' => '/admin'],
    ['text' => trans('reviews.reviews'), 'url' => '/admin/reviews'],
    ['text' => trans('reviews.moderate'), 'url' => '']
];
?>

<?php include viewPath('backend/layouts/header.php'); ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?= trans('reviews.moderate_reviews') ?></h1>
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

            <!-- Pending Reviews -->
            <div class="card">
                <div class="card-header bg-warning">
                    <h3 class="card-title">
                        <i class="fas fa-clock"></i> <?= trans('reviews.pending_approval') ?> (<?= count($pendingReviews) ?>)
                    </h3>
                    <?php if (!empty($pendingReviews)): ?>
                    <div class="card-tools">
                        <button type="button" class="btn btn-success btn-sm" onclick="bulkApprove()">
                            <i class="fas fa-check"></i> <?= trans('reviews.approve_selected') ?>
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="bulkReject()">
                            <i class="fas fa-times"></i> <?= trans('reviews.reject_selected') ?>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingReviews)): ?>
                        <p class="text-center text-muted"><?= trans('reviews.no_pending_reviews') ?></p>
                    <?php else: ?>
                        <form id="bulkActionForm">
                            <input type="hidden" name="csrf_token" value="<?= Security::generateCSRF() ?>">
                            <?php foreach ($pendingReviews as $review): ?>
                                <div class="card mb-3 border-warning">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-1 text-center">
                                                <div class="icheck-primary">
                                                    <input type="checkbox" class="review-checkbox" name="ids[]" value="<?= $review['id'] ?>" id="review-<?= $review['id'] ?>">
                                                    <label for="review-<?= $review['id'] ?>"></label>
                                                </div>
                                            </div>

                                            <div class="col-md-8">
                                                <h5 class="mb-1"><?= Security::escape($review['review_title'] ?? trans('reviews.no_title')) ?></h5>
                                                <div class="text-warning mb-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?= $i <= $review['rating'] ? '' : '-o' ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ml-2"><?= $review['rating'] ?>/5</span>
                                                </div>

                                                <p class="mb-2"><?= nl2br(Security::escape($review['review_text'])) ?></p>

                                                <small class="text-muted">
                                                    <i class="fas fa-user"></i> <?= Security::escape($review['customer_name']) ?> |
                                                    <i class="fas fa-car"></i> <?= Security::escape($review['car_full_name']) ?> (<?= Security::escape($review['plate_number']) ?>) |
                                                    <i class="fas fa-clock"></i> <?= date('Y-m-d H:i', strtotime($review['created_at'])) ?>
                                                </small>
                                            </div>

                                            <div class="col-md-3 text-right">
                                                <div class="btn-group-vertical btn-block">
                                                    <button type="button" class="btn btn-success btn-sm" onclick="approveReview(<?= $review['id'] ?>)">
                                                        <i class="fas fa-check"></i> <?= trans('reviews.approve') ?>
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="rejectReview(<?= $review['id'] ?>)">
                                                        <i class="fas fa-times"></i> <?= trans('reviews.reject') ?>
                                                    </button>
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="showResponseModal(<?= $review['id'] ?>)">
                                                        <i class="fas fa-reply"></i> <?= trans('reviews.respond') ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews Without Response -->
            <div class="card">
                <div class="card-header bg-info">
                    <h3 class="card-title">
                        <i class="fas fa-reply"></i> <?= trans('reviews.need_response') ?> (<?= count($reviewsWithoutResponse) ?>)
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($reviewsWithoutResponse)): ?>
                        <p class="text-center text-muted"><?= trans('reviews.no_reviews_need_response') ?></p>
                    <?php else: ?>
                        <?php foreach ($reviewsWithoutResponse as $review): ?>
                            <div class="card mb-3 border-info">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-9">
                                            <h5 class="mb-1"><?= Security::escape($review['review_title'] ?? trans('reviews.no_title')) ?></h5>
                                            <div class="text-warning mb-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?= $i <= $review['rating'] ? '' : '-o' ?>"></i>
                                                <?php endfor; ?>
                                                <span class="ml-2"><?= $review['rating'] ?>/5</span>
                                            </div>

                                            <p class="mb-2"><?= nl2br(Security::escape($review['review_text'])) ?></p>

                                            <small class="text-muted">
                                                <i class="fas fa-user"></i> <?= Security::escape($review['customer_name']) ?> |
                                                <i class="fas fa-car"></i> <?= Security::escape($review['car_full_name']) ?> (<?= Security::escape($review['plate_number']) ?>) |
                                                <i class="fas fa-clock"></i> <?= date('Y-m-d H:i', strtotime($review['created_at'])) ?>
                                            </small>
                                        </div>

                                        <div class="col-md-3 text-right">
                                            <button type="button" class="btn btn-primary btn-block btn-sm" onclick="showResponseModal(<?= $review['id'] ?>)">
                                                <i class="fas fa-reply"></i> <?= trans('reviews.add_response') ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- Response Modal -->
<div class="modal fade" id="responseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
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
                        <textarea name="response_text" class="form-control" rows="5" required 
                                  placeholder="<?= trans('reviews.response_placeholder') ?>"></textarea>
                        <small class="text-muted"><?= trans('reviews.response_hint') ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> <?= trans('common.cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= trans('common.save') ?>
                    </button>
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
    document.getElementById('responseForm').reset();
    $('#responseModal').modal('show');
}

document.getElementById('responseForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const reviewId = document.getElementById('responseReviewId').value;
    const submitBtn = this.querySelector('button[type="submit"]');
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= trans('common.saving') ?>...';

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
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> <?= trans('common.save') ?>';
        }
    });
});

function bulkApprove() {
    const checked = document.querySelectorAll('.review-checkbox:checked');
    if (checked.length === 0) {
        alert('<?= trans('reviews.select_reviews') ?>');
        return;
    }

    if (!confirm('<?= trans('reviews.confirm_bulk_approve') ?>')) return;

    const ids = Array.from(checked).map(cb => cb.value);

    fetch('/admin/reviews/bulk-approve', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= Security::generateCSRF() ?>'
        },
        body: JSON.stringify({
            csrf_token: '<?= Security::generateCSRF() ?>',
            ids: ids
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

function bulkReject() {
    const checked = document.querySelectorAll('.review-checkbox:checked');
    if (checked.length === 0) {
        alert('<?= trans('reviews.select_reviews') ?>');
        return;
    }

    if (!confirm('<?= trans('reviews.confirm_bulk_reject') ?>')) return;

    const ids = Array.from(checked).map(cb => cb.value);

    fetch('/admin/reviews/bulk-reject', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= Security::generateCSRF() ?>'
        },
        body: JSON.stringify({
            csrf_token: '<?= Security::generateCSRF() ?>',
            ids: ids
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

// Select All Checkbox
document.addEventListener('DOMContentLoaded', function() {
    const selectAllBtn = document.createElement('button');
    selectAllBtn.type = 'button';
    selectAllBtn.className = 'btn btn-sm btn-outline-secondary ml-2';
    selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i> <?= trans('common.select_all') ?>';
    selectAllBtn.onclick = function() {
        const checkboxes = document.querySelectorAll('.review-checkbox');
        checkboxes.forEach(cb => cb.checked = true);
    };

    const header = document.querySelector('.bg-warning .card-tools');
    if (header && document.querySelectorAll('.review-checkbox').length > 0) {
        header.insertBefore(selectAllBtn, header.firstChild);
    }
});
</script>

<?php 
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 8');
include viewPath('backend/layouts/footer.php'); 
?>
