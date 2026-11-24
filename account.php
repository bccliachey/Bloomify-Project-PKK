<?php
/**
 * Bloomify - customer account dashboard (profile, password, shortcuts).
 */

require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'My Account';
$activeNav = 'account';

$user = current_user();

$profileErrors = flash_get('profile_errors', []);
$passwordErrors = flash_get('password_errors', []);
$profileOld = flash_get('profile_old', []);

$value = function (string $key, string $fallback = '') use ($profileOld, $user): string {
    if (array_key_exists($key, $profileOld)) {
        return (string) $profileOld[$key];
    }

    return (string) ($user[$key] ?? $fallback);
};

require __DIR__ . '/includes/header.php';
?>

<main class="page-main">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= e(url('index.php')) ?>">Home</a>
        <span>/</span>
        <span>My Account</span>
    </nav>

    <div class="page-heading">
        <p class="eyebrow">Your Account</p>
        <h1>Hello, <?= e($user['full_name']) ?>.</h1>
        <p>Manage your profile, password, orders, and notifications here.</p>
    </div>

    <div class="account-layout">
        <aside class="account-sidebar">
            <img class="avatar"
                 src="<?= e(public_image_url($user['profile_photo'])) ?>"
                 alt="Profile photo of <?= e($user['full_name']) ?>">

            <p class="account-name"><?= e($user['full_name']) ?></p>
            <p class="account-username">@<?= e($user['username']) ?></p>

            <nav class="account-tabs">
                <a href="#profile" class="active">Edit Profile</a>
                <a href="#password">Change Password</a>
                <a href="<?= e(url('orders.php')) ?>">My Orders</a>
                <a href="<?= e(url('notifications.php')) ?>">Notifications</a>
                <form method="post" action="<?= e(url('actions/auth/logout.php')) ?>"
                      data-confirm="Log out of Bloomify?">
                    <?= csrf_field() ?>
                    <button type="submit" class="logout-button">Logout</button>
                </form>
            </nav>
        </aside>

        <div>
            <section class="form-card section-gap" id="profile" style="margin-bottom: 24px;">
                <h2 class="account-section-title">Edit Profile</h2>
                <p class="account-section-sub">
                    Member since <?= e(format_date($user['created_at'])) ?>.
                </p>

                <?php if ($profileErrors): ?>
                    <ul class="input-error-list">
                        <?php foreach ($profileErrors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" action="<?= e(url('actions/profile/update.php')) ?>"
                      enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name">Full name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control"
                                   value="<?= e($value('full_name')) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" class="form-control"
                                   value="<?= e($value('username')) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone / WhatsApp</label>
                            <input type="text" id="phone" name="phone" class="form-control"
                                   value="<?= e($value('phone')) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="profile_photo">Profile photo (optional)</label>
                            <input type="file" id="profile_photo" name="profile_photo"
                                   class="form-control" accept="image/jpeg,image/png,image/webp">
                            <span class="form-help">JPG, PNG, or WEBP. Maximum 2 MB.</span>
                        </div>

                        <div class="form-group full">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" required><?= e($value('address')) ?></textarea>
                        </div>

                        <div class="form-group full">
                            <label for="profile_description">Profile description (optional)</label>
                            <textarea id="profile_description" name="profile_description"
                                      class="form-control" maxlength="1000"><?= e($value('profile_description')) ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <img class="profile-photo-preview"
                             src="<?= e(public_image_url($user['profile_photo'])) ?>"
                             alt="Current profile photo">
                    </div>
                </form>
            </section>

            <section class="form-card" id="password">
                <h2 class="account-section-title">Change Password</h2>
                <p class="account-section-sub">
                    Enter your current password, then choose a new one (at least 8 characters).
                </p>

                <?php if ($passwordErrors): ?>
                    <ul class="input-error-list">
                        <?php foreach ($passwordErrors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" action="<?= e(url('actions/profile/password.php')) ?>">
                    <?= csrf_field() ?>

                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="current_password">Current password</label>
                            <input type="password" id="current_password" name="current_password"
                                   class="form-control" autocomplete="current-password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New password</label>
                            <input type="password" id="new_password" name="new_password"
                                   class="form-control" autocomplete="new-password" minlength="8" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password_confirm">Confirm new password</label>
                            <input type="password" id="new_password_confirm" name="new_password_confirm"
                                   class="form-control" autocomplete="new-password" minlength="8" required>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
