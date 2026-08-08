<?php
// registrs/lib/db.php — SQLite savienojumi (read-only).
declare(strict_types=1);

require_once __DIR__ . '/paths.php';

/** Galvenās UR datubāzes ceļš (skat. paths.php). */
function ur_db_path(): string {
    return reg_ur_db_path();
}

function get_ur_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $path = ur_db_path();
    // Read-only DSN
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA query_only = ON;');
    return $pdo;
}
