<?php
/**
 * File: index.php
 * Path: /app/views/backend/customers/index.php
 * Purpose: Customers List Page - قائمة العملاء
 * Phase: Phase 6 - Customer Management
 * Created: 2025-10-24
 */

$pageTitle = trans('customer.customers_list');
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h1 class="page-title"><?= trans('customer.customers_list') ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/admin/dashboard"><?= trans('common.dashboard') ?></a></li>
                    <li class="breadcrumb-item active"><?= trans('customer.customers') ?></li>
                </ol>
            </nav>
        </div>
        <div class="col-auto">
            <a href="/admin/customers/create" class="btn btn-primary">
                <i class="fas fa-plus"></i> <?= trans('customer.add_customer') ?>
            </a>
            <a href="/admin/customers/export<?= !empty($filters['search']) ? '?search=' . urlencode($filters['search']) : '' ?>" class="btn btn-success">
                <i class="fas fa-file-excel"></i> <?= trans('common.export') ?>
            </a>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-icon bg-primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-content">
                <div class="stats-number"><?= number_format($stats['total_customers']) ?></div>
                <div class="stats-label"><?= trans('customer.total_customers') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-icon bg-success">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stats-content">
                <div class="stats-number"><?= number_format($stats['verified_customers']) ?></div>
                <div class="stats-label"><?= trans('customer.verified_customers') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-icon bg-info">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="stats-content">
                <div class="stats-number"><?= number_format($stats['new_this_month']) ?></div>
                <div class="stats-label"><?= trans('customer.new_this_month') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-icon bg-danger">
                <i class="fas fa-user-times"></i>
            </div>
            <div class="stats-content">
                <div class="stats-number"><?= number_format($stats['blacklisted_customers']) ?></div>
                <div class="stats-label"><?= trans('customer.blacklisted') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="/admin/customers" id="filterForm">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="<?= trans('customer.search_placeholder') ?>"
                           value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <select name="verified" class="form-select">
                        <option value=""><?= trans('customer.all_verification') ?></option>
                        <option value="1" <?= $filters['is_verified'] === '1' ? 'selected' : '' ?>><?= trans('customer.verified') ?></option>
                        <option value="0" <?= $filters['is_verified'] === '0' ? 'selected' : '' ?>><?= trans('customer.not_verified') ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="blacklisted" class="form-select">
                        <option value=""><?= trans('customer.all_status') ?></option>
                        <option value="0" <?= $filters['is_blacklisted'] === '0' ? 'selected' : '' ?>><?= trans('customer.active') ?></option>
                        <option value="1" <?= $filters['is_blacklisted'] === '1' ? 'selected' : '' ?>><?= trans('customer.blacklisted') ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select">
                        <option value=""><?= trans('customer.all_types') ?></option>
                        <option value="form" <?= $filters['registration_type'] === 'form' ? 'selected' : '' ?>><?= trans('customer.form_registration') ?></option>
                        <option value="google" <?= $filters['registration_type'] === 'google' ? 'selected' : '' ?>><?= trans('customer.google_registration') ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> <?= trans('common.search') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><?= trans('customer.id') ?></th>
                        <th><?= trans('customer.full_name') ?></th>
                        <th><?= trans('customer.email') ?></th>
                        <th><?= trans('customer.phone') ?></th>
                        <th><?= trans('customer.total_rentals') ?></th>
                        <th><?= trans('customer.total_spent') ?></th>
                        <th><?= trans('customer.loyalty_points') ?></th>
                        <th><?= trans('customer.status') ?></th>
                        <th><?= trans('common.actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <?= trans('customer.no_customers_found') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?= $customer['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="customer-avatar me-2">
                                            <?php if ($customer['registration_type'] === 'google'): ?>
                                                <i class="fab fa-google text-danger"></i>
                                            <?php else: ?>
                                                <?= strtoupper(substr($customer['full_name'], 0, 2)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($customer['full_name']) ?></div>
                                            <?php if ($customer['is_verified']): ?>
                                                <span class="badge badge-sm bg-success">
                                                    <i class="fas fa-check"></i> <?= trans('customer.verified') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($customer['email']) ?></td>
                                <td><?= htmlspecialchars($customer['phone']) ?></td>
                                <td>
                                    <span class="badge bg-info"><?= $customer['total_rentals'] ?></span>
                                </td>
                                <td>
                                    <strong><?= number_format($customer['total_spent'], 2) ?></strong> 
                                    <?= trans('common.currency') ?>
                                </td>
                                <td>
                                    <span class="badge bg-warning"><?= number_format($customer['loyalty_points']) ?></span>
                                </td>
                                <td>
                                    <?php if ($customer['is_blacklisted']): ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-ban"></i> <?= trans('customer.blacklisted') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> <?= trans('customer.active') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="/admin/customers/view/<?= $customer['id'] ?>" 
                                           class="btn btn-sm btn-info"
                                           title="<?= trans('common.view') ?>">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/admin/customers/edit/<?= $customer['id'] ?>" 
                                           class="btn btn-sm btn-primary"
                                           title="<?= trans('common.edit') ?>">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger delete-btn"
                                                data-id="<?= $customer['id'] ?>"
                                                data-name="<?= htmlspecialchars($customer['full_name']) ?>"
                                                title="<?= trans('common.delete') ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total'] > 1): ?>
            <div class="pagination-wrapper mt-4">
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $pagination['total']; $i++): ?>
                            <li class="page-item <?= $i === $pagination['current'] ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?><?= !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Delete customer
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const name = this.dataset.name;
        
        if (confirm('<?= trans('customer.confirm_delete') ?>'.replace(':name', name))) {
            fetch(`/admin/customers/delete/${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?= csrf_token() ?>'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }
    });
});
</script>

<style>
.customer-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 12px;
}

.stats-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.stats-content {
    flex: 1;
}

.stats-number {
    font-size: 24px;
    font-weight: bold;
    color: #2d3748;
}

.stats-label {
    font-size: 14px;
    color: #718096;
    margin-top: 5px;
}
</style>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
