<?php
$pageTitle = trans('auth.reset_password');
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h2><?= trans('auth.new_password') ?></h2>
            <p><?= trans('auth.new_password_subtitle') ?></p>
        </div>

        <form method="POST" action="/reset-password">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            
            <div class="mb-3">
                <label class="form-label"><?= trans('customer.password') ?></label>
                <input type="password" name="password" class="form-control" required minlength="8">
            </div>

            <div class="mb-3">
                <label class="form-label"><?= trans('customer.password_confirmation') ?></label>
                <input type="password" name="password_confirmation" class="form-control" required minlength="8">
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">
                <?= trans('auth.reset_password') ?>
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
