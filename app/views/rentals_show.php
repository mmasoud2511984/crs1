<?php
/**
 * File: show.php
 * Path: /app/views/backend/rentals/show.php
 * Purpose: Rental details view with payments and extensions
 * Phase: Phase 7 - Rental System
 * Created: 2025-10-24
 */
?>
<?php $this->layout('backend/layouts/main', ['title' => $title]) ?>

<?php $this->start('content') ?>

<div class="rental-details-page">
    <!-- Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title">
                <i class="fas fa-file-contract"></i>
                <?= trans('rental.view.title') ?>
            </h1>
            
            <div class="page-header-actions">
                <a href="/admin/rentals" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <?= trans('common.back') ?>
                </a>
                
                <?php if (can('edit_rentals') && !in_array($rental['status'], ['completed', 'cancelled'])): ?>
                    <a href="/admin/rentals/edit/<?= $rental['id'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i>
                        <?= trans('common.edit') ?>
                    </a>
                <?php endif ?>
                
                <a href="/admin/rentals/contract/<?= $rental['id'] ?>" class="btn btn-info" target="_blank">
                    <i class="fas fa-file-pdf"></i>
                    <?= trans('rental.generate_contract') ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Status Actions -->
    <?php if (can('manage_rentals')): ?>
        <div class="card">
            <div class="card-body">
                <div class="status-actions">
                    <?php if ($rental['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-success" onclick="confirmRental(<?= $rental['id'] ?>)">
                            <i class="fas fa-check"></i>
                            <?= trans('rental.confirm') ?>
                        </button>
                    <?php endif ?>
                    
                    <?php if ($rental['status'] === 'confirmed'): ?>
                        <button type="button" class="btn btn-primary" onclick="activateRental(<?= $rental['id'] ?>)">
                            <i class="fas fa-play"></i>
                            <?= trans('rental.activate') ?>
                        </button>
                    <?php endif ?>
                    
                    <?php if (in_array($rental['status'], ['active', 'extended'])): ?>
                        <button type="button" class="btn btn-warning" onclick="extendRental(<?= $rental['id'] ?>)">
                            <i class="fas fa-calendar-plus"></i>
                            <?= trans('rental.extend') ?>
                        </button>
                        
                        <button type="button" class="btn btn-success" onclick="completeRental(<?= $rental['id'] ?>)">
                            <i class="fas fa-check-circle"></i>
                            <?= trans('rental.complete') ?>
                        </button>
                    <?php endif ?>
                    
                    <?php if (!in_array($rental['status'], ['completed', 'cancelled'])): ?>
                        <button type="button" class="btn btn-danger" onclick="cancelRental(<?= $rental['id'] ?>)">
                            <i class="fas fa-times-circle"></i>
                            <?= trans('rental.cancel') ?>
                        </button>
                    <?php endif ?>
                </div>
            </div>
        </div>
    <?php endif ?>

    <!-- Details -->
    <div class="row">
        <!-- Basic Info -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= trans('rental.basic_info') ?></h2>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <strong><?= trans('rental.rental_number') ?>:</strong>
                            <span><?= esc($rental['rental_number']) ?></span>
                        </div>
                        
                        <div class="info-item">
                            <strong><?= trans('customer.name') ?>:</strong>
                            <span><?= esc($rental['customer_name']) ?></span>
                        </div>
                        
                        <div class="info-item">
                            <strong><?= trans('car.info') ?>:</strong>
                            <span><?= esc($rental['brand_name']) ?> <?= esc($rental['model_name']) ?> (<?= esc($rental['plate_number']) ?>)</span>
                        </div>
                        
                        <div class="info-item">
                            <strong><?= trans('rental.start_date') ?>:</strong>
                            <span><?= date('Y-m-d H:i', strtotime($rental['start_date'])) ?></span>
                        </div>
                        
                        <div class="info-item">
                            <strong><?= trans('rental.end_date') ?>:</strong>
                            <span><?= date('Y-m-d H:i', strtotime($rental['end_date'])) ?></span>
                        </div>
                        
                        <div class="info-item">
                            <strong><?= trans('rental.duration') ?>:</strong>
                            <span><?= $rental['rental_duration_days'] ?> <?= trans('common.days') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payments -->
            <div class="card mt-3">
                <div class="card-header">
                    <h2 class="card-title"><?= trans('rental.payments') ?></h2>
                    <?php if (can('manage_payments')): ?>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addPayment(<?= $rental['id'] ?>)">
                            <i class="fas fa-plus"></i>
                            <?= trans('rental.add_payment') ?>
                        </button>
                    <?php endif ?>
                </div>
                <div class="card-body">
                    <?php if (empty($payments)): ?>
                        <p class="text-muted"><?= trans('rental.no_payments') ?></p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?= trans('common.date') ?></th>
                                    <th><?= trans('rental.amount') ?></th>
                                    <th><?= trans('rental.type') ?></th>
                                    <th><?= trans('rental.payment_method') ?></th>
                                    <th><?= trans('common.actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?= date('Y-m-d H:i', strtotime($payment['payment_date'])) ?></td>
                                        <td><strong><?= number_format($payment['amount']) ?></strong></td>
                                        <td><span class="badge badge-info"><?= trans('rental.payment_type.' . $payment['payment_type']) ?></span></td>
                                        <td><?= esc($payment['payment_method_name'] ?? trans('common.cash')) ?></td>
                                        <td>
                                            <?php if (can('manage_payments')): ?>
                                                <button class="btn btn-sm btn-danger" onclick="deletePayment(<?= $payment['id'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif ?>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    <?php endif ?>
                </div>
            </div>

            <!-- Extensions -->
            <?php if (!empty($extensions)): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h2 class="card-title"><?= trans('rental.extensions') ?></h2>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?= trans('rental.original_date') ?></th>
                                    <th><?= trans('rental.new_date') ?></th>
                                    <th><?= trans('rental.days') ?></th>
                                    <th><?= trans('rental.amount') ?></th>
                                    <th><?= trans('common.status') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($extensions as $ext): ?>
                                    <tr>
                                        <td><?= date('Y-m-d', strtotime($ext['original_end_date'])) ?></td>
                                        <td><?= date('Y-m-d', strtotime($ext['new_end_date'])) ?></td>
                                        <td><?= $ext['extension_days'] ?></td>
                                        <td><?= number_format($ext['extension_amount']) ?></td>
                                        <td><span class="badge badge-<?= $ext['payment_status'] === 'paid' ? 'success' : 'warning' ?>"><?= trans('rental.payment_status.' . $ext['payment_status']) ?></span></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif ?>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Financial Summary -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= trans('rental.financial_summary') ?></h2>
                </div>
                <div class="card-body">
                    <div class="summary-item">
                        <span><?= trans('rental.total_amount') ?>:</span>
                        <strong><?= number_format($rental['total_amount']) ?></strong>
                    </div>
                    <div class="summary-item">
                        <span><?= trans('rental.paid_amount') ?>:</span>
                        <strong class="text-success"><?= number_format($rental['paid_amount']) ?></strong>
                    </div>
                    <div class="summary-item">
                        <span><?= trans('rental.remaining_amount') ?>:</span>
                        <strong class="text-danger"><?= number_format($rental['remaining_amount']) ?></strong>
                    </div>
                    <hr>
                    <div class="summary-item">
                        <span><?= trans('rental.deposit_amount') ?>:</span>
                        <strong><?= number_format($rental['deposit_amount']) ?></strong>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="card mt-3">
                <div class="card-header">
                    <h2 class="card-title"><?= trans('common.status') ?></h2>
                </div>
                <div class="card-body">
                    <div class="status-badge-large badge-<?= getStatusColor($rental['status']) ?>">
                        <?= trans('rental.status.' . $rental['status']) ?>
                    </div>
                    <div class="status-badge-large badge-<?= getPaymentStatusColor($rental['payment_status']) ?> mt-2">
                        <?= trans('rental.payment_status.' . $rental['payment_status']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->stop() ?>

<?php $this->start('scripts') ?>
<script src="/assets/js/rentals.js"></script>
<?php $this->stop() ?>
