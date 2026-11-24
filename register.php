<?php
/**
 * Bloomify - customer registration page.
 */

require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

$pageTitle = 'Create Account';
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
                <h2>Join Bloomify and <span>bloom</span> with us.</h2>
                <p>Create an account to save your cart and track every order.</p>
            </div>
        </div>

        <div class="login-area">
            <p class="subtitle">NEW CUSTOMER</p>
            <h1>Create your account.</h1>
            <p class="description">
                Tell us a little about yourself so we can prepare
                your flowers and deliver them smoothly.
            </p>

            <?php if ($errors): ?>
                <ul class="input-error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="post" action="<?= e(url('actions/auth/register.php')) ?>">
                <?= csrf_field() ?>
                <?php if ($redirectTarget): ?>
                    <input type="hidden" name="redirect" value="<?= e($redirectTarget) ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group <?= isset($errors['full_name']) ? 'field-error' : '' ?>">
                        <label for="full_name">Full name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control"
                               value="<?= e($old['full_name'] ?? '') ?>"
                               autocomplete="name" required>
                    </div>

                    <div class="form-group <?= isset($errors['username']) ? 'field-error' : '' ?>">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control"
                               value="<?= e($old['username'] ?? '') ?>"
                               autocomplete="username" required>
                    </div>

                    <div class="form-group <?= isset($errors['phone']) ? 'field-error' : '' ?>">
                        <label for="phone">Phone / WhatsApp</label>
                        <input type="text" id="phone" name="phone" class="form-control"
                               value="<?= e($old['phone'] ?? '') ?>"
                               placeholder="08xxxxxxxxxx" autocomplete="tel" required>
                    </div>

                    <div class="form-group <?= isset($errors['password']) ? 'field-error' : '' ?>">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control"
                               autocomplete="new-password" minlength="8" required>
                        <span class="form-help">At least 8 characters.</span>
                    </div>

                    <div class="form-group full <?= isset($errors['password_confirm']) ? 'field-error' : '' ?>">
                        <label for="password_confirm">Confirm password</label>
                        <input type="password" id="password_confirm" name="password_confirm"
                               class="form-control" autocomplete="new-password" minlength="8" required>
                    </div>

                    <div class="form-group full <?= isset($errors['address']) ? 'field-error' : '' ?>">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control"
                                  autocomplete="street-address" required><?= e($old['address'] ?? '') ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Create account</button>
            </form>

            <p class="register">
                Already have an account?
                <a href="<?= e(url('login.php' . ($redirectTarget ? '?redirect=' . rawurlencode($redirectTarget) : ''))) ?>">
                    Login here
                </a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
