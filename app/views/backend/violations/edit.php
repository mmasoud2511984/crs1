<?php
/**
 * File: edit.php
 * Path: /app/views/backend/violations/edit.php
 * Purpose: نموذج تعديل مخالفة
 * Phase: Phase 8 - Violations & Reviews
 * Created: 2025-10-24
 */

use Core\Security;
use Core\FileTracker;

$pageTitle = trans('violations.edit_violation');
$breadcrumbs = [
    ['text' => trans('dashboard'), 'url' => '/admin'],
    ['text' => trans('violations.violations'), 'url' => '/admin/violations'],
    ['text' => trans('violations.edit_violation'), 'url' => '']
];
?>

<?php include viewPath('backend/layouts/header.php'); ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?= trans('violations.edit_violation') ?> #<?= Security::escape($violation['violation_number']) ?></h1>
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
            <form id="violationForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= Security::generateCSRF() ?>">
                <input type="hidden" name="_method" value="PUT">

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><?= trans('violations.basic_info') ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?= trans('violations.violation_number') ?> <span class="text-danger">*</span></label>
                                            <input type="text" name="violation_number" class="form-control" required
                                                   value="<?= Security::escape($violation['violation_number']) ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?= trans('violations.violation_date') ?> <span class="text-danger">*</span></label>
                                            <input type="datetime-local" name="violation_date" class="form-control" required
                                                   value="<?= date('Y-m-d\TH:i', strtotime($violation['violation_date'])) ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?= trans('violations.car') ?> <span class="text-danger">*</span></label>
                                            <select name="car_id" class="form-control" required>
                                                <?php foreach ($cars as $car): ?>
                                                    <option value="<?= $car['id'] ?>" <?= $car['id'] == $violation['car_id'] ? 'selected' : '' ?>>
                                                        <?= Security::escape($car['plate_number']) ?> - <?= Security::escape($car['brand_name'] . ' ' . $car['model_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?= trans('violations.customer') ?></label>
                                            <select name="customer_id" class="form-control">
                                                <option value=""><?= trans('common.not_assigned') ?></option>
                                                <?php foreach ($customers as $customer): ?>
                                                    <option value="<?= $customer['id'] ?>" <?= $customer['id'] == $violation['customer_id'] ? 'selected' : '' ?>>
                                                        <?= Security::escape($customer['full_name']) ?> - <?= Security::escape($customer['phone']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?= trans('violations.violation_type') ?> <span class="text-danger">*</span></label>
                                            <input type="text" name="violation_type" class="form-control" required
                                                   value="<?= Security::escape($violation['violation_type']) ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?= trans('violations.fine_amount') ?> <span class="text-danger">*</span></label>
                                            <input type="number" name="fine_amount" class="form-control" step="0.01" min="0" required
                                                   value="<?= $violation['fine_amount'] ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label><?= trans('violations.violation_location') ?></label>
                                    <textarea name="violation_location" class="form-control" rows="2"><?= Security::escape($violation['violation_location']) ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label><?= trans('violations.notes') ?></label>
                                    <textarea name="notes" class="form-control" rows="3"><?= Security::escape($violation['notes']) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><?= trans('violations.violation_status') ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label><?= trans('violations.status') ?> <span class="text-danger">*</span></label>
                                    <select name="status" id="status" class="form-control" required>
                                        <option value="pending" <?= $violation['status'] == 'pending' ? 'selected' : '' ?>><?= trans('violations.pending') ?></option>
                                        <option value="paid" <?= $violation['status'] == 'paid' ? 'selected' : '' ?>><?= trans('violations.paid') ?></option>
                                        <option value="disputed" <?= $violation['status'] == 'disputed' ? 'selected' : '' ?>><?= trans('violations.disputed') ?></option>
                                        <option value="cancelled" <?= $violation['status'] == 'cancelled' ? 'selected' : '' ?>><?= trans('violations.cancelled') ?></option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label><?= trans('violations.paid_by') ?> <span class="text-danger">*</span></label>
                                    <select name="paid_by" class="form-control" required>
                                        <option value="pending" <?= $violation['paid_by'] == 'pending' ? 'selected' : '' ?>><?= trans('violations.payment_pending') ?></option>
                                        <option value="company" <?= $violation['paid_by'] == 'company' ? 'selected' : '' ?>><?= trans('violations.company') ?></option>
                                        <option value="customer" <?= $violation['paid_by'] == 'customer' ? 'selected' : '' ?>><?= trans('violations.customer') ?></option>
                                    </select>
                                </div>

                                <div id="paymentFields" style="display: <?= $violation['status'] == 'paid' ? 'block' : 'none' ?>;">
                                    <div class="form-group">
                                        <label><?= trans('violations.payment_date') ?></label>
                                        <input type="datetime-local" name="payment_date" class="form-control"
                                               value="<?= !empty($violation['payment_date']) ? date('Y-m-d\TH:i', strtotime($violation['payment_date'])) : '' ?>">
                                    </div>

                                    <div class="form-group">
                                        <label><?= trans('violations.payment_reference') ?></label>
                                        <input type="text" name="payment_reference" class="form-control"
                                               value="<?= Security::escape($violation['payment_reference']) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><?= trans('violations.documents') ?></h3>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($violation['document_path'])): ?>
                                    <div class="mb-3">
                                        <a href="/<?= Security::escape($violation['document_path']) ?>" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-file"></i> <?= trans('violations.view_document') ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <div class="form-group">
                                    <label><?= trans('violations.upload_new_document') ?></label>
                                    <div class="custom-file">
                                        <input type="file" name="document" class="custom-file-input" id="documentFile" accept=".pdf,.jpg,.jpeg,.png">
                                        <label class="custom-file-label" for="documentFile"><?= trans('common.choose_file') ?></label>
                                    </div>
                                    <small class="text-muted"><?= trans('violations.allowed_formats') ?>: PDF, JPG, PNG (Max 5MB)</small>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="fas fa-save"></i> <?= trans('common.update') ?>
                                </button>
                                <a href="/admin/violations/<?= $violation['id'] ?>" class="btn btn-secondary btn-block">
                                    <i class="fas fa-times"></i> <?= trans('common.cancel') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
document.getElementById('status').addEventListener('change', function() {
    const paymentFields = document.getElementById('paymentFields');
    paymentFields.style.display = this.value === 'paid' ? 'block' : 'none';
});

document.querySelector('.custom-file-input').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || '<?= trans('common.choose_file') ?>';
    e.target.nextElementSibling.textContent = fileName;
});

document.getElementById('violationForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= trans('common.saving') ?>...';

    fetch('/admin/violations/<?= $violation['id'] ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        } else {
            alert(data.message);
        }
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?= trans('error.something_went_wrong') ?>');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});
</script>

<?php 
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 8');
include viewPath('backend/layouts/footer.php'); 
?>
