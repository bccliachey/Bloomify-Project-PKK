<?php
/**
 * Mark one or all notifications as read.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('notifications.php');
}

require_csrf();

$user = current_user();

if (!empty($_POST['mark_all'])) {
    $update = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $update->execute([$user['id']]);
    flash_set('success', 'All notifications marked as read.');
} else {
    $notificationId = (int) ($_POST['notification_id'] ?? 0);

    if ($notificationId > 0) {
        $update = db()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
        );
        $update->execute([$notificationId, $user['id']]);
    }
}

redirect('notifications.php');
