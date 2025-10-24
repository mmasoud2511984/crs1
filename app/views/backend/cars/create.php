<?php
/**
 * File: create.php
 * Path: /app/views/backend/cars/create.php
 * Purpose: صفحة إضافة سيارة جديدة مع drag & drop للصور
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
    <style>
        .image-upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .image-upload-area:hover,
        .image-upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .preview-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .preview-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .preview-item .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
        }
    </style>
</head>
<body class="admin-body">
    <?php include VIEW_PATH . '/backend/layouts/header.php'; ?>
    <?php include VIEW_PATH . '/backend/layouts/sidebar.php'; ?>

    <main class="main-content">
        <div class="breadcrumbs">
            <?php foreach ($breadcrumbs as $crumb): ?>
                <?php if (!empty($crumb['url'])): ?>
                    <a href="<?= $crumb['url'] ?>"><?= $crumb['title'] ?></a>
                <?php else: ?>
                    <span><?= $crumb['title'] ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <form method="POST" action="/admin/cars/store" enctype="multipart/form-data" id="carForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="page-header">
                <h1><?= trans('cars.add_new') ?></h1>
                <div class="page-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="icon-save"></i>
                        <?= trans('common.save') ?>
                    </button>
                    <a href="/admin/cars" class="btn btn-secondary">
                        <?= trans('common.cancel') ?>
                    </a>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="card">
                <div class="card-header">
                    <h3><?= trans('cars.basic_info') ?></h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="required"><?= trans('cars.brand') ?></label>
                            <select name="brand_id" id="brandSelect" required>
                                <option value=""><?= trans('common.select') ?></option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?= $brand['id'] ?>">
                                        <?= e($brand['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group col-md-4">
                            <label class="required"><?= trans('cars.model') ?></label>
                            <select name="model_id" id="modelSelect" required>
                                <option value=""><?= trans('cars.select_brand_first') ?></option>
                            </select>
                        </div>

                        <div class="form-group col-md-4">
                            <label><?= trans('cars.nickname') ?></label>
                            <input type="text" name="nickname" placeholder="<?= trans('cars.nickname_placeholder') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="required"><?= trans('cars.plate_number') ?></label>
                            <input type="text" name="plate_number" required>
                        </div>

                        <div class="form-group col-md-4">
                            <label><?= trans('cars.vin_number') ?></label>
                            <input type="text" name="vin_number">
                        </div>

                        <div class="form-group col-md-4">
                            <label><?= trans('cars.color') ?></label>
                            <input type="text" name="color">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label><?= trans('cars.manufacturing_year') ?></label>
                            <input type="number" name="manufacturing_year" min="1990" max="<?= date('Y') + 1 ?>">
                        </div>

                        <div class="form-group col-md-3">
                            <label><?= trans('cars.transmission') ?></label>
                            <select name="transmission">
                                <option value="automatic"><?= trans('cars.automatic') ?></option>
                                <option value="manual"><?= trans('cars.manual') ?></option>
                            </select>
                        </div>

                        <div class="form-group col-md-3">
                            <label><?= trans('cars.fuel_type') ?></label>
                            <select name="fuel_type">
                                <option value="petrol"><?= trans('cars.petrol') ?></option>
                                <option value="diesel"><?= trans('cars.diesel') ?></option>
                                <option value="electric"><?= trans('cars.electric') ?></option>
                                <option value="hybrid"><?= trans('cars.hybrid') ?></option>
                            </select>
                        </div>

                        <div class="form-group col-md-3">
                            <label><?= trans('cars.seats') ?></label>
                            <input type="number" name="seats" min="2" max="12" value="5">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing -->
            <div class="card">
                <div class="card-header">
                    <h3><?= trans('cars.pricing') ?></h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="required"><?= trans('cars.daily_rate') ?></label>
                            <input type="number" name="daily_rate" step="0.01" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label><?= trans('cars.weekly_rate') ?></label>
                            <input type="number" name="weekly_rate" step="0.01">
                        </div>
                        <div class="form-group col-md-3">
                            <label><?= trans('cars.monthly_rate') ?></label>
                            <input type="number" name="monthly_rate" step="0.01">
                        </div>
                        <div class="form-group col-md-3">
                            <label><?= trans('cars.driver_daily_rate') ?></label>
                            <input type="number" name="driver_daily_rate" step="0.01">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Images -->
            <div class="card">
                <div class="card-header">
                    <h3><?= trans('cars.images') ?></h3>
                </div>
                <div class="card-body">
                    <div class="image-upload-area" id="dropZone">
                        <i class="icon-upload" style="font-size: 48px; color: #ccc;"></i>
                        <p><?= trans('cars.drag_drop_images') ?></p>
                        <input type="file" name="images[]" id="imageInput" 
                               accept="image/*" multiple style="display: none;">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('imageInput').click()">
                            <?= trans('cars.select_images') ?>
                        </button>
                    </div>
                    <div class="preview-grid" id="previewGrid"></div>
                </div>
            </div>

            <!-- Features -->
            <div class="card">
                <div class="card-header">
                    <h3><?= trans('cars.features') ?></h3>
                </div>
                <div class="card-body">
                    <div class="features-grid">
                        <?php foreach ($features as $feature): ?>
                            <label class="feature-checkbox">
                                <input type="checkbox" name="features[]" value="<?= $feature['id'] ?>">
                                <?php if ($feature['icon']): ?>
                                    <i class="<?= e($feature['icon']) ?>"></i>
                                <?php endif; ?>
                                <?= trans('features.' . $feature['feature_key']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Options -->
            <div class="card">
                <div class="card-header">
                    <h3><?= trans('cars.options') ?></h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_featured" value="1">
                                <?= trans('cars.featured_car') ?>
                            </label>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_with_driver" value="1">
                                <?= trans('cars.with_driver_option') ?>
                            </label>
                        </div>
                        <div class="form-group col-md-4">
                            <label><?= trans('cars.status') ?></label>
                            <select name="status">
                                <option value="available"><?= trans('cars.available') ?></option>
                                <option value="maintenance"><?= trans('cars.maintenance') ?></option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <?php include VIEW_PATH . '/backend/layouts/footer.php'; ?>

    <script>
    // Drag & Drop للصور
    const dropZone = document.getElementById('dropZone');
    const imageInput = document.getElementById('imageInput');
    const previewGrid = document.getElementById('previewGrid');
    const brandSelect = document.getElementById('brandSelect');
    const modelSelect = document.getElementById('modelSelect');

    // منع السلوك الافتراضي
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // تأثيرات الـ drag
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('dragover');
        }, false);
    });

    // معالجة الإسقاط
    dropZone.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    }

    // معالجة اختيار الملفات
    imageInput.addEventListener('change', function() {
        handleFiles(this.files);
    });

    function handleFiles(files) {
        [...files].forEach(previewFile);
    }

    function previewFile(file) {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onloadend = function() {
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.innerHTML = `
                <img src="${reader.result}" alt="Preview">
                <button type="button" class="remove-btn" onclick="this.parentElement.remove()">
                    <i class="icon-x"></i>
                </button>
            `;
            previewGrid.appendChild(div);
        }
    }

    // تحميل الموديلات عند اختيار العلامة التجارية
    brandSelect.addEventListener('change', function() {
        const brandId = this.value;
        modelSelect.innerHTML = '<option value=""><?= trans('common.loading') ?>...</option>';
        
        if (!brandId) {
            modelSelect.innerHTML = '<option value=""><?= trans('cars.select_brand_first') ?></option>';
            return;
        }

        fetch(`/admin/cars/brands/${brandId}/models`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modelSelect.innerHTML = '<option value=""><?= trans('common.select') ?></option>';
                    data.models.forEach(model => {
                        modelSelect.innerHTML += `<option value="${model.id}">${model.name}</option>`;
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modelSelect.innerHTML = '<option value=""><?= trans('errors.loading_failed') ?></option>';
            });
    });
    </script>
</body>
</html>
