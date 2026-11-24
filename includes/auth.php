<?php
/**
 * Bloomify - authentication session helpers.
 */

require_once __DIR__ . '/functions.php';

function login_user(array $user): void
{
    session_regenerate_id(true);
    unset($_SESSION['admin_id']);
    $_SESSION['user_id'] = (int) $user['id'];
}

function logout_user(): void
{
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}

function login_admin(array $admin): void
{
    session_regenerate_id(true);
    unset($_SESSION['user_id']);
    $_SESSION['admin_id'] = (int) $admin['id'];
}

function logout_admin(): void
{
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
}

/**
 * Read and clear a stored intended destination.
 */
function take_intended_redirect(string $sessionKey = 'intended_url', string $fallback = 'index.php'): string
{
    $target = normalize_redirect($_SESSION[$sessionKey] ?? null);
    unset($_SESSION[$sessionKey]);

    return $target ?? url($fallback);
}

/**
 * Password strength rule used by register and password change forms.
 */
function password_is_valid(string $password): bool
{
    return strlen($password) >= 8;
}
