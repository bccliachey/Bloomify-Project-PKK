<?php
/**
 * Guard included at the top of every admin page.
 */

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/admin-functions.php';
