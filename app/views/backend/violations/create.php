<?php
/**
 * File: create.php
 * Path: /app/views/backend/violations/create.php
 * Purpose: نموذج إضافة مخالفة جديدة
 * Phase: Phase 8 - Violations & Reviews
 * Created: 2025-10-24
 */

use Core\Security;
use Core\FileTracker;

$pageTitle = trans('violations.add_violation');
$breadcrumbs = [
    ['text' => trans('dashboard'), 'url' => '/admin'],
    ['text' => trans('violations.violations'), 'url' => '/admin/violations'],
    ['text' => trans('violations.add_violation'), 'url' => '']
];
?>

<?php include viewPath('backend/layouts/header.php'); ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?= trans('violations.add_violation') ?></h1>
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

                <div class="row">
                    <div class="col-md-8">
                        <!-- معلومات أساسية -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><?= trans('violations.basic_info') ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?= trans('violations.violation_number') ?></label>
                                            <input type="text" name="violation_number" class="form-control" 
                                                   placeholder="<?= trans('violations.auto_generated') ?>" 
                                                   value="<?= $rental['violation_number'] ?? '' ?>">
                                            <small class="text-muted"><?= trans('violations.leave_empty_auto') ?></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?= trans('violations.violation_date') ?> <span class="text-danger">*</span></label>
                                            <input type="datetime-local" name="violation_date" class="form-control" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?= trans('violations.car') ?> <span class="text-danger">*</span></label>
                                            <select name="car_id" id="car_id" class="form-control" required <?= isset($rental) ? 'disabled' : '' ?>>
                                                <option value=""><?= trans('common.select') ?></option>
                                                <?php foreach ($cars as $car): ?>
                                                    <option value="<?= $car['id'] ?>" 
                                                            <?= isset($rental) && $rental['car_id'] == $car['id'] ? 'selected' : '' ?>>
                                                        <?= Security::escape($car['plate_number']) ?> - <?= Security::escape($car['brand_name'] . ' ' . $car['model_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($rental)): ?>
                                                <input type="hidden" name="car_id" value="<?= $rental['car_id'] ?>">
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?= trans('violations.rental_contract') ?></label>
                                            <select name="rental_id" id="rental_id" class="form-control" <?= isset($rental) ? 'disabled' : '' ?>>
                                                <option value=""><?= trans('common.not_related') ?></option>
                                                <?php if (isset($rental)): ?>
                                                    <option value="<?= $rental['id'] ?>" selected>
                                                        <?= Security::escape($rental['rental_number']) ?>
                                                    </option>
                                                    <input type="hidden" name="rental_id" value="<?= $rental['id'] ?>">
                                                    <input type="hidden" name="customer_id" value="<?= $rental['customer_id'] ?>">
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!isset($rental)): ?>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label><?= trans('violations.customer') ?></label>
                                            <select name="customer_id" id="customer_id" class="form-control">
                                                <option value=""><?= trans('common.select') ?></option>
                                                <?php foreach ($customers as $customer): ?>
                                                    <option value="<?= $customer['id'] ?>">
                                                        <?= Security::escape($customer['full_name']) ?> - <?= Security::escape($customer['phone']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted"><?= trans('violations.auto_from_rental') ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?= trans('violations.violation_type') ?> <span class="text-danger">*</span></label>
                                            <input type="text" name="violation_type" class="form-control" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?= trans('violations.fine_amount') ?> <span class="text-danger">*</span></label>
                                            <input type="number" name="fine_amount" class="form-control" step="0.01" min="0" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label><?= trans('violations.violation_location') ?></label>
                                    <textarea name="violation_location" class="form-control" rows="2"></textarea>
                                </div>

                                <div class="form-group">
                                    <label><?= trans('violations.notes') ?></label>
                                    <textarea name="notes" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- حالة المخالفة -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><?= trans('violations.violation_status') ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label><?= trans('violations.status') ?> <span class="text-danger">*</span></label>
                                    <select name="status" id="status" class="form-control" required>
                                        <option value="pending"><?= trans('violations.pending') ?></option>
                                        <option value="paid"><?= trans('violations.paid') ?></option>
                                        <option value="disputed"><?= trans('violations.disputed') ?></option>
                                        <option value="cancelled"><?= trans('violations.cancelled') ?></option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label><?= trans('violations.paid_by') ?> <span class="text-danger">*</span></label>
                                    <select name="paid_by" class="form-control" required>
                                        <option value="pending"><?= trans('violations.payment_pending') ?></option>
                                        <option value="company"><?= trans('violations.company') ?></option>
                                        <option value="customer"><?= trans('violations.customer') ?></option>
                                    </select>
                                </div>

                                <div id="paymentFields" style="display: none;">
                                    <div class="form-group">
                                        <label><?= trans('violations.payment_date') ?></label>
                                        <input type="datetime-local" name="payment_date" class="form-control">
                                    </div>

                                    <div class="form-group">
                                        <label><?= trans('violations.payment_reference') ?></label>
                                        <input type="text" name="payment_reference" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- المستندات -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><?= trans('violations.documents') ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label><?= trans('violations.violation_document') ?></label>
                                    <div class="custom-file">
                                        <input type="file" name="document" class="custom-file-input" id="documentFile" accept=".pdf,.jpg,.jpeg,.png">
                                        <label class="custom-file-label" for="documentFile"><?= trans('common.choose_file') ?></label>
                                    </div>
                                    <small class="text-muted"><?= trans('violations.allowed_formats') ?>: PDF, JPG, PNG (<?= trans('common.max') ?> 5MB)</small>
                                </div>
                            </div>
                        </div>

                        <!-- الأزرار -->
                        <div class="card">
                            <div class="card-body">
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="fas fa-save"></i> <?= trans('common.save') ?>
                                </button>
                                <a href="/admin/violations" class="btn btn-secondary btn-block">
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
// عرض حقول الدفع عند تغيير الحالة
document.getElementById('status').addEventListener('change', function() {
    const paymentFields = document.getElementById('paymentFields');
    if (this.value === 'paid') {
        paymentFields.style.display = 'block';
    } else {
        paymentFields.style.display = 'none';
    }
});

// تحديث label الملف
document.querySelector('.custom-file-input').addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || '<?= trans('common.choose_file') ?>';
    e.target.nextElementSibling.textContent = fileName;
});

// إرسال النموذج
document.getElementById('violationForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= trans('common.saving') ?>...';

    fetch('/admin/violations', {
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
            if (data.errors) {
                console.error('Validation errors:', data.errors);
            }
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
