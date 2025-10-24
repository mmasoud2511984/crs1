<?php
/**
 * File: index.php
 * Path: /app/views/backend/cars/index.php
 * Purpose: عرض قائمة السيارات مع المصفيات والبحث
 * Phase: Phase 4 - Car Management
 * Created: 2025-10-24
 */

defined('APP_PATH') or die('Direct access not allowed');
?>

<!DOCTYPE html>
<html lang="<?= current_language() ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= setting('site_name') ?></title>
    <?php include VIEW_PATH . '/backend/layouts/head.php'; ?>
</head>
<body class="admin-body">
    <?php include VIEW_PATH . '/backend/layouts/header.php'; ?>
    <?php include VIEW_PATH . '/backend/layouts/sidebar.php'; ?>

    <main class="main-content">
        <!-- Breadcrumbs -->
        <div class="breadcrumbs">
            <?php foreach ($breadcrumbs as $crumb): ?>
                <?php if (!empty($crumb['url'])): ?>
                    <a href="<?= $crumb['url'] ?>"><?= $crumb['title'] ?></a>
                <?php else: ?>
                    <span><?= $crumb['title'] ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1><?= trans('cars.management') ?></h1>
            <div class="page-actions">
                <?php if (has_permission('cars.create')): ?>
                    <a href="/admin/cars/create" class="btn btn-primary">
                        <i class="icon-plus"></i>
                        <?= trans('cars.add_new') ?>
                    </a>
                <?php endif; ?>
                <a href="/admin/cars/brands" class="btn btn-secondary">
                    <i class="icon-tag"></i>
                    <?= trans('cars.manage_brands') ?>
                </a>
                <a href="/admin/cars/export" class="btn btn-secondary">
                    <i class="icon-download"></i>
                    <?= trans('common.export') ?>
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-primary">
                    <i class="icon-car"></i>
                </div>
                <div class="stat-details">
                    <h3><?= number_format($statistics['total_cars']) ?></h3>
                    <p><?= trans('cars.total_cars') ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-success">
                    <i class="icon-check"></i>
                </div>
                <div class="stat-details">
                    <h3><?= number_format($statistics['available']) ?></h3>
                    <p><?= trans('cars.available') ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-warning">
                    <i class="icon-clock"></i>
                </div>
                <div class="stat-details">
                    <h3><?= number_format($statistics['rented']) ?></h3>
                    <p><?= trans('cars.rented') ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-danger">
                    <i class="icon-tool"></i>
                </div>
                <div class="stat-details">
                    <h3><?= number_format($statistics['maintenance']) ?></h3>
                    <p><?= trans('cars.in_maintenance') ?></p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="card-header">
                <h3><?= trans('common.filters') ?></h3>
            </div>
            <div class="card-body">
                <form method="GET" action="/admin/cars" class="filters-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label><?= trans('common.search') ?></label>
                            <input type="text" name="search" value="<?= e($filters['search']) ?>" 
                                   placeholder="<?= trans('cars.search_placeholder') ?>">
                        </div>

                        <div class="form-group">
                            <label><?= trans('cars.status') ?></label>
                            <select name="status">
                                <option value=""><?= trans('common.all') ?></option>
                                <option value="available" <?= $filters['status'] == 'available' ? 'selected' : '' ?>>
                                    <?= trans('cars.available') ?>
                                </option>
                                <option value="rented" <?= $filters['status'] == 'rented' ? 'selected' : '' ?>>
                                    <?= trans('cars.rented') ?>
                                </option>
                                <option value="maintenance" <?= $filters['status'] == 'maintenance' ? 'selected' : '' ?>>
                                    <?= trans('cars.maintenance') ?>
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><?= trans('cars.brand') ?></label>
                            <select name="brand_id" id="brandFilter">
                                <option value=""><?= trans('common.all') ?></option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?= $brand['id'] ?>" 
                                            <?= $filters['brand_id'] == $brand['id'] ? 'selected' : '' ?>>
                                        <?= e($brand['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><?= trans('cars.transmission') ?></label>
                            <select name="transmission">
                                <option value=""><?= trans('common.all') ?></option>
                                <option value="automatic" <?= $filters['transmission'] == 'automatic' ? 'selected' : '' ?>>
                                    <?= trans('cars.automatic') ?>
                                </option>
                                <option value="manual" <?= $filters['transmission'] == 'manual' ? 'selected' : '' ?>>
                                    <?= trans('cars.manual') ?>
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon-search"></i>
                            <?= trans('common.filter') ?>
                        </button>
                        <a href="/admin/cars" class="btn btn-secondary">
                            <i class="icon-refresh"></i>
                            <?= trans('common.reset') ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cars Table -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($cars)): ?>
                    <div class="empty-state">
                        <i class="icon-car"></i>
                        <p><?= trans('cars.no_cars_found') ?></p>
                        <?php if (has_permission('cars.create')): ?>
                            <a href="/admin/cars/create" class="btn btn-primary">
                                <?= trans('cars.add_first_car') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th><?= trans('cars.image') ?></th>
                                    <th><?= trans('cars.plate_number') ?></th>
                                    <th><?= trans('cars.brand_model') ?></th>
                                    <th><?= trans('cars.year') ?></th>
                                    <th><?= trans('cars.daily_rate') ?></th>
                                    <th><?= trans('cars.status') ?></th>
                                    <th><?= trans('cars.featured') ?></th>
                                    <th><?= trans('common.actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cars as $car): ?>
                                    <tr>
                                        <td>
                                            <?php if ($car['primary_image']): ?>
                                                <img src="<?= e($car['primary_image']) ?>" 
                                                     alt="<?= e($car['nickname'] ?: $car['brand_name']) ?>" 
                                                     class="car-thumb">
                                            <?php else: ?>
                                                <div class="no-image">
                                                    <i class="icon-car"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= e($car['plate_number']) ?></strong>
                                            <?php if ($car['nickname']): ?>
                                                <small class="text-muted"><?= e($car['nickname']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= e($car['brand_name']) ?> <?= e($car['model_name']) ?>
                                        </td>
                                        <td><?= e($car['manufacturing_year']) ?></td>
                                        <td>
                                            <strong><?= format_money($car['daily_rate']) ?></strong>
                                            / <?= trans('common.day') ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= get_status_class($car['status']) ?>">
                                                <?= trans('cars.status_' . $car['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($car['is_featured']): ?>
                                                <span class="badge badge-primary">
                                                    <i class="icon-star"></i>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="/admin/cars/<?= $car['id'] ?>" 
                                                   class="btn-icon" title="<?= trans('common.view') ?>">
                                                    <i class="icon-eye"></i>
                                                </a>
                                                <?php if (has_permission('cars.edit')): ?>
                                                    <a href="/admin/cars/<?= $car['id'] ?>/edit" 
                                                       class="btn-icon" title="<?= trans('common.edit') ?>">
                                                        <i class="icon-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (has_permission('cars.delete')): ?>
                                                    <button type="button" 
                                                            class="btn-icon text-danger delete-car" 
                                                            data-id="<?= $car['id'] ?>"
                                                            title="<?= trans('common.delete') ?>">
                                                        <i class="icon-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pagination['last_page'] > 1): ?>
                        <div class="pagination">
                            <?php if ($pagination['current_page'] > 1): ?>
                                <a href="?page=<?= $pagination['current_page'] - 1 ?>" class="page-link">
                                    <?= trans('common.previous') ?>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
                                <a href="?page=<?= $i ?>" 
                                   class="page-link <?= $i == $pagination['current_page'] ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
                                <a href="?page=<?= $pagination['current_page'] + 1 ?>" class="page-link">
                                    <?= trans('common.next') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include VIEW_PATH . '/backend/layouts/footer.php'; ?>

    <script>
    // حذف سيارة
    document.querySelectorAll('.delete-car').forEach(btn => {
        btn.addEventListener('click', function() {
            const carId = this.dataset.id;
            
            if (confirm('<?= trans('cars.confirm_delete') ?>')) {
                fetch('/admin/cars/' + carId + '/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?= csrf_token() ?>'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('<?= trans('errors.general') ?>');
                });
            }
        });
    });
    </script>
</body>
</html>
