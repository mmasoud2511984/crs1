<?php
/**
 * File: view.php
 * Path: /app/views/backend/customers/view.php
 * Purpose: Customer Details Page - تفاصيل العميل
 * Phase: Phase 6 - Customer Management
 * Created: 2025-10-24
 */

$pageTitle = trans('customer.customer_details');
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h1 class="page-title"><?= trans('customer.customer_details') ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/admin/dashboard"><?= trans('common.dashboard') ?></a></li>
                    <li class="breadcrumb-item"><a href="/admin/customers"><?= trans('customer.customers') ?></a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($customer['full_name']) ?></li>
                </ol>
            </nav>
        </div>
        <div class="col-auto">
            <a href="/admin/customers/edit/<?= $customer['id'] ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> <?= trans('common.edit') ?>
            </a>
            <?php if (!$customer['is_blacklisted']): ?>
                <button type="button" class="btn btn-danger" id="blacklistBtn">
                    <i class="fas fa-ban"></i> <?= trans('customer.add_to_blacklist') ?>
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-success" id="unblacklistBtn">
                    <i class="fas fa-check"></i> <?= trans('customer.remove_from_blacklist') ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Customer Info Card -->
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="customer-avatar-large mb-3">
                    <?php if ($customer['registration_type'] === 'google'): ?>
                        <i class="fab fa-google"></i>
                    <?php else: ?>
                        <?= strtoupper(substr($customer['full_name'], 0, 2)) ?>
                    <?php endif; ?>
                </div>
                <h4><?= htmlspecialchars($customer['full_name']) ?></h4>
                <p class="text-muted"><?= htmlspecialchars($customer['email']) ?></p>
                
                <div class="customer-badges mb-3">
                    <?php if ($customer['is_verified']): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle"></i> <?= trans('customer.verified') ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning">
                            <i class="fas fa-clock"></i> <?= trans('customer.pending_verification') ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($customer['is_blacklisted']): ?>
                        <span class="badge bg-danger">
                            <i class="fas fa-ban"></i> <?= trans('customer.blacklisted') ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="row text-center mb-3">
                    <div class="col-4">
                        <div class="stat-value"><?= $customer['total_rentals'] ?></div>
                        <div class="stat-label"><?= trans('customer.rentals') ?></div>
                    </div>
                    <div class="col-4">
                        <div class="stat-value"><?= number_format($customer['loyalty_points']) ?></div>
                        <div class="stat-label"><?= trans('customer.points') ?></div>
                    </div>
                    <div class="col-4">
                        <div class="stat-value"><?= number_format($customer['total_spent'], 0) ?></div>
                        <div class="stat-label"><?= trans('customer.spent') ?></div>
                    </div>
                </div>

                <?php if (!$customer['is_verified']): ?>
                    <button type="button" class="btn btn-success w-100 mb-2" id="verifyBtn">
                        <i class="fas fa-check"></i> <?= trans('customer.verify_documents') ?>
                    </button>
                <?php endif; ?>
                
                <a href="/admin/customers/loyalty/<?= $customer['id'] ?>" class="btn btn-warning w-100">
                    <i class="fas fa-star"></i> <?= trans('customer.manage_loyalty_points') ?>
                </a>
            </div>
        </div>

        <!-- Contact Info -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0"><?= trans('customer.contact_info') ?></h5>
            </div>
            <div class="card-body">
                <div class="info-item">
                    <i class="fas fa-phone text-primary"></i>
                    <div>
                        <label><?= trans('customer.phone') ?></label>
                        <div><?= htmlspecialchars($customer['phone']) ?></div>
                    </div>
                </div>
                <?php if ($customer['whatsapp']): ?>
                    <div class="info-item">
                        <i class="fab fa-whatsapp text-success"></i>
                        <div>
                            <label><?= trans('customer.whatsapp') ?></label>
                            <div><?= htmlspecialchars($customer['whatsapp']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($customer['address']): ?>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt text-danger"></i>
                        <div>
                            <label><?= trans('customer.address') ?></label>
                            <div><?= htmlspecialchars($customer['address']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#details">
                    <i class="fas fa-info-circle"></i> <?= trans('customer.details') ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#rentals">
                    <i class="fas fa-car"></i> <?= trans('customer.rental_history') ?>
                    <span class="badge bg-primary"><?= $customer['total_rentals'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#violations">
                    <i class="fas fa-exclamation-triangle"></i> <?= trans('customer.violations') ?>
                    <span class="badge bg-danger"><?= $customer['total_violations'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#reviews">
                    <i class="fas fa-star"></i> <?= trans('customer.reviews') ?>
                    <span class="badge bg-warning"><?= $customer['total_reviews'] ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#loyalty">
                    <i class="fas fa-gift"></i> <?= trans('customer.loyalty_points') ?>
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Details Tab -->
            <div class="tab-pane fade show active" id="details">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= trans('customer.personal_info') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label><?= trans('customer.full_name') ?></label>
                                    <div><?= htmlspecialchars($customer['full_name']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label><?= trans('customer.email') ?></label>
                                    <div><?= htmlspecialchars($customer['email']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label><?= trans('customer.phone') ?></label>
                                    <div><?= htmlspecialchars($customer['phone']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label><?= trans('customer.date_of_birth') ?></label>
                                    <div><?= $customer['date_of_birth'] ?? '-' ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label><?= trans('customer.nationality') ?></label>
                                    <div><?= htmlspecialchars($customer['nationality'] ?? '-') ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label><?= trans('customer.registration_type') ?></label>
                                    <div>
                                        <?php if ($customer['registration_type'] === 'google'): ?>
                                            <span class="badge bg-danger">
                                                <i class="fab fa-google"></i> Google
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">
                                                <i class="fas fa-user"></i> <?= trans('customer.manual') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label><?= trans('customer.created_at') ?></label>
                                    <div><?= date('Y-m-d H:i', strtotime($customer['created_at'])) ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <label><?= trans('customer.last_login') ?></label>
                                    <div><?= $customer['last_login'] ? date('Y-m-d H:i', strtotime($customer['last_login'])) : '-' ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documents -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?= trans('customer.documents') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><?= trans('customer.id_document') ?></h6>
                                <div class="detail-item">
                                    <label><?= trans('customer.id_type') ?></label>
                                    <div><?= trans('customer.id_type_' . $customer['id_type']) ?></div>
                                </div>
                                <div class="detail-item">
                                    <label><?= trans('customer.id_number') ?></label>
                                    <div><?= htmlspecialchars($customer['id_number'] ?? '-') ?></div>
                                </div>
                                <div class="detail-item">
                                    <label><?= trans('customer.expiry_date') ?></label>
                                    <div><?= $customer['id_expiry_date'] ?? '-' ?></div>
                                </div>
                                <?php if ($customer['id_document_path']): ?>
                                    <a href="/<?= $customer['id_document_path'] ?>" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="fas fa-file"></i> <?= trans('customer.view_document') ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6><?= trans('customer.license_document') ?></h6>
                                <div class="detail-item">
                                    <label><?= trans('customer.license_number') ?></label>
                                    <div><?= htmlspecialchars($customer['license_number'] ?? '-') ?></div>
                                </div>
                                <div class="detail-item">
                                    <label><?= trans('customer.expiry_date') ?></label>
                                    <div><?= $customer['license_expiry_date'] ?? '-' ?></div>
                                </div>
                                <?php if ($customer['license_document_path']): ?>
                                    <a href="/<?= $customer['license_document_path'] ?>" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="fas fa-file"></i> <?= trans('customer.view_document') ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($customer['is_blacklisted'] && $customer['blacklist_reason']): ?>
                    <div class="alert alert-danger mt-3">
                        <h5><i class="fas fa-ban"></i> <?= trans('customer.blacklist_reason') ?></h5>
                        <p><?= htmlspecialchars($customer['blacklist_reason']) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Rentals Tab -->
            <div class="tab-pane fade" id="rentals">
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($rentalHistory)): ?>
                            <p class="text-center text-muted"><?= trans('customer.no_rentals') ?></p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th><?= trans('rental.contract_number') ?></th>
                                            <th><?= trans('rental.car') ?></th>
                                            <th><?= trans('rental.dates') ?></th>
                                            <th><?= trans('rental.total_cost') ?></th>
                                            <th><?= trans('rental.status') ?></th>
                                            <th><?= trans('common.actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rentalHistory as $rental): ?>
                                            <tr>
                                                <td><?= $rental['contract_number'] ?></td>
                                                <td>
                                                    <?= $rental['brand_name'] ?> <?= $rental['model_name'] ?><br>
                                                    <small class="text-muted"><?= $rental['plate_number'] ?></small>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?= date('Y-m-d', strtotime($rental['start_date'])) ?><br>
                                                        <?= date('Y-m-d', strtotime($rental['end_date'])) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <strong><?= number_format($rental['total_paid'], 2) ?></strong>
                                                    <?= trans('common.currency') ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $rental['status'] === 'completed' ? 'success' : 'info' ?>">
                                                        <?= trans('rental.status_' . $rental['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="/admin/rentals/view/<?= $rental['id'] ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Violations Tab -->
            <div class="tab-pane fade" id="violations">
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($violations)): ?>
                            <p class="text-center text-muted"><?= trans('customer.no_violations') ?></p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($violations as $violation): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <h6><?= htmlspecialchars($violation['violation_type']) ?></h6>
                                            <span class="badge bg-danger"><?= number_format($violation['fine_amount'], 2) ?> <?= trans('common.currency') ?></span>
                                        </div>
                                        <p class="mb-1"><?= htmlspecialchars($violation['description']) ?></p>
                                        <small class="text-muted">
                                            <?= $violation['brand_name'] ?> <?= $violation['model_name'] ?> (<?= $violation['plate_number'] ?>)
                                            - <?= date('Y-m-d', strtotime($violation['violation_date'])) ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Reviews Tab -->
            <div class="tab-pane fade" id="reviews">
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($reviews)): ?>
                            <p class="text-center text-muted"><?= trans('customer.no_reviews') ?></p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between mb-2">
                                            <div>
                                                <h6><?= htmlspecialchars($review['review_title']) ?></h6>
                                                <div class="rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <span class="badge bg-<?= $review['is_approved'] ? 'success' : 'warning' ?>">
                                                <?= trans($review['is_approved'] ? 'review.approved' : 'review.pending') ?>
                                            </span>
                                        </div>
                                        <p><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                                        <small class="text-muted">
                                            <?= $review['brand_name'] ?> <?= $review['model_name'] ?>
                                            - <?= date('Y-m-d', strtotime($review['created_at'])) ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Loyalty Points Tab -->
            <div class="tab-pane fade" id="loyalty">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?= trans('customer.loyalty_history') ?></h5>
                        <span class="badge bg-warning">
                            <?= trans('customer.current_balance') ?>: <?= number_format($customer['loyalty_points']) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($loyaltyHistory)): ?>
                            <p class="text-center text-muted"><?= trans('customer.no_loyalty_transactions') ?></p>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($loyaltyHistory as $transaction): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker bg-<?= $transaction['points'] > 0 ? 'success' : 'danger' ?>">
                                            <i class="fas fa-<?= $transaction['points'] > 0 ? 'plus' : 'minus' ?>"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between">
                                                <h6><?= trans('loyalty.type_' . $transaction['transaction_type']) ?></h6>
                                                <span class="badge bg-<?= $transaction['points'] > 0 ? 'success' : 'danger' ?>">
                                                    <?= $transaction['points'] > 0 ? '+' : '' ?><?= number_format($transaction['points']) ?>
                                                </span>
                                            </div>
                                            <p class="mb-1"><?= htmlspecialchars($transaction['description']) ?></p>
                                            <small class="text-muted">
                                                <?= date('Y-m-d H:i', strtotime($transaction['created_at'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($expiringPoints)): ?>
                    <div class="alert alert-warning mt-3">
                        <h6><i class="fas fa-exclamation-triangle"></i> <?= trans('customer.expiring_points') ?></h6>
                        <ul class="mb-0">
                            <?php foreach ($expiringPoints as $point): ?>
                                <li>
                                    <?= number_format($point['points']) ?> <?= trans('customer.points') ?>
                                    - <?= trans('customer.expires_in') ?> <?= $point['days_until_expiry'] ?> <?= trans('customer.days') ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Verify Documents Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= trans('customer.verify_documents') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><?= trans('customer.verification_notes') ?></label>
                    <textarea class="form-control" id="verifyNotes" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= trans('common.cancel') ?></button>
                <button type="button" class="btn btn-success" id="confirmVerify"><?= trans('common.confirm') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Blacklist Modal -->
<div class="modal fade" id="blacklistModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= trans('customer.blacklist_reason') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><?= trans('customer.reason') ?> *</label>
                    <textarea class="form-control" id="blacklistReason" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= trans('common.cancel') ?></button>
                <button type="button" class="btn btn-danger" id="confirmBlacklist"><?= trans('common.confirm') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
const customerId = <?= $customer['id'] ?>;

// Verify documents
document.getElementById('verifyBtn')?.addEventListener('click', () => {
    new bootstrap.Modal(document.getElementById('verifyModal')).show();
});

document.getElementById('confirmVerify')?.addEventListener('click', async () => {
    const notes = document.getElementById('verifyNotes').value;
    
    const res = await fetch(`/admin/customers/verify-documents/${customerId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= csrf_token() ?>'
        },
        body: JSON.stringify({ verified: true, notes })
    });
    
    const data = await res.json();
    if (data.success) {
        location.reload();
    } else {
        alert(data.message);
    }
});

// Blacklist
document.getElementById('blacklistBtn')?.addEventListener('click', () => {
    new bootstrap.Modal(document.getElementById('blacklistModal')).show();
});

document.getElementById('confirmBlacklist')?.addEventListener('click', async () => {
    const reason = document.getElementById('blacklistReason').value;
    
    if (!reason.trim()) {
        alert('<?= trans('customer.blacklist_reason_required') ?>');
        return;
    }
    
    const res = await fetch(`/admin/customers/toggle-blacklist/${customerId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= csrf_token() ?>'
        },
        body: JSON.stringify({ blacklisted: true, reason })
    });
    
    const data = await res.json();
    if (data.success) {
        location.reload();
    } else {
        alert(data.message);
    }
});

// Unblacklist
document.getElementById('unblacklistBtn')?.addEventListener('click', async () => {
    if (!confirm('<?= trans('customer.confirm_remove_blacklist') ?>')) return;
    
    const res = await fetch(`/admin/customers/toggle-blacklist/${customerId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= csrf_token() ?>'
        },
        body: JSON.stringify({ blacklisted: false, reason: '' })
    });
    
    const data = await res.json();
    if (data.success) {
        location.reload();
    } else {
        alert(data.message);
    }
});
</script>

<style>
.customer-avatar-large {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 48px;
    margin: 0 auto;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #2d3748;
}

.stat-label {
    font-size: 12px;
    color: #718096;
    text-transform: uppercase;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 15px;
}

.info-item i {
    font-size: 20px;
    margin-top: 5px;
}

.info-item label {
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 2px;
    font-size: 12px;
}

.detail-item {
    margin-bottom: 20px;
}

.detail-item label {
    font-weight: 600;
    color: #4a5568;
    display: block;
    margin-bottom: 5px;
    font-size: 13px;
}

.timeline {
    position: relative;
    padding-left: 40px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e2e8f0;
}

.timeline-item:last-child {
    border-bottom: none;
}

.timeline-marker {
    position: absolute;
    left: -40px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.timeline-content {
    flex: 1;
}
</style>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
