<?php
/**
 * File: create.php
 * Path: /app/views/backend/rentals/create.php
 * Purpose: Create new rental form
 * Phase: Phase 7 - Rental System
 * Created: 2025-10-24
 */
?>
<?php $this->layout('backend/layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>

<div class="rental-create-page">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-plus"></i>
            <?= trans('rental.create.title') ?>
        </h1>
        <a href="/admin/rentals" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            <?= trans('common.back') ?>
        </a>
    </div>

    <form id="rentalForm" method="POST" action="/admin/rentals/store">
        <?= csrf_field() ?>
        
        <div class="row">
            <div class="col-md-8">
                <!-- Customer & Car -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><?= trans('rental.customer_car_info') ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label><?= trans('customer.select') ?> <span class="required">*</span></label>
                            <select name="customer_id" id="customer_id" class="form-control select2" required>
                                <option value=""><?= trans('customer.select') ?></option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['id'] ?>"><?= esc($customer['name']) ?> - <?= esc($customer['phone']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><?= trans('car.select') ?> <span class="required">*</span></label>
                            <select name="car_id" id="car_id" class="form-control select2" required>
                                <option value=""><?= trans('car.select') ?></option>
                                <?php foreach ($cars as $car): ?>
                                    <option value="<?= $car['id'] ?>" data-daily-rate="<?= $car['daily_rate'] ?>">
                                        <?= esc($car['brand_name']) ?> <?= esc($car['model_name']) ?> (<?= esc($car['plate_number']) ?>)
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Dates & Duration -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h2 class="card-title"><?= trans('rental.dates_duration') ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?= trans('rental.start_date') ?> <span class="required">*</span></label>
                                    <input type="datetime-local" name="start_date" id="start_date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?= trans('rental.end_date') ?> <span class="required">*</span></label>
                                    <input type="datetime-local" name="end_date" id="end_date" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong><?= trans('rental.duration') ?>:</strong> <span id="duration_display">0</span> <?= trans('common.days') ?>
                        </div>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h2 class="card-title"><?= trans('rental.pricing') ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?= trans('rental.daily_rate') ?> <span class="required">*</span></label>
                                    <input type="number" name="daily_rate" id="daily_rate" class="form-control" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input type="checkbox" name="with_driver" id="with_driver" class="form-check-input" value="1">
                                    <label class="form-check-label"><?= trans('rental.with_driver') ?></label>
                                </div>
                            </div>
                        </div>
                        
                        <div id="driver_fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?= trans('rental.driver_name') ?></label>
                                        <input type="text" name="driver_name" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?= trans('rental.driver_phone') ?></label>
                                        <input type="text" name="driver_phone" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?= trans('rental.driver_daily_rate') ?></label>
                                        <input type="number" name="driver_daily_rate" id="driver_daily_rate" class="form-control" step="0.01">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Summary -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><?= trans('rental.summary') ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="summary-item">
                            <span><?= trans('rental.total_amount') ?>:</span>
                            <strong id="total_amount_display">0.00</strong>
                        </div>
                        <div class="summary-item">
                            <span><?= trans('rental.deposit') ?> (<?= $settings['deposit_percentage'] ?>%):</span>
                            <strong id="deposit_amount_display">0.00</strong>
                        </div>
                    </div>
                </div>

                <!-- Initial Payment -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h2 class="card-title"><?= trans('rental.initial_payment') ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label><?= trans('rental.payment_method') ?></label>
                            <select name="payment_method_id" class="form-control">
                                <option value=""><?= trans('rental.cash') ?></option>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <option value="<?= $method['id'] ?>"><?= esc($method['name']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><?= trans('rental.initial_payment_amount') ?></label>
                            <input type="number" name="initial_payment" class="form-control" step="0.01" value="0">
                        </div>
                        
                        <div class="form-group">
                            <label><?= trans('rental.deposit_payment_amount') ?></label>
                            <input type="number" name="deposit_payment" id="deposit_payment" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="card mt-3">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i>
                            <?= trans('common.save') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php $this->stop() ?>

<?php $this->start('scripts') ?>
<script>
const depositPercentage = <?= $settings['deposit_percentage'] ?>;

// Calculate total when dates or rates change
function calculateTotal() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const dailyRate = parseFloat(document.getElementById('daily_rate').value) || 0;
    const withDriver = document.getElementById('with_driver').checked;
    const driverRate = withDriver ? (parseFloat(document.getElementById('driver_daily_rate').value) || 0) : 0;
    
    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
        
        if (days > 0) {
            document.getElementById('duration_display').textContent = days;
            const total = (dailyRate + driverRate) * days;
            const deposit = total * (depositPercentage / 100);
            
            document.getElementById('total_amount_display').textContent = total.toFixed(2);
            document.getElementById('deposit_amount_display').textContent = deposit.toFixed(2);
            document.getElementById('deposit_payment').value = deposit.toFixed(2);
        }
    }
}

document.getElementById('start_date').addEventListener('change', calculateTotal);
document.getElementById('end_date').addEventListener('change', calculateTotal);
document.getElementById('daily_rate').addEventListener('input', calculateTotal);
document.getElementById('with_driver').addEventListener('change', function() {
    document.getElementById('driver_fields').style.display = this.checked ? 'block' : 'none';
    calculateTotal();
});
document.getElementById('driver_daily_rate').addEventListener('input', calculateTotal);

// Set daily rate when car is selected
document.getElementById('car_id').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const rate = selected.dataset.dailyRate;
    if (rate) {
        document.getElementById('daily_rate').value = rate;
        calculateTotal();
    }
});

// Form submission
document.getElementById('rentalForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => window.location.href = '/admin/rentals/show/' + data.data.id, 1500);
        } else {
            showToast(data.message, 'error');
            if (data.errors) {
                Object.keys(data.errors).forEach(field => {
                    showFieldError(field, data.errors[field]);
                });
            }
        }
    } catch (error) {
        showToast(translate('error.server_error'), 'error');
    }
});
</script>
<?php $this->stop() ?>
