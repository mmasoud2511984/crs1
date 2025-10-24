<?php
/**
 * File: view.php
 * Path: /app/views/backend/maintenance/view.php
 * Purpose: صفحة عرض تفاصيل سجل الصيانة
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
        <h1><?= trans('maintenance.view_record') ?> #<?= $maintenance['id'] ?></h1>
        <p><?= trans('maintenance.record_date') ?>: <?= date('Y-m-d H:i', strtotime($maintenance['created_at'])) ?></p>
    </div>
    <div class="page-actions">
        <a href="/admin/maintenance" class="btn btn-secondary">
            <i class="icon-arrow-left"></i>
            <?= trans('common.back') ?>
        </a>
        <a href="/admin/maintenance/edit/<?= $maintenance['id'] ?>" class="btn btn-warning">
            <i class="icon-edit"></i>
            <?= trans('common.edit') ?>
        </a>
        <button type="button" class="btn btn-danger" onclick="confirmDelete(<?= $maintenance['id'] ?>)">
            <i class="icon-trash"></i>
            <?= trans('common.delete') ?>
        </button>
    </div>
</div>

<div class="view-container">
    <!-- معلومات السيارة -->
    <div class="card">
        <div class="card-header">
            <h3><?= trans('maintenance.car_info') ?></h3>
            <a href="/admin/cars/view/<?= $maintenance['car_id'] ?>" class="btn btn-sm btn-primary">
                <?= trans('car.view_details') ?>
            </a>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label><?= trans('car.plate_number') ?>:</label>
                    <span class="value"><?= htmlspecialchars($maintenance['plate_number']) ?></span>
                </div>
                <div class="info-item">
                    <label><?= trans('car.brand') ?>:</label>
                    <span class="value"><?= htmlspecialchars($maintenance['brand_name']) ?></span>
                </div>
                <div class="info-item">
                    <label><?= trans('car.model') ?>:</label>
                    <span class="value"><?= htmlspecialchars($maintenance['model_name']) ?></span>
                </div>
                <?php if ($maintenance['nickname']): ?>
                    <div class="info-item">
                        <label><?= trans('car.nickname') ?>:</label>
                        <span class="value"><?= htmlspecialchars($maintenance['nickname']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-item">
                    <label><?= trans('car.current_odometer') ?>:</label>
                    <span class="value"><?= number_format($maintenance['current_odometer']) ?> <?= trans('car.km') ?></span>
                </div>
                <div class="info-item">
                    <label><?= trans('car.last_maintenance_odometer') ?>:</label>
                    <span class="value"><?= number_format($maintenance['last_maintenance_odometer']) ?> <?= trans('car.km') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- تفاصيل الصيانة -->
    <div class="card">
        <div class="card-header">
            <h3><?= trans('maintenance.maintenance_details') ?></h3>
            <span class="badge badge-lg badge-<?= getMaintenanceTypeBadge($maintenance['maintenance_type']) ?>">
                <?= trans('maintenance.type.' . $maintenance['maintenance_type']) ?>
            </span>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label><?= trans('maintenance.type') ?>:</label>
                    <span class="value">
                        <span class="badge badge-<?= getMaintenanceTypeBadge($maintenance['maintenance_type']) ?>">
                            <?= trans('maintenance.type.' . $maintenance['maintenance_type']) ?>
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <label><?= trans('maintenance.date') ?>:</label>
                    <span class="value"><?= date('Y-m-d', strtotime($maintenance['maintenance_date'])) ?></span>
                </div>
                <?php if ($maintenance['odometer_reading']): ?>
                    <div class="info-item">
                        <label><?= trans('maintenance.odometer') ?>:</label>
                        <span class="value"><?= number_format($maintenance['odometer_reading']) ?> <?= trans('car.km') ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($maintenance['cost']): ?>
                    <div class="info-item">
                        <label><?= trans('maintenance.cost') ?>:</label>
                        <span class="value cost"><?= number_format($maintenance['cost'], 2) ?> <?= trans('currency') ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($maintenance['service_center']): ?>
                    <div class="info-item">
                        <label><?= trans('maintenance.service_center') ?>:</label>
                        <span class="value"><?= htmlspecialchars($maintenance['service_center']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($maintenance['technician_name']): ?>
                    <div class="info-item">
                        <label><?= trans('maintenance.technician_name') ?>:</label>
                        <span class="value"><?= htmlspecialchars($maintenance['technician_name']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($maintenance['next_maintenance_date']): ?>
                    <div class="info-item">
                        <label><?= trans('maintenance.next_maintenance_date') ?>:</label>
                        <span class="value"><?= date('Y-m-d', strtotime($maintenance['next_maintenance_date'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- الوصف -->
            <div class="section-block">
                <h4><?= trans('maintenance.description') ?>:</h4>
                <div class="description-box">
                    <?= nl2br(htmlspecialchars($maintenance['description'])) ?>
                </div>
            </div>

            <!-- القطع المستبدلة -->
            <?php if ($maintenance['parts_replaced']): ?>
                <div class="section-block">
                    <h4><?= trans('maintenance.parts_replaced') ?>:</h4>
                    <div class="parts-box">
                        <?= nl2br(htmlspecialchars($maintenance['parts_replaced'])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ملاحظات -->
            <?php if ($maintenance['notes']): ?>
                <div class="section-block">
                    <h4><?= trans('maintenance.notes') ?>:</h4>
                    <div class="notes-box">
                        <?= nl2br(htmlspecialchars($maintenance['notes'])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- الإيصال -->
            <?php if ($maintenance['receipt_path']): ?>
                <div class="section-block">
                    <h4><?= trans('maintenance.receipt') ?>:</h4>
                    <div class="receipt-container">
                        <?php 
                        $extension = pathinfo($maintenance['receipt_path'], PATHINFO_EXTENSION);
                        if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])): 
                        ?>
                            <img src="/<?= htmlspecialchars($maintenance['receipt_path']) ?>" 
                                 alt="Receipt" class="receipt-image"
                                 onclick="openImageModal(this.src)">
                        <?php else: ?>
                            <a href="/<?= htmlspecialchars($maintenance['receipt_path']) ?>" 
                               class="btn btn-primary" target="_blank">
                                <i class="icon-download"></i>
                                <?= trans('maintenance.download_receipt') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- معلومات إضافية -->
            <div class="section-block">
                <h4><?= trans('maintenance.additional_info') ?>:</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <label><?= trans('maintenance.created_by') ?>:</label>
                        <span class="value"><?= htmlspecialchars($maintenance['created_by_name'] ?? trans('common.unknown')) ?></span>
                    </div>
                    <div class="info-item">
                        <label><?= trans('maintenance.created_at') ?>:</label>
                        <span class="value"><?= date('Y-m-d H:i', strtotime($maintenance['created_at'])) ?></span>
                    </div>
                    <div class="info-item">
                        <label><?= trans('maintenance.updated_at') ?>:</label>
                        <span class="value"><?= date('Y-m-d H:i', strtotime($maintenance['updated_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- سجلات الصيانة السابقة -->
    <?php if (!empty($previousMaintenance)): ?>
        <div class="card">
            <div class="card-header">
                <h3><?= trans('maintenance.previous_records') ?></h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= trans('maintenance.date') ?></th>
                                <th><?= trans('maintenance.type') ?></th>
                                <th><?= trans('maintenance.odometer') ?></th>
                                <th><?= trans('maintenance.cost') ?></th>
                                <th><?= trans('maintenance.description') ?></th>
                                <th><?= trans('common.actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previousMaintenance as $record): ?>
                                <?php if ($record['id'] != $maintenance['id']): ?>
                                    <tr>
                                        <td><?= date('Y-m-d', strtotime($record['maintenance_date'])) ?></td>
                                        <td>
                                            <span class="badge badge-<?= getMaintenanceTypeBadge($record['maintenance_type']) ?>">
                                                <?= trans('maintenance.type.' . $record['maintenance_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $record['odometer_reading'] ? number_format($record['odometer_reading']) . ' ' . trans('car.km') : '-' ?>
                                        </td>
                                        <td>
                                            <?= $record['cost'] ? number_format($record['cost'], 2) . ' ' . trans('currency') : '-' ?>
                                        </td>
                                        <td class="text-truncate" style="max-width: 300px;">
                                            <?= htmlspecialchars(substr($record['description'], 0, 100)) ?>...
                                        </td>
                                        <td>
                                            <a href="/admin/maintenance/view/<?= $record['id'] ?>" class="btn btn-sm btn-info">
                                                <i class="icon-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- نموذج الحذف -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
</form>

<!-- Modal لعرض الصورة -->
<div id="imageModal" class="modal" style="display: none;">
    <span class="modal-close" onclick="closeImageModal()">&times;</span>
    <img class="modal-content" id="modalImage">
</div>

<script>
function confirmDelete(id) {
    if (confirm('<?= trans('maintenance.confirm_delete') ?>')) {
        const form = document.getElementById('deleteForm');
        form.action = '/admin/maintenance/delete/' + id;
        form.submit();
    }
}

function openImageModal(src) {
    document.getElementById('imageModal').style.display = 'flex';
    document.getElementById('modalImage').src = src;
}

function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
}
</script>

<style>
.view-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1rem;
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

.info-item .value {
    font-size: 1rem;
    font-weight: 600;
    color: #212529;
}

.info-item .value.cost {
    color: #28a745;
    font-size: 1.125rem;
}

.section-block {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #dee2e6;
}

.section-block h4 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #495057;
}

.description-box,
.parts-box,
.notes-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    line-height: 1.6;
}

.receipt-container {
    margin-top: 0.5rem;
}

.receipt-image {
    max-width: 100%;
    max-height: 500px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: transform 0.2s;
}

.receipt-image:hover {
    transform: scale(1.02);
}

.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    padding-top: 50px;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.9);
    justify-content: center;
    align-items: center;
}

.modal-content {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
}

.modal-close {
    position: absolute;
    top: 15px;
    right: 35px;
    color: #f1f1f1;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
}

.modal-close:hover {
    color: #bbb;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .page-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .page-actions .btn {
        width: 100%;
    }
}
</style>

<?php
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
