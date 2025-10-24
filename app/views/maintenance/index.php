<?php
/**
 * File: index.php
 * Path: /app/views/backend/maintenance/index.php
 * Purpose: صفحة قائمة سجلات الصيانة مع البحث والفلترة
 * Dependencies: layout.php, trans()
 * Phase: Phase 5 - Maintenance System
 * Created: 2025-10-24
 */

use Core\FileTracker;

// تسجيل الملف
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 5');
?>

<!-- رأس الصفحة -->
<div class="page-header">
    <div class="page-title">
        <h1><?= trans('maintenance.title') ?></h1>
        <p><?= trans('maintenance.subtitle') ?></p>
    </div>
    <div class="page-actions">
        <a href="/admin/maintenance/alerts" class="btn btn-warning">
            <i class="icon-alert"></i>
            <?= trans('maintenance.alerts') ?>
            <?php if ($statistics['overdue'] ?? 0): ?>
                <span class="badge badge-danger"><?= $statistics['overdue'] ?></span>
            <?php endif; ?>
        </a>
        <a href="/admin/maintenance/create" class="btn btn-primary">
            <i class="icon-plus"></i>
            <?= trans('maintenance.add_new') ?>
        </a>
    </div>
</div>

<!-- بطاقات الإحصائيات -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-blue">
            <i class="icon-wrench"></i>
        </div>
        <div class="stat-details">
            <h3><?= number_format($statistics['total_records'] ?? 0) ?></h3>
            <p><?= trans('maintenance.total_records') ?></p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-green">
            <i class="icon-check-circle"></i>
        </div>
        <div class="stat-details">
            <h3><?= number_format($statistics['periodic'] ?? 0) ?></h3>
            <p><?= trans('maintenance.type.periodic') ?></p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-orange">
            <i class="icon-tool"></i>
        </div>
        <div class="stat-details">
            <h3><?= number_format($statistics['repair'] ?? 0) ?></h3>
            <p><?= trans('maintenance.type.repair') ?></p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-red">
            <i class="icon-alert-triangle"></i>
        </div>
        <div class="stat-details">
            <h3><?= number_format($statistics['accident'] ?? 0) ?></h3>
            <p><?= trans('maintenance.type.accident') ?></p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-purple">
            <i class="icon-dollar-sign"></i>
        </div>
        <div class="stat-details">
            <h3><?= number_format($statistics['total_cost'] ?? 0, 2) ?> <?= trans('currency') ?></h3>
            <p><?= trans('maintenance.total_cost') ?></p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-teal">
            <i class="icon-calendar"></i>
        </div>
        <div class="stat-details">
            <h3><?= number_format($statistics['this_month'] ?? 0) ?></h3>
            <p><?= trans('maintenance.this_month') ?></p>
        </div>
    </div>
</div>

<!-- نموذج البحث والفلترة -->
<div class="card">
    <div class="card-header">
        <h3><?= trans('maintenance.search_filter') ?></h3>
        <button type="button" class="btn btn-sm btn-secondary" onclick="resetFilters()">
            <i class="icon-x"></i>
            <?= trans('common.reset') ?>
        </button>
    </div>
    <div class="card-body">
        <form method="GET" action="/admin/maintenance" id="filterForm">
            <div class="form-grid">
                <div class="form-group">
                    <label><?= trans('maintenance.car') ?></label>
                    <select name="car_id" class="form-control">
                        <option value=""><?= trans('common.all') ?></option>
                        <?php foreach ($cars as $car): ?>
                            <option value="<?= $car['id'] ?>" <?= ($filters['car_id'] ?? '') == $car['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($car['plate_number']) ?> - 
                                <?= htmlspecialchars($car['brand_name']) ?> 
                                <?= htmlspecialchars($car['model_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><?= trans('maintenance.type') ?></label>
                    <select name="type" class="form-control">
                        <option value=""><?= trans('common.all') ?></option>
                        <option value="periodic" <?= ($filters['maintenance_type'] ?? '') === 'periodic' ? 'selected' : '' ?>>
                            <?= trans('maintenance.type.periodic') ?>
                        </option>
                        <option value="repair" <?= ($filters['maintenance_type'] ?? '') === 'repair' ? 'selected' : '' ?>>
                            <?= trans('maintenance.type.repair') ?>
                        </option>
                        <option value="accident" <?= ($filters['maintenance_type'] ?? '') === 'accident' ? 'selected' : '' ?>>
                            <?= trans('maintenance.type.accident') ?>
                        </option>
                        <option value="inspection" <?= ($filters['maintenance_type'] ?? '') === 'inspection' ? 'selected' : '' ?>>
                            <?= trans('maintenance.type.inspection') ?>
                        </option>
                        <option value="other" <?= ($filters['maintenance_type'] ?? '') === 'other' ? 'selected' : '' ?>>
                            <?= trans('maintenance.type.other') ?>
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label><?= trans('maintenance.date_from') ?></label>
                    <input type="date" name="date_from" class="form-control" 
                           value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label><?= trans('maintenance.date_to') ?></label>
                    <input type="date" name="date_to" class="form-control" 
                           value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label><?= trans('common.search') ?></label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="<?= trans('maintenance.search_placeholder') ?>"
                           value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="icon-search"></i>
                        <?= trans('common.search') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- جدول السجلات -->
<div class="card">
    <div class="card-header">
        <h3><?= trans('maintenance.records_list') ?></h3>
        <div class="card-actions">
            <a href="/admin/maintenance/export?<?= http_build_query($filters) ?>" class="btn btn-sm btn-success">
                <i class="icon-download"></i>
                <?= trans('common.export') ?>
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($maintenanceRecords)): ?>
            <div class="empty-state">
                <i class="icon-inbox"></i>
                <h3><?= trans('maintenance.no_records') ?></h3>
                <p><?= trans('maintenance.no_records_desc') ?></p>
                <a href="/admin/maintenance/create" class="btn btn-primary">
                    <?= trans('maintenance.add_first') ?>
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= trans('maintenance.id') ?></th>
                            <th><?= trans('maintenance.car') ?></th>
                            <th><?= trans('maintenance.type') ?></th>
                            <th><?= trans('maintenance.date') ?></th>
                            <th><?= trans('maintenance.odometer') ?></th>
                            <th><?= trans('maintenance.cost') ?></th>
                            <th><?= trans('maintenance.service_center') ?></th>
                            <th><?= trans('common.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($maintenanceRecords as $record): ?>
                            <tr>
                                <td><?= $record['id'] ?></td>
                                <td>
                                    <div class="car-info">
                                        <strong><?= htmlspecialchars($record['plate_number']) ?></strong>
                                        <small><?= htmlspecialchars($record['brand_name']) ?> <?= htmlspecialchars($record['model_name']) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?= getMaintenanceTypeBadge($record['maintenance_type']) ?>">
                                        <?= trans('maintenance.type.' . $record['maintenance_type']) ?>
                                    </span>
                                </td>
                                <td><?= date('Y-m-d', strtotime($record['maintenance_date'])) ?></td>
                                <td>
                                    <?php if ($record['odometer_reading']): ?>
                                        <?= number_format($record['odometer_reading']) ?> <?= trans('car.km') ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['cost']): ?>
                                        <strong><?= number_format($record['cost'], 2) ?> <?= trans('currency') ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($record['service_center'] ?? '-') ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="/admin/maintenance/view/<?= $record['id'] ?>" 
                                           class="btn btn-sm btn-info" title="<?= trans('common.view') ?>">
                                            <i class="icon-eye"></i>
                                        </a>
                                        <a href="/admin/maintenance/edit/<?= $record['id'] ?>" 
                                           class="btn btn-sm btn-warning" title="<?= trans('common.edit') ?>">
                                            <i class="icon-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="confirmDelete(<?= $record['id'] ?>)" 
                                                title="<?= trans('common.delete') ?>">
                                            <i class="icon-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <?= trans('common.showing') ?> 
                        <?= (($pagination['current_page'] - 1) * $pagination['per_page']) + 1 ?> 
                        <?= trans('common.to') ?> 
                        <?= min($pagination['current_page'] * $pagination['per_page'], $pagination['total_records']) ?> 
                        <?= trans('common.of') ?> 
                        <?= $pagination['total_records'] ?> 
                        <?= trans('common.records') ?>
                    </div>
                    <nav class="pagination">
                        <?php if ($pagination['current_page'] > 1): ?>
                            <a href="?<?= http_build_query(array_merge($filters, ['page' => 1])) ?>" class="page-link">
                                <i class="icon-chevrons-left"></i>
                            </a>
                            <a href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['current_page'] - 1])) ?>" class="page-link">
                                <i class="icon-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                            <a href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>" 
                               class="page-link <?= $i == $pagination['current_page'] ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                            <a href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['current_page'] + 1])) ?>" class="page-link">
                                <i class="icon-chevron-right"></i>
                            </a>
                            <a href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['total_pages']])) ?>" class="page-link">
                                <i class="icon-chevrons-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- نموذج الحذف -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
</form>

<script>
// دالة إعادة تعيين الفلاتر
function resetFilters() {
    window.location.href = '/admin/maintenance';
}

// دالة تأكيد الحذف
function confirmDelete(id) {
    if (confirm('<?= trans('maintenance.confirm_delete') ?>')) {
        const form = document.getElementById('deleteForm');
        form.action = '/admin/maintenance/delete/' + id;
        form.submit();
    }
}

// دالة الحصول على لون Badge حسب النوع
function getMaintenanceTypeBadge(type) {
    const badges = {
        'periodic': 'success',
        'repair': 'warning',
        'accident': 'danger',
        'inspection': 'info',
        'other': 'secondary'
    };
    return badges[type] || 'secondary';
}
</script>

<?php
// دالة مساعدة للحصول على لون Badge
function getMaintenanceTypeBadge($type) {
    $badges = [
        'periodic' => 'success',
        'repair' => 'warning',
        'accident' => 'danger',
        'inspection' => 'info',
        'other' => 'secondary'
    ];
    return $badges[$type] ?? 'secondary';
}
?>
