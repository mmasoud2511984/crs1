<?php
/**
 * File: view.php
 * Path: /app/views/backend/violations/view.php
 * Purpose: عرض تفاصيل مخالفة
 * Phase: Phase 8 - Violations & Reviews
 * Created: 2025-10-24
 */

use Core\Security;
use Core\FileTracker;

$pageTitle = trans('violations.violation_details');
$breadcrumbs = [
    ['text' => trans('dashboard'), 'url' => '/admin'],
    ['text' => trans('violations.violations'), 'url' => '/admin/violations'],
    ['text' => trans('violations.violation_details'), 'url' => '']
];
?>

<?php include viewPath('backend/layouts/header.php'); ?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><?= trans('violations.violation') ?> #<?= Security::escape($violation['violation_number']) ?></h1>
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
            <div class="row">
                <div class="col-md-8">
                    <!-- معلومات المخالفة -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?= trans('violations.violation_information') ?></h3>
                            <div class="card-tools">
                                <?php if (auth()->hasPermission('violations.edit')): ?>
                                    <a href="/admin/violations/<?= $violation['id'] ?>/edit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> <?= trans('common.edit') ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="30%"><?= trans('violations.violation_number') ?></th>
                                    <td><strong><?= Security::escape($violation['violation_number']) ?></strong></td>
                                </tr>
                                <tr>
                                    <th><?= trans('violations.violation_date') ?></th>
                                    <td><?= date('Y-m-d H:i', strtotime($violation['violation_date'])) ?></td>
                                </tr>
                                <tr>
                                    <th><?= trans('violations.car') ?></th>
                                    <td>
                                        <strong><?= Security::escape($violation['plate_number']) ?></strong><br>
                                        <?= Security::escape($violation['car_full_name']) ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?= trans('violations.customer') ?></th>
                                    <td>
                                        <?php if (!empty($violation['customer_name'])): ?>
                                            <?= Security::escape($violation['customer_name']) ?><br>
                                            <small class="text-muted"><?= Security::escape($violation['customer_phone']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted"><?= trans('common.not_assigned') ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if (!empty($violation['rental_number'])): ?>
                                <tr>
                                    <th><?= trans('violations.rental_contract') ?></th>
                                    <td>
                                        <a href="/admin/rentals/<?= $violation['rental_id'] ?>">
                                            <?= Security::escape($violation['rental_number']) ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th><?= trans('violations.violation_type') ?></th>
                                    <td><?= Security::escape($violation['violation_type']) ?></td>
                                </tr>
                                <tr>
                                    <th><?= trans('violations.violation_location') ?></th>
                                    <td><?= Security::escape($violation['violation_location']) ?: '-' ?></td>
                                </tr>
                                <tr>
                                    <th><?= trans('violations.fine_amount') ?></th>
                                    <td><strong class="text-danger"><?= number_format($violation['fine_amount'], 2) ?></strong></td>
                                </tr>
                                <tr>
                                    <th><?= trans('violations.notes') ?></th>
                                    <td><?= nl2br(Security::escape($violation['notes'])) ?: '-' ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- المستندات -->
                    <?php if (!empty($violation['document_path'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?= trans('violations.violation_document') ?></h3>
                        </div>
                        <div class="card-body">
                            <?php 
                            $ext = pathinfo($violation['document_path'], PATHINFO_EXTENSION);
                            if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png'])): 
                            ?>
                                <img src="/<?= Security::escape($violation['document_path']) ?>" class="img-fluid" alt="Violation Document">
                            <?php else: ?>
                                <a href="/<?= Security::escape($violation['document_path']) ?>" target="_blank" class="btn btn-info">
                                    <i class="fas fa-file-pdf"></i> <?= trans('violations.view_document') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <!-- الحالة -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?= trans('violations.status') ?></h3>
                        </div>
                        <div class="card-body">
                            <?php
                            $statusClasses = [
                                'pending' => 'warning',
                                'paid' => 'success',
                                'disputed' => 'info',
                                'cancelled' => 'danger'
                            ];
                            $class = $statusClasses[$violation['status']] ?? 'secondary';
                            ?>
                            <div class="alert alert-<?= $class ?>">
                                <h5><i class="icon fas fa-info-circle"></i> <?= trans('violations.' . $violation['status']) ?></h5>
                            </div>

                            <table class="table table-sm">
                                <tr>
                                    <th><?= trans('violations.paid_by') ?></th>
                                    <td>
                                        <?php
                                        $paidByClasses = [
                                            'company' => 'success',
                                            'customer' => 'warning',
                                            'pending' => 'secondary'
                                        ];
                                        $class = $paidByClasses[$violation['paid_by']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $class ?>">
                                            <?= trans('violations.' . $violation['paid_by']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php if ($violation['status'] === 'paid'): ?>
                                <tr>
                                    <th><?= trans('violations.payment_date') ?></th>
                                    <td><?= date('Y-m-d', strtotime($violation['payment_date'])) ?></td>
                                </tr>
                                <?php if (!empty($violation['payment_reference'])): ?>
                                <tr>
                                    <th><?= trans('violations.payment_reference') ?></th>
                                    <td><?= Security::escape($violation['payment_reference']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>

                    <!-- معلومات الإنشاء -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?= trans('common.created_info') ?></h3>
                        </div>
                        <div class="card-body">
                            <p><strong><?= trans('common.created_by') ?>:</strong><br>
                               <?= Security::escape($violation['created_by_name'] ?? '-') ?></p>
                            <p><strong><?= trans('common.created_at') ?>:</strong><br>
                               <?= date('Y-m-d H:i', strtotime($violation['created_at'])) ?></p>
                            <p><strong><?= trans('common.updated_at') ?>:</strong><br>
                               <?= date('Y-m-d H:i', strtotime($violation['updated_at'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php 
FileTracker::logCreate(__FILE__, FileTracker::countLines(__FILE__), 'Phase 8');
include viewPath('backend/layouts/footer.php'); 
?>
