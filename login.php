<?php
/**
 * Bloomify - customer login page.
 */

require_once __DIR__ . '/includes/functions.php';

if (is_admin_logged_in()) {
    redirect('admin/index.php');
}
if (is_logged_in()) {
    redirect('index.php');
}

$pageTitle = 'Login';
$activeNav = 'account';
$extraStyles = ['Account Style.css'];

$redirectTarget = normalize_redirect($_GET['redirect'] ?? ($_SESSION['intended_url'] ?? null));
$old = flash_get('old', []);
$errors = flash_get('errors', []);

require __DIR__ . '/includes/header.php';
?>

<section class="account-section">
    <div class="account-card">
        <div class="account-image">
            <div class="image-overlay">
                <p class="mini-logo"><?= e(setting('store_name', 'Bloomify')) ?></p>
                <h2>Flowers made for <span>every moment.</span></h2>
                <p>Login to manage your cart, orders, and notifications.</p>
            </div>
        </div>

        <div class="login-area">
            <p class="subtitle">WELCOME BACK</p>
            <h1>Login to Bloomify.</h1>
            <p class="description">
                Sign in as a customer or administrator and continue discovering
                beautiful flowers made for every moment.
            </p>

            <?php if ($errors): ?>
                <ul class="input-error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="post" action="<?= e(url('actions/auth/login.php')) ?>">
                <?= csrf_field() ?>
                <?php if ($redirectTarget): ?>
                    <input type="hidden" name="redirect" value="<?= e($redirectTarget) ?>">
                <?php endif; ?>

                <div class="input-box">
                    <label for="username">Username</label>
                    <input type="text"
                           id="username"
                           name="username"
                           value="<?= e($old['username'] ?? '') ?>"
                           placeholder="Enter your username"
                           autocomplete="username"
                           required
                           autofocus>
                </div>

                <div class="input-box">
                    <label for="password">Password</label>
                    <input type="password"
                           id="password"
                           name="password"
                           placeholder="Enter your password"
                           autocomplete="current-password"
                           required>
                </div>

                <button type="submit">Login</button>
            </form>

            <p class="register">
                Don't have an account?
                <a href="<?= e(url('register.php' . ($redirectTarget ? '?redirect=' . rawurlencode($redirectTarget) : ''))) ?>">
                    Create one
                </a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
