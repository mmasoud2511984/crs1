<?php
$pageTitle = trans('auth.forgot_password');
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h2><?= trans('auth.reset_password') ?></h2>
            <p><?= trans('auth.reset_password_subtitle') ?></p>
        </div>

        <form method="POST" action="/forgot-password">
            <?= csrf_field() ?>
            
            <div class="mb-3">
                <label class="form-label"><?= trans('customer.email') ?></label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">
                <?= trans('auth.send_reset_link') ?>
            </button>

            <div class="auth-footer">
                <a href="/login"><?= trans('auth.back_to_login') ?></a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
