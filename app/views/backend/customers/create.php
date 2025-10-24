<?php
$pageTitle = trans('customer.add_customer');
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><?= trans('customer.add_customer') ?></h1>
</div>

<form method="POST" action="/admin/customers/store" enctype="multipart/form-data">
    <?= csrf_field() ?>
    
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.full_name') ?> *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.email') ?> *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.phone') ?> *</label>
                        <input type="tel" name="phone" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.whatsapp') ?></label>
                        <input type="tel" name="whatsapp" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.password') ?> *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.nationality') ?></label>
                        <input type="text" name="nationality" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.id_type') ?></label>
                        <select name="id_type" class="form-select">
                            <option value="national_id"><?= trans('customer.national_id') ?></option>
                            <option value="iqama"><?= trans('customer.iqama') ?></option>
                            <option value="passport"><?= trans('customer.passport') ?></option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.id_number') ?></label>
                        <input type="text" name="id_number" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.id_expiry_date') ?></label>
                        <input type="date" name="id_expiry_date" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.id_document') ?></label>
                        <input type="file" name="id_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.license_number') ?></label>
                        <input type="text" name="license_number" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.license_expiry_date') ?></label>
                        <input type="date" name="license_expiry_date" class="form-control">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.license_document') ?></label>
                        <input type="file" name="license_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>
                <div class="col-12">
                    <div class="mb-3">
                        <label class="form-label"><?= trans('customer.address') ?></label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary"><?= trans('common.save') ?></button>
            <a href="/admin/customers" class="btn btn-secondary"><?= trans('common.cancel') ?></a>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
