<?php
$pageTitle = trans('auth.register');
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h2><?= trans('auth.create_account') ?></h2>
            <p><?= trans('auth.register_subtitle') ?></p>
        </div>

        <form method="POST" action="/register" id="registerForm">
            <?= csrf_field() ?>
            
            <div class="mb-3">
                <label class="form-label"><?= trans('customer.full_name') ?></label>
                <input type="text" name="full_name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= trans('customer.email') ?></label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= trans('customer.phone') ?></label>
                <input type="tel" name="phone" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= trans('customer.whatsapp') ?></label>
                <input type="tel" name="whatsapp" class="form-control" placeholder="<?= trans('auth.optional') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label"><?= trans('customer.password') ?></label>
                <input type="password" name="password" class="form-control" required minlength="8">
            </div>

            <div class="mb-3">
                <label class="form-label"><?= trans('customer.password_confirmation') ?></label>
                <input type="password" name="password_confirmation" class="form-control" required minlength="8">
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="terms" class="form-check-input" id="terms" required>
                    <label class="form-check-label" for="terms">
                        <?= trans('auth.agree_terms') ?> 
                        <a href="/terms" target="_blank"><?= trans('auth.terms_link') ?></a>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">
                <?= trans('auth.register') ?>
            </button>

            <div class="divider"><?= trans('auth.or') ?></div>

            <a href="/auth/google" class="btn btn-google w-100">
                <i class="fab fa-google"></i> <?= trans('auth.register_with_google') ?>
            </a>

            <div class="auth-footer">
                <?= trans('auth.already_have_account') ?>
                <a href="/login"><?= trans('auth.login') ?></a>
            </div>
        </form>
    </div>
</div>

<style>
.auth-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.auth-card {
    background: white;
    border-radius: 15px;
    padding: 40px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.auth-header {
    text-align: center;
    margin-bottom: 30px;
}

.auth-header h2 {
    color: #2d3748;
    font-weight: 700;
    margin-bottom: 10px;
}

.auth-header p {
    color: #718096;
}

.btn-google {
    background: #db4437;
    color: white;
    border: none;
}

.btn-google:hover {
    background: #c23321;
    color: white;
}

.divider {
    text-align: center;
    margin: 20px 0;
    position: relative;
}

.divider::before,
.divider::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 45%;
    height: 1px;
    background: #e2e8f0;
}

.divider::before {
    left: 0;
}

.divider::after {
    right: 0;
}

.auth-footer {
    text-align: center;
    margin-top: 20px;
    color: #718096;
}

.auth-footer a {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
}
</style>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
