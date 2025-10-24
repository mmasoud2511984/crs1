<?php
/**
 * File: alerts.php
 * Path: /app/views/backend/maintenance/alerts.php
 * Purpose: صفحة تنبيهات الصيانة - السيارات المستحقة والقريبة
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
        <h1><?= trans('maintenance.alerts_title') ?></h1>
        <p><?= trans('maintenance.alerts_subtitle') ?></p>
    </div>
    <div class="page-actions">
        <a href="/admin/maintenance" class="btn btn-secondary">
            <i class="icon-arrow-left"></i>
            <?= trans('common.back') ?>
        </a>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="icon-printer"></i>
            <?= trans('common.print') ?>
        </button>
    </div>
</div>

<!-- بطاقات الإحصائيات -->
<div class="stats-grid">
    <div class="stat-card alert-card danger">
        <div class="stat-icon bg-red">
            <i class="icon-alert-triangle"></i>
        </div>
        <div class="stat-details">
            <h3><?= number_format($statistics['overdue']) ?></h3>
            <p><?= trans('maintenance.alerts.overdue_cars') ?></p>
        </div>
    </div>

    <div class="stat-card alert-card warning">
        <div class="stat-icon bg-orange">
            <i class="icon-clock"></i>
        </div>
        <div class="stat-details">
            <h3><?= number_format($statistics['upcoming']) ?></h3>
            <p><?= trans('maintenance.alerts.upcoming_cars') ?></p>
        </div>
    </div>

    <div class="stat-card alert-card info">
        <div class="stat-icon bg-blue">
            <i class="icon-trending-up"></i>
        </div>
        <div class="stat-details">
            <h3><?= number_format($statistics['total_overdue_km']) ?> <?= trans('car.km') ?></h3>
            <p><?= trans('maintenance.alerts.total_overdue_km') ?></p>
        </div>
    </div>
</div>

<!-- السيارات المستحقة للصيانة فوراً -->
<?php if (!empty($carsNeedingMaintenance)): ?>
    <div class="card alert-section danger-section">
        <div class="card-header">
            <div>
                <h3>
                    <i class="icon-alert-triangle"></i>
                    <?= trans('maintenance.alerts.overdue_title') ?>
                </h3>
                <p><?= trans('maintenance.alerts.overdue_desc') ?></p>
            </div>
            <span class="badge badge-danger badge-lg">
                <?= count($carsNeedingMaintenance) ?> <?= trans('car.car') ?>
            </span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table alert-table">
                    <thead>
                        <tr>
                            <th><?= trans('car.plate_number') ?></th>
                            <th><?= trans('car.car') ?></th>
                            <th><?= trans('car.current_odometer') ?></th>
                            <th><?= trans('car.last_maintenance_odometer') ?></th>
                            <th><?= trans('maintenance.km_since_last') ?></th>
                            <th><?= trans('maintenance.overdue_by') ?></th>
                            <th><?= trans('maintenance.urgency') ?></th>
                            <th class="no-print"><?= trans('common.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($carsNeedingMaintenance as $car): ?>
                            <tr class="alert-row danger">
                                <td>
                                    <strong><?= htmlspecialchars($car['plate_number']) ?></strong>
                                </td>
                                <td>
                                    <?= htmlspecialchars($car['brand_name']) ?> 
                                    <?= htmlspecialchars($car['model_name']) ?>
                                    <?php if ($car['nickname']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($car['nickname']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($car['current_odometer']) ?> <?= trans('car.km') ?></td>
                                <td><?= number_format($car['last_maintenance_odometer']) ?> <?= trans('car.km') ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?= number_format($car['km_since_maintenance']) ?> <?= trans('car.km') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-danger">
                                        +<?= number_format($car['overdue_km']) ?> <?= trans('car.km') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $urgencyLevel = getUrgencyLevel($car['overdue_km'], $car['maintenance_interval']);
                                    ?>
                                    <span class="urgency-badge <?= $urgencyLevel['class'] ?>">
                                        <?= trans($urgencyLevel['label']) ?>
                                    </span>
                                </td>
                                <td class="no-print">
                                    <div class="action-buttons">
                                        <a href="/admin/maintenance/create?car_id=<?= $car['id'] ?>" 
                                           class="btn btn-sm btn-success" 
                                           title="<?= trans('maintenance.add_maintenance') ?>">
                                            <i class="icon-plus"></i>
                                        </a>
                                        <a href="/admin/cars/view/<?= $car['id'] ?>" 
                                           class="btn btn-sm btn-info"
                                           title="<?= trans('car.view_details') ?>">
                                            <i class="icon-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- السيارات القريبة من موعد الصيانة -->
<?php if (!empty($carsNearingMaintenance)): ?>
    <div class="card alert-section warning-section">
        <div class="card-header">
            <div>
                <h3>
                    <i class="icon-clock"></i>
                    <?= trans('maintenance.alerts.upcoming_title') ?>
                </h3>
                <p><?= trans('maintenance.alerts.upcoming_desc') ?></p>
            </div>
            <span class="badge badge-warning badge-lg">
                <?= count($carsNearingMaintenance) ?> <?= trans('car.car') ?>
            </span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table alert-table">
                    <thead>
                        <tr>
                            <th><?= trans('car.plate_number') ?></th>
                            <th><?= trans('car.car') ?></th>
                            <th><?= trans('car.current_odometer') ?></th>
                            <th><?= trans('car.next_maintenance_due') ?></th>
                            <th><?= trans('maintenance.km_until_maintenance') ?></th>
                            <th><?= trans('maintenance.progress') ?></th>
                            <th class="no-print"><?= trans('common.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($carsNearingMaintenance as $car): ?>
                            <tr class="alert-row warning">
                                <td>
                                    <strong><?= htmlspecialchars($car['plate_number']) ?></strong>
                                </td>
                                <td>
                                    <?= htmlspecialchars($car['brand_name']) ?> 
                                    <?= htmlspecialchars($car['model_name']) ?>
                                    <?php if ($car['nickname']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($car['nickname']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($car['current_odometer']) ?> <?= trans('car.km') ?></td>
                                <td><?= number_format($car['next_maintenance_due']) ?> <?= trans('car.km') ?></td>
                                <td>
                                    <span class="badge badge-warning">
                                        <?= number_format($car['km_until_maintenance']) ?> <?= trans('car.km') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $progress = (($car['current_odometer'] - $car['last_maintenance_odometer']) / $car['maintenance_interval']) * 100;
                                    $progress = min($progress, 100);
                                    ?>
                                    <div class="progress-container">
                                        <div class="progress-bar">
                                            <div class="progress-fill" 
                                                 style="width: <?= $progress ?>%; background-color: <?= getProgressColor($progress) ?>;">
                                            </div>
                                        </div>
                                        <span class="progress-text"><?= round($progress) ?>%</span>
                                    </div>
                                </td>
                                <td class="no-print">
                                    <div class="action-buttons">
                                        <a href="/admin/maintenance/create?car_id=<?= $car['id'] ?>" 
                                           class="btn btn-sm btn-success"
                                           title="<?= trans('maintenance.add_maintenance') ?>">
                                            <i class="icon-plus"></i>
                                        </a>
                                        <a href="/admin/cars/view/<?= $car['id'] ?>" 
                                           class="btn btn-sm btn-info"
                                           title="<?= trans('car.view_details') ?>">
                                            <i class="icon-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- رسالة عند عدم وجود تنبيهات -->
<?php if (empty($carsNeedingMaintenance) && empty($carsNearingMaintenance)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state success-state">
                <i class="icon-check-circle"></i>
                <h3><?= trans('maintenance.alerts.no_alerts') ?></h3>
                <p><?= trans('maintenance.alerts.no_alerts_desc') ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// دالة لتحديث الصفحة كل 5 دقائق
setInterval(function() {
    location.reload();
}, 5 * 60 * 1000);
</script>

<style>
.alert-section {
    margin-bottom: 2rem;
}

.alert-section.danger-section {
    border-left: 4px solid #dc3545;
}

.alert-section.warning-section {
    border-left: 4px solid #ffc107;
}

.alert-table {
    margin-bottom: 0;
}

.alert-row.danger {
    background-color: #fff5f5;
}

.alert-row.warning {
    background-color: #fffbf0;
}

.urgency-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
}

.urgency-badge.critical {
    background-color: #dc3545;
    color: white;
}

.urgency-badge.high {
    background-color: #fd7e14;
    color: white;
}

.urgency-badge.medium {
    background-color: #ffc107;
    color: #000;
}

.progress-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.progress-bar {
    flex: 1;
    height: 20px;
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 0.875rem;
    font-weight: 600;
    color: #495057;
    min-width: 40px;
}

.empty-state.success-state {
    color: #28a745;
}

.empty-state.success-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
}

@media print {
    .no-print {
        display: none !important;
    }
    
    .page-actions,
    .stats-grid {
        display: none !important;
    }
    
    .card {
        page-break-inside: avoid;
    }
}

@media (max-width: 768px) {
    .alert-table {
        font-size: 0.875rem;
    }
    
    .alert-table th,
    .alert-table td {
        padding: 0.5rem;
    }
    
    .progress-container {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<?php
/**
 * دالة تحديد مستوى الإلحاح
 */
function getUrgencyLevel($overdue, $interval) {
    $percentage = ($overdue / $interval) * 100;
    
    if ($percentage >= 50) {
        return [
            'class' => 'critical',
            'label' => 'maintenance.urgency.critical'
        ];
    } elseif ($percentage >= 25) {
        return [
            'class' => 'high',
            'label' => 'maintenance.urgency.high'
        ];
    } else {
        return [
            'class' => 'medium',
            'label' => 'maintenance.urgency.medium'
        ];
    }
}

/**
 * دالة تحديد لون شريط التقدم
 */
function getProgressColor($progress) {
    if ($progress >= 90) {
        return '#dc3545'; // أحمر
    } elseif ($progress >= 75) {
        return '#ffc107'; // برتقالي
    } else {
        return '#28a745'; // أخضر
    }
}
?>
