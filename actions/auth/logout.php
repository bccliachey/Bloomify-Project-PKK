<?php
/**
 * Handle customer logout (POST only).
 */

require_once __DIR__ . '/../../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('index.php');
}

require_csrf();

logout_user();
flash_set('info', 'You have been logged out.');

redirect('index.php');
