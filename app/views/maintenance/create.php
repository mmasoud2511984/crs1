<?php
/**
 * File: create.php
 * Path: /app/views/backend/maintenance/create.php
 * Purpose: صفحة إضافة سجل صيانة جديد
 * Dependencies: layout.php, trans()
 * Phase: Phase 5 - Maintenance System
 * Created: 2025-10-24
 */

use Core\FileTracker;
use Core\Session;

// تسجيل الملف
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 5');

$old = Session::getFlash('old') ?? [];
?>

<!-- رأس الصفحة -->
<div class="page-header">
    <div class="page-title">
        <h1><?= trans('maintenance.add_new') ?></h1>
        <p><?= trans('maintenance.add_new_desc') ?></p>
    </div>
    <div class="page-actions">
        <a href="/admin/maintenance" class="btn btn-secondary">
            <i class="icon-arrow-left"></i>
            <?= trans('common.back') ?>
        </a>
    </div>
</div>

<div class="form-container">
    <form method="POST" action="/admin/maintenance/store" enctype="multipart/form-data" id="maintenanceForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        
        <div class="card">
            <div class="card-header">
                <h3><?= trans('maintenance.car_selection') ?></h3>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <!-- اختيار السيارة -->
                    <div class="form-group col-span-2">
                        <label class="required"><?= trans('maintenance.car') ?></label>
                        <select name="car_id" id="carSelect" class="form-control" required>
                            <option value=""><?= trans('maintenance.select_car') ?></option>
                            <?php foreach ($cars as $car): ?>
                                <option value="<?= $car['id'] ?>" 
                                        data-odometer="<?= $car['current_odometer'] ?>"
                                        data-last-maintenance="<?= $car['last_maintenance_odometer'] ?>"
                                        data-interval="<?= $car['maintenance_interval'] ?>"
                                        <?= ($selectedCar && $selectedCar['id'] == $car['id']) || ($old['car_id'] ?? '') == $car['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($car['plate_number']) ?> - 
                                    <?= htmlspecialchars($car['brand_name']) ?> 
                                    <?= htmlspecialchars($car['model_name']) ?>
                                    <?php if ($car['nickname']): ?>
                                        (<?= htmlspecialchars($car['nickname']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- معلومات السيارة -->
                    <div id="carInfo" class="info-box col-span-2" style="display: none;">
                        <div class="info-grid">
                            <div class="info-item">
                                <label><?= trans('car.current_odometer') ?>:</label>
                                <span id="currentOdometer">-</span> <?= trans('car.km') ?>
                            </div>
                            <div class="info-item">
                                <label><?= trans('car.last_maintenance_odometer') ?>:</label>
                                <span id="lastMaintenanceOdometer">-</span> <?= trans('car.km') ?>
                            </div>
                            <div class="info-item">
                                <label><?= trans('car.maintenance_interval') ?>:</label>
                                <span id="maintenanceInterval">-</span> <?= trans('car.km') ?>
                            </div>
                            <div class="info-item">
                                <label><?= trans('maintenance.km_since_last') ?>:</label>
                                <span id="kmSinceLast" class="badge">-</span> <?= trans('car.km') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><?= trans('maintenance.maintenance_details') ?></h3>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <!-- نوع الصيانة -->
                    <div class="form-group">
                        <label class="required"><?= trans('maintenance.type') ?></label>
                        <select name="maintenance_type" class="form-control" required>
                            <option value=""><?= trans('maintenance.select_type') ?></option>
                            <?php foreach ($maintenanceTypes as $type => $label): ?>
                                <option value="<?= $type ?>" <?= ($old['maintenance_type'] ?? '') === $type ? 'selected' : '' ?>>
                                    <?= trans($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- تاريخ الصيانة -->
                    <div class="form-group">
                        <label class="required"><?= trans('maintenance.date') ?></label>
                        <input type="date" name="maintenance_date" class="form-control" 
                               value="<?= $old['maintenance_date'] ?? date('Y-m-d') ?>" required>
                    </div>

                    <!-- قراءة العداد -->
                    <div class="form-group">
                        <label><?= trans('maintenance.odometer') ?></label>
                        <input type="number" name="odometer_reading" id="odometerReading" 
                               class="form-control" min="0"
                               placeholder="<?= trans('maintenance.odometer_placeholder') ?>"
                               value="<?= $old['odometer_reading'] ?? '' ?>">
                        <small class="form-text"><?= trans('maintenance.odometer_hint') ?></small>
                    </div>

                    <!-- التكلفة -->
                    <div class="form-group">
                        <label><?= trans('maintenance.cost') ?></label>
                        <div class="input-group">
                            <input type="number" name="cost" class="form-control" 
                                   min="0" step="0.01"
                                   placeholder="0.00"
                                   value="<?= $old['cost'] ?? '' ?>">
                            <span class="input-suffix"><?= trans('currency') ?></span>
                        </div>
                    </div>

                    <!-- مركز الخدمة -->
                    <div class="form-group">
                        <label><?= trans('maintenance.service_center') ?></label>
                        <input type="text" name="service_center" class="form-control" 
                               maxlength="100"
                               placeholder="<?= trans('maintenance.service_center_placeholder') ?>"
                               value="<?= $old['service_center'] ?? '' ?>">
                    </div>

                    <!-- اسم الفني -->
                    <div class="form-group">
                        <label><?= trans('maintenance.technician_name') ?></label>
                        <input type="text" name="technician_name" class="form-control" 
                               maxlength="100"
                               placeholder="<?= trans('maintenance.technician_placeholder') ?>"
                               value="<?= $old['technician_name'] ?? '' ?>">
                    </div>

                    <!-- تاريخ الصيانة القادمة -->
                    <div class="form-group">
                        <label><?= trans('maintenance.next_maintenance_date') ?></label>
                        <input type="date" name="next_maintenance_date" class="form-control"
                               value="<?= $old['next_maintenance_date'] ?? '' ?>">
                        <small class="form-text"><?= trans('maintenance.next_date_hint') ?></small>
                    </div>

                    <!-- رفع الإيصال -->
                    <div class="form-group">
                        <label><?= trans('maintenance.receipt') ?></label>
                        <input type="file" name="receipt" class="form-control" 
                               accept=".jpg,.jpeg,.png,.pdf">
                        <small class="form-text">
                            <?= trans('maintenance.receipt_hint') ?>
                        </small>
                    </div>

                    <!-- الوصف -->
                    <div class="form-group col-span-2">
                        <label class="required"><?= trans('maintenance.description') ?></label>
                        <textarea name="description" class="form-control" rows="4" 
                                  minlength="10" maxlength="1000" required
                                  placeholder="<?= trans('maintenance.description_placeholder') ?>"><?= $old['description'] ?? '' ?></textarea>
                        <small class="form-text"><?= trans('maintenance.description_hint') ?></small>
                    </div>

                    <!-- القطع المستبدلة -->
                    <div class="form-group col-span-2">
                        <label><?= trans('maintenance.parts_replaced') ?></label>
                        <textarea name="parts_replaced" class="form-control" rows="3" 
                                  maxlength="1000"
                                  placeholder="<?= trans('maintenance.parts_placeholder') ?>"><?= $old['parts_replaced'] ?? '' ?></textarea>
                    </div>

                    <!-- ملاحظات -->
                    <div class="form-group col-span-2">
                        <label><?= trans('maintenance.notes') ?></label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  maxlength="1000"
                                  placeholder="<?= trans('maintenance.notes_placeholder') ?>"><?= $old['notes'] ?? '' ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="icon-save"></i>
                <?= trans('maintenance.save_record') ?>
            </button>
            <a href="/admin/maintenance" class="btn btn-secondary btn-lg">
                <i class="icon-x"></i>
                <?= trans('common.cancel') ?>
            </a>
        </div>
    </form>
</div>

<script>
// عند اختيار سيارة
document.getElementById('carSelect').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const carInfo = document.getElementById('carInfo');
    
    if (this.value) {
        const currentOdometer = parseInt(option.dataset.odometer) || 0;
        const lastMaintenance = parseInt(option.dataset.lastMaintenance) || 0;
        const interval = parseInt(option.dataset.interval) || 0;
        const kmSince = currentOdometer - lastMaintenance;
        
        document.getElementById('currentOdometer').textContent = currentOdometer.toLocaleString();
        document.getElementById('lastMaintenanceOdometer').textContent = lastMaintenance.toLocaleString();
        document.getElementById('maintenanceInterval').textContent = interval.toLocaleString();
        
        const kmSinceElement = document.getElementById('kmSinceLast');
        kmSinceElement.textContent = kmSince.toLocaleString();
        
        // تغيير اللون حسب الحالة
        if (kmSince >= interval) {
            kmSinceElement.className = 'badge badge-danger';
        } else if (kmSince >= interval * 0.8) {
            kmSinceElement.className = 'badge badge-warning';
        } else {
            kmSinceElement.className = 'badge badge-success';
        }
        
        // تعبئة قراءة العداد تلقائياً
        document.getElementById('odometerReading').value = currentOdometer;
        
        carInfo.style.display = 'block';
    } else {
        carInfo.style.display = 'none';
    }
});

// تفعيل اختيار السيارة إذا كان محدداً مسبقاً
window.addEventListener('DOMContentLoaded', function() {
    const carSelect = document.getElementById('carSelect');
    if (carSelect.value) {
        carSelect.dispatchEvent(new Event('change'));
    }
});

// التحقق من النموذج قبل الإرسال
document.getElementById('maintenanceForm').addEventListener('submit', function(e) {
    const carId = document.getElementById('carSelect').value;
    const maintenanceType = document.querySelector('[name="maintenance_type"]').value;
    const description = document.querySelector('[name="description"]').value;
    const maintenanceDate = document.querySelector('[name="maintenance_date"]').value;
    
    if (!carId || !maintenanceType || !description || !maintenanceDate) {
        e.preventDefault();
        alert('<?= trans('maintenance.error.required_fields') ?>');
        return false;
    }
    
    if (description.length < 10) {
        e.preventDefault();
        alert('<?= trans('maintenance.error.description_too_short') ?>');
        return false;
    }
});
</script>

<style>
.info-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1.5rem;
    margin-top: 1rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-item label {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.info-item span {
    font-size: 1.125rem;
    font-weight: 600;
    color: #212529;
}

.input-group {
    display: flex;
    align-items: center;
}

.input-suffix {
    margin-left: 0.5rem;
    color: #6c757d;
    font-weight: 500;
}

.required::after {
    content: ' *';
    color: #dc3545;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr !important;
    }
    
    .col-span-2 {
        grid-column: auto !important;
    }
}
</style>
