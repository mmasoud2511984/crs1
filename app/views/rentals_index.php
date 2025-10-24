<?php
/**
 * File: index.php
 * Path: /app/views/backend/rentals/index.php
 * Purpose: Rentals list view with filters and actions
 * Phase: Phase 7 - Rental System
 * Created: 2025-10-24
 */
?>
<?php $this->layout('backend/layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>

<div class="rentals-page">
    <!-- Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title">
                <i class="fas fa-file-contract"></i>
                <?= trans('rental.list.title') ?>
            </h1>
            
            <?php if (can('create_rentals')): ?>
                <a href="/admin/rentals/create" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    <?= trans('rental.create_new') ?>
                </a>
            <?php endif ?>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #ffc107;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= $stats['pending'] ?? 0 ?></div>
                <div class="stat-label"><?= trans('rental.status.pending') ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #17a2b8;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= $stats['confirmed'] ?? 0 ?></div>
                <div class="stat-label"><?= trans('rental.status.confirmed') ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #28a745;">
                <i class="fas fa-car"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= $stats['active'] ?? 0 ?></div>
                <div class="stat-label"><?= trans('rental.status.active') ?></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #007bff;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($stats['total_revenue'] ?? 0) ?></div>
                <div class="stat-label"><?= trans('rental.total_revenue') ?></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-filter"></i>
                <?= trans('common.filters') ?>
            </h2>
            <button type="button" class="btn btn-link" id="toggle-filters">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="card-body" id="filters-content" style="display: none;">
            <form method="GET" action="/admin/rentals" class="filters-form">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label><?= trans('rental.status') ?></label>
                        <select name="status" class="form-control">
                            <option value=""><?= trans('common.all') ?></option>
                            <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>><?= trans('rental.status.pending') ?></option>
                            <option value="confirmed" <?= ($filters['status'] ?? '') === 'confirmed' ? 'selected' : '' ?>><?= trans('rental.status.confirmed') ?></option>
                            <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>><?= trans('rental.status.active') ?></option>
                            <option value="extended" <?= ($filters['status'] ?? '') === 'extended' ? 'selected' : '' ?>><?= trans('rental.status.extended') ?></option>
                            <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>><?= trans('rental.status.completed') ?></option>
                            <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>><?= trans('rental.status.cancelled') ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group col-md-3">
                        <label><?= trans('rental.payment_status') ?></label>
                        <select name="payment_status" class="form-control">
                            <option value=""><?= trans('common.all') ?></option>
                            <option value="pending" <?= ($filters['payment_status'] ?? '') === 'pending' ? 'selected' : '' ?>><?= trans('rental.payment_status.pending') ?></option>
                            <option value="partial" <?= ($filters['payment_status'] ?? '') === 'partial' ? 'selected' : '' ?>><?= trans('rental.payment_status.partial') ?></option>
                            <option value="paid" <?= ($filters['payment_status'] ?? '') === 'paid' ? 'selected' : '' ?>><?= trans('rental.payment_status.paid') ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group col-md-3">
                        <label><?= trans('branch.name') ?></label>
                        <select name="branch_id" class="form-control">
                            <option value=""><?= trans('common.all') ?></option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>" <?= ($filters['branch_id'] ?? '') == $branch['id'] ? 'selected' : '' ?>>
                                    <?= esc($branch['name']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    
                    <div class="form-group col-md-3">
                        <label><?= trans('common.search') ?></label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="<?= trans('rental.search_placeholder') ?>" 
                               value="<?= esc($filters['search'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label><?= trans('common.date_from') ?></label>
                        <input type="date" name="date_from" class="form-control" 
                               value="<?= esc($filters['date_from'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group col-md-3">
                        <label><?= trans('common.date_to') ?></label>
                        <input type="date" name="date_to" class="form-control" 
                               value="<?= esc($filters['date_to'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                                <?= trans('common.search') ?>
                            </button>
                            <a href="/admin/rentals" class="btn btn-secondary">
                                <i class="fas fa-undo"></i>
                                <?= trans('common.reset') ?>
                            </a>
                            <a href="/admin/rentals/calendar" class="btn btn-info">
                                <i class="fas fa-calendar"></i>
                                <?= trans('rental.calendar.title') ?>
                            </a>
                            <?php if (can('view_rentals')): ?>
                                <a href="/admin/rentals/export/excel?<?= http_build_query($filters) ?>" class="btn btn-success">
                                    <i class="fas fa-file-excel"></i>
                                    <?= trans('common.export_excel') ?>
                                </a>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($rentals)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-contract"></i>
                    <p><?= trans('rental.no_rentals_found') ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?= trans('rental.rental_number') ?></th>
                                <th><?= trans('customer.name') ?></th>
                                <th><?= trans('car.info') ?></th>
                                <th><?= trans('rental.dates') ?></th>
                                <th><?= trans('rental.duration') ?></th>
                                <th><?= trans('rental.amount') ?></th>
                                <th><?= trans('rental.status') ?></th>
                                <th><?= trans('rental.payment_status') ?></th>
                                <th><?= trans('common.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rentals as $rental): ?>
                                <tr>
                                    <td>
                                        <strong><?= esc($rental['rental_number']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= date('Y-m-d', strtotime($rental['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div><?= esc($rental['customer_name']) ?></div>
                                        <small class="text-muted"><?= esc($rental['customer_phone']) ?></small>
                                    </td>
                                    <td>
                                        <div><?= esc($rental['brand_name']) ?> <?= esc($rental['model_name']) ?></div>
                                        <small class="text-muted"><?= esc($rental['plate_number']) ?></small>
                                    </td>
                                    <td>
                                        <div><i class="fas fa-calendar-check"></i> <?= date('Y-m-d', strtotime($rental['start_date'])) ?></div>
                                        <div><i class="fas fa-calendar-times"></i> <?= date('Y-m-d', strtotime($rental['end_date'])) ?></div>
                                    </td>
                                    <td><?= $rental['rental_duration_days'] ?> <?= trans('common.days') ?></td>
                                    <td>
                                        <div><strong><?= number_format($rental['total_amount']) ?></strong></div>
                                        <div class="text-success"><small><?= trans('rental.paid') ?>: <?= number_format($rental['paid_amount']) ?></small></div>
                                        <div class="text-danger"><small><?= trans('rental.remaining') ?>: <?= number_format($rental['remaining_amount']) ?></small></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= getStatusColor($rental['status']) ?>">
                                            <?= trans('rental.status.' . $rental['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= getPaymentStatusColor($rental['payment_status']) ?>">
                                            <?= trans('rental.payment_status.' . $rental['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="/admin/rentals/show/<?= $rental['id'] ?>" class="btn btn-sm btn-info" title="<?= trans('common.view') ?>">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if (can('edit_rentals') && !in_array($rental['status'], ['completed', 'cancelled'])): ?>
                                                <a href="/admin/rentals/edit/<?= $rental['id'] ?>" class="btn btn-sm btn-warning" title="<?= trans('common.edit') ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif ?>
                                            
                                            <?php if (can('delete_rentals') && $rental['status'] !== 'active'): ?>
                                                <button type="button" class="btn btn-sm btn-danger delete-rental" 
                                                        data-id="<?= $rental['id'] ?>" 
                                                        data-number="<?= esc($rental['rental_number']) ?>"
                                                        title="<?= trans('common.delete') ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination-wrapper">
                        <?= renderPagination($pagination, $filters) ?>
                    </div>
                <?php endif ?>
            <?php endif ?>
        </div>
    </div>
</div>

<?php $this->stop() ?>

<?php $this->start('scripts') ?>
<script>
// Toggle filters
document.getElementById('toggle-filters').addEventListener('click', function() {
    const content = document.getElementById('filters-content');
    const icon = this.querySelector('i');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        content.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
});

// Show filters if any filter is active
<?php if (!empty($filters)): ?>
    document.getElementById('filters-content').style.display = 'block';
    document.querySelector('#toggle-filters i').classList.remove('fa-chevron-down');
    document.querySelector('#toggle-filters i').classList.add('fa-chevron-up');
<?php endif ?>

// Delete rental
document.querySelectorAll('.delete-rental').forEach(button => {
    button.addEventListener('click', async function() {
        const id = this.dataset.id;
        const number = this.dataset.number;
        
        if (!confirm(translate('rental.confirm_delete').replace(':number', number))) {
            return;
        }
        
        try {
            const response = await fetch(`/admin/rentals/delete/${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCsrfToken()
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            showToast(translate('error.server_error'), 'error');
        }
    });
});
</script>
<?php $this->stop() ?>

<?php
/**
 * Helper functions for status colors
 */
function getStatusColor(string $status): string {
    return match($status) {
        'pending' => 'warning',
        'confirmed' => 'info',
        'active' => 'success',
        'extended' => 'primary',
        'completed' => 'secondary',
        'cancelled' => 'danger',
        default => 'secondary'
    };
}

function getPaymentStatusColor(string $status): string {
    return match($status) {
        'pending' => 'danger',
        'partial' => 'warning',
        'paid' => 'success',
        default => 'secondary'
    };
}
?>
