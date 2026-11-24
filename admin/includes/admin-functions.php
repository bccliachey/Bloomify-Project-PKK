<?php
/**
 * Small helpers used only by the admin panel.
 */

function admin_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'item';
}

/**
 * Return a slug that is guaranteed to be unique for the given table.
 */
function admin_unique_slug(string $table, string $slug, ?int $ignoreId = null, int $maxLength = 120): string
{
    $table = $table === 'categories' ? 'categories' : 'products';
    $maxLength = max(10, $maxLength);
    $base = substr(admin_slugify($slug), 0, $maxLength);
    $candidate = $base;
    $counter = 2;

    while (true) {
        $sql = "SELECT COUNT(*) FROM `$table` WHERE slug = ?";
        $parameters = [$candidate];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $parameters[] = $ignoreId;
        }

        $statement = db()->prepare($sql);
        $statement->execute($parameters);

        if ((int) $statement->fetchColumn() === 0) {
            return $candidate;
        }

        $suffix = '-' . $counter;
        $candidate = substr($base, 0, $maxLength - strlen($suffix)) . $suffix;
        $counter++;
    }
}

/**
 * Render validation errors as an alert list.
 */
function admin_error_alert(array $errors): string
{
    if (!$errors) {
        return '';
    }

    $html = '<div class="alert alert-error"><ul class="error-list">';
    foreach ($errors as $error) {
        $html .= '<li>' . e($error) . '</li>';
    }
    $html .= '</ul></div>';

    return $html;
}
