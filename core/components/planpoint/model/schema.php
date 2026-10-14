<?php
/** Database migration shared by transport resolver and tests. */
function planpointInstallSchema($pdo, $prefix, $driver = 'mysql')
{
    if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) throw new RuntimeException('Invalid table prefix');
    $s=$prefix.'planpoint_schemes'; $a=$prefix.'planpoint_assignments'; $l=$prefix.'planpoint_locks';
    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS $s (id INTEGER PRIMARY KEY AUTOINCREMENT, house_id INTEGER NOT NULL, title TEXT NOT NULL, section TEXT NOT NULL DEFAULT '', image_json TEXT NOT NULL, svg_code TEXT NOT NULL, show_pins INTEGER NOT NULL DEFAULT 1, revision INTEGER NOT NULL DEFAULT 1, created_by INTEGER NOT NULL, updated_by INTEGER NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS $a (floor_id INTEGER PRIMARY KEY, scheme_id INTEGER NOT NULL, revision INTEGER NOT NULL, updated_by INTEGER NOT NULL, updated_at TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS $l (house_id INTEGER PRIMARY KEY)");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$s` (id INT UNSIGNED NOT NULL AUTO_INCREMENT, house_id INT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL, section VARCHAR(64) NOT NULL DEFAULT '', image_json MEDIUMTEXT NOT NULL, svg_code MEDIUMTEXT NOT NULL, show_pins TINYINT NOT NULL DEFAULT 1, revision INT UNSIGNED NOT NULL DEFAULT 1, created_by INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id), KEY house(house_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$a` (floor_id INT UNSIGNED NOT NULL, scheme_id INT UNSIGNED NOT NULL, revision INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(floor_id), KEY scheme(scheme_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$l` (house_id INT UNSIGNED NOT NULL, PRIMARY KEY(house_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
