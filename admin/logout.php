<?php
/**
 * Bloomify - admin logout (POST only, CSRF protected).
 */

require_once __DIR__ . '/../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('admin/index.php');
}

require_csrf();
logout_admin();
flash_set('info', 'You have been signed out.');
redirect('login.php');
