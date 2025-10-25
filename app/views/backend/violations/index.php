<?php
/**
 * File: index.php
 * Path: /app/views/backend/violations/index.php
 * Purpose: صفحة قائمة المخالفات في لوحة التحكم
 * Phase: Phase 8 - Violations & Reviews
 * Created: 2025-10-24
 */

use Core\Security;
use Core\FileTracker;

$pageTitle = trans('violations.page_title');
$breadcrumbs = [
    ['text' => trans('dashboard'), 'url' => '/admin'],
    ['text' => trans('violations.violations'), 'url' => '']
];
?>

<?php include viewPath('backend/layouts/header.php'); ?>

<div class="content-wrapper">
    <!-- Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?= trans('violations.violations_management') ?></h1>
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

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= $statistics['total_violations'] ?? 0 ?></h3>
                            <p><?= trans('violations.total_violations') ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= $statistics['pending'] ?? 0 ?></h3>
                            <p><?= trans('violations.pending_violations') ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= $statistics['paid'] ?? 0 ?></h3>
                            <p><?= trans('violations.paid_violations') ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?= number_format($statistics['pending_fines'] ?? 0, 2) ?></h3>
                            <p><?= trans('violations.pending_fines') ?></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters & Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= trans('violations.filter_search') ?></h3>
                    <div class="card-tools">
                        <?php if (auth()->hasPermission('violations.create')): ?>
                            <a href="/admin/violations/create" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> <?= trans('violations.add_violation') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="/admin/violations" id="filterForm">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label><?= trans('violations.status') ?></label>
                                    <select name="status" class="form-control form-control-sm">
                                        <option value=""><?= trans('common.all') ?></option>
                                        <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>><?= trans('violations.pending') ?></option>
                                        <option value="paid" <?= ($filters['status'] ?? '') === 'paid' ? 'selected' : '' ?>><?= trans('violations.paid') ?></option>
                                        <option value="disputed" <?= ($filters['status'] ?? '') === 'disputed' ? 'selected' : '' ?>><?= trans('violations.disputed') ?></option>
                                        <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>><?= trans('violations.cancelled') ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label><?= trans('violations.paid_by') ?></label>
                                    <select name="paid_by" class="form-control form-control-sm">
                                        <option value=""><?= trans('common.all') ?></option>
                                        <option value="company" <?= ($filters['paid_by'] ?? '') === 'company' ? 'selected' : '' ?>><?= trans('violations.company') ?></option>
                                        <option value="customer" <?= ($filters['paid_by'] ?? '') === 'customer' ? 'selected' : '' ?>><?= trans('violations.customer') ?></option>
                                        <option value="pending" <?= ($filters['paid_by'] ?? '') === 'pending' ? 'selected' : '' ?>><?= trans('violations.payment_pending') ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label><?= trans('violations.date_from') ?></label>
                                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= Security::escape($filters['date_from'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label><?= trans('violations.date_to') ?></label>
                                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= Security::escape($filters['date_to'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><?= trans('common.search') ?></label>
                                    <input type="text" name="search" class="form-control form-control-sm" 
                                           placeholder="<?= trans('violations.search_placeholder') ?>" 
                                           value="<?= Security::escape($filters['search'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-1">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-sm btn-block">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Violations Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= trans('violations.violations_list') ?> (<?= $total ?>)</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th><?= trans('violations.violation_number') ?></th>
                                <th><?= trans('violations.car') ?></th>
                                <th><?= trans('violations.customer') ?></th>
                                <th><?= trans('violations.violation_date') ?></th>
                                <th><?= trans('violations.violation_type') ?></th>
                                <th><?= trans('violations.fine_amount') ?></th>
                                <th><?= trans('violations.paid_by') ?></th>
                                <th><?= trans('violations.status') ?></th>
                                <th><?= trans('common.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($violations)): ?>
                                <tr>
                                    <td colspan="9" class="text-center"><?= trans('violations.no_violations_found') ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($violations as $violation): ?>
                                    <tr>
                                        <td>
                                            <strong><?= Security::escape($violation['violation_number']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="text-muted"><?= Security::escape($violation['plate_number']) ?></span><br>
                                            <small><?= Security::escape($violation['car_full_name']) ?></small>
                                        </td>
                                        <td>
                                            <?= Security::escape($violation['customer_name'] ?? trans('common.not_assigned')) ?>
                                            <?php if (!empty($violation['rental_number'])): ?>
                                                <br><small class="text-muted"><?= Security::escape($violation['rental_number']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('Y-m-d', strtotime($violation['violation_date'])) ?></td>
                                        <td><?= Security::escape($violation['violation_type']) ?></td>
                                        <td><strong><?= number_format($violation['fine_amount'], 2) ?></strong></td>
                                        <td>
                                            <?php
                                            $paidByClasses = [
                                                'company' => 'badge-success',
                                                'customer' => 'badge-warning',
                                                'pending' => 'badge-secondary'
                                            ];
                                            $class = $paidByClasses[$violation['paid_by']] ?? 'badge-secondary';
                                            ?>
                                            <span class="badge <?= $class ?>">
                                                <?= trans('violations.' . $violation['paid_by']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClasses = [
                                                'pending' => 'badge-warning',
                                                'paid' => 'badge-success',
                                                'disputed' => 'badge-info',
                                                'cancelled' => 'badge-danger'
                                            ];
                                            $class = $statusClasses[$violation['status']] ?? 'badge-secondary';
                                            ?>
                                            <span class="badge <?= $class ?>">
                                                <?= trans('violations.' . $violation['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="/admin/violations/<?= $violation['id'] ?>" class="btn btn-info" title="<?= trans('common.view') ?>">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (auth()->hasPermission('violations.edit')): ?>
                                                    <a href="/admin/violations/<?= $violation['id'] ?>/edit" class="btn btn-primary" title="<?= trans('common.edit') ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (auth()->hasPermission('violations.delete')): ?>
                                                    <button type="button" class="btn btn-danger" onclick="deleteViolation(<?= $violation['id'] ?>)" title="<?= trans('common.delete') ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

<script>
function deleteViolation(id) {
    if (!confirm('<?= trans('violations.confirm_delete') ?>')) {
        return;
    }

    fetch('/admin/violations/' + id, {
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
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?= trans('error.something_went_wrong') ?>');
    });
}
</script>

<?php 
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 8');
include viewPath('backend/layouts/footer.php'); 
?>
