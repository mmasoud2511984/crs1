<?php
$pageTitle = trans('auth.login');
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h2><?= trans('auth.welcome_back') ?></h2>
            <p><?= trans('auth.login_subtitle') ?></p>
        </div>

        <form method="POST" action="/login">
            <?= csrf_field() ?>
            
            <div class="mb-3">
                <label class="form-label"><?= trans('customer.email') ?></label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= trans('customer.password') ?></label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input type="checkbox" name="remember" class="form-check-input" id="remember" value="1">
                    <label class="form-check-label" for="remember">
                        <?= trans('auth.remember_me') ?>
                    </label>
                </div>
                <a href="/forgot-password" class="text-sm"><?= trans('auth.forgot_password') ?></a>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">
                <?= trans('auth.login') ?>
            </button>

            <div class="divider"><?= trans('auth.or') ?></div>

            <a href="/auth/google" class="btn btn-google w-100">
                <i class="fab fa-google"></i> <?= trans('auth.login_with_google') ?>
            </a>

            <div class="auth-footer">
                <?= trans('auth.dont_have_account') ?>
                <a href="/register"><?= trans('auth.register') ?></a>
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
    max-width: 450px;
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

.text-sm {
    font-size: 0.875rem;
    color: #667eea;
    text-decoration: none;
}
</style>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
