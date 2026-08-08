<?php
// registrs/lib/paths.php — centrāls ceļu atrisinātājs.
// docroot = mape, kur atrodas index.php un registrs/ (šis fails: registrs/lib/paths.php).
declare(strict_types=1);

/** Tīmekļa saknes (docroot) absolūtais ceļš. */
function reg_docroot(): string {
    return dirname(__DIR__, 2);
}

/** Datu sakne (kur csv/, build_state/). Noklusējums = docroot; pārraksta ar REG_DATA_DIR. */
function reg_data_root(): string {
    $env = getenv('REG_DATA_DIR');
    if ($env !== false && $env !== '') return rtrim($env, '/');
    return reg_docroot();
}

/** Dzīvā ur_data.db ceļš (UR_DB_PATH_OVERRIDE > UR_DB_PATH vide > data_root/csv/SQLite). */
function reg_ur_db_path(): string {
    if (defined('UR_DB_PATH_OVERRIDE')) return UR_DB_PATH_OVERRIDE;
    $env = getenv('UR_DB_PATH');
    if ($env !== false && $env !== '') return $env;
    return reg_data_root() . '/csv/SQLite/ur_data.db';
}

/** Meklēšanas/statistikas DB mape (companies.sqlite, nace_stats.sqlite). */
function reg_search_dir(): string {
    $env = getenv('REG_SEARCH_DIR');
    if ($env !== false && $env !== '') return rtrim($env, '/');
    return reg_docroot() . '/registrs/assets/search';
}
