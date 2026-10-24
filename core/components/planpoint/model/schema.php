<?php
/** Database migration shared by transport resolver and tests. */
function planpointInstallSchema($pdo, $prefix, $driver = 'mysql')
{
    if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) throw new RuntimeException('Invalid table prefix');
    $s=$prefix.'planpoint_schemes'; $a=$prefix.'planpoint_assignments'; $l=$prefix.'planpoint_locks';
    $exec=function($sql)use($pdo){if($pdo->exec($sql)===false){$error=$pdo->errorInfo();throw new RuntimeException('PlanPoint schema: '.implode(' ',array_filter($error)));}};
    if ($driver === 'sqlite') {
        $exec("CREATE TABLE IF NOT EXISTS $s (id INTEGER PRIMARY KEY AUTOINCREMENT, house_id INTEGER NOT NULL, title TEXT NOT NULL, section TEXT NOT NULL DEFAULT '', image_json TEXT NOT NULL, svg_code TEXT NOT NULL, show_pins INTEGER NOT NULL DEFAULT 1, revision INTEGER NOT NULL DEFAULT 1, created_by INTEGER NOT NULL, updated_by INTEGER NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
        $exec("CREATE TABLE IF NOT EXISTS $a (floor_id INTEGER PRIMARY KEY, scheme_id INTEGER NOT NULL, revision INTEGER NOT NULL, updated_by INTEGER NOT NULL, updated_at TEXT NOT NULL)");
        $exec("CREATE TABLE IF NOT EXISTS $l (house_id INTEGER PRIMARY KEY)");
    } else {
        $exec("CREATE TABLE IF NOT EXISTS `$s` (id INT UNSIGNED NOT NULL AUTO_INCREMENT, house_id INT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL, section VARCHAR(64) NOT NULL DEFAULT '', image_json MEDIUMTEXT NOT NULL, svg_code MEDIUMTEXT NOT NULL, show_pins TINYINT NOT NULL DEFAULT 1, revision INT UNSIGNED NOT NULL DEFAULT 1, created_by INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id), KEY house(house_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $exec("CREATE TABLE IF NOT EXISTS `$a` (floor_id INT UNSIGNED NOT NULL, scheme_id INT UNSIGNED NOT NULL, revision INT UNSIGNED NOT NULL, updated_by INT UNSIGNED NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(floor_id), KEY scheme(scheme_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $exec("CREATE TABLE IF NOT EXISTS `$l` (house_id INT UNSIGNED NOT NULL, PRIMARY KEY(house_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    $r=$prefix.'planpoint_requests'; $m=$prefix.'planpoint_meta';
    if ($driver==='sqlite') {
        $exec("CREATE TABLE IF NOT EXISTS $r (request_key TEXT PRIMARY KEY, operation TEXT NOT NULL, payload_hash TEXT NOT NULL, result_json TEXT NOT NULL, created_at TEXT NOT NULL)");
        $exec("CREATE TABLE IF NOT EXISTS $m (id INTEGER PRIMARY KEY, version INTEGER NOT NULL)");
    } else {
        $exec("CREATE TABLE IF NOT EXISTS `$r` (request_key VARCHAR(100) NOT NULL, operation VARCHAR(16) NOT NULL, payload_hash CHAR(64) NOT NULL, result_json MEDIUMTEXT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(request_key), KEY created(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $exec("CREATE TABLE IF NOT EXISTS `$m` (id TINYINT UNSIGNED PRIMARY KEY, version INT UNSIGNED NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        foreach(array($s,$a,$l,$r,$m) as $table) {
            $q=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            if (!$q || !$q->execute(array($table)) || strtoupper((string)$q->fetchColumn())!=='INNODB') throw new RuntimeException('PlanPoint: таблица '.$table.' должна использовать InnoDB. Данные не изменены.');
        }
    }
    // Verify existing tables too: IF NOT EXISTS alone cannot validate an older schema.
    $checks=array($s=>'id,house_id,title,section,image_json,svg_code,show_pins,revision,created_by,updated_by,created_at,updated_at',$a=>'floor_id,scheme_id,revision,updated_by,updated_at',$l=>'house_id',$r=>'request_key,operation,payload_hash,result_json,created_at');
    foreach($checks as $table=>$fields) if ($pdo->query("SELECT $fields FROM $table WHERE 1=0")===false) throw new RuntimeException('PlanPoint: неполная структура '.$table.'. Нужна проверка миграции.');
    $exec(($driver==='sqlite'?'INSERT OR IGNORE':'INSERT IGNORE')." INTO $m (id,version) VALUES (1,2)");
    $exec("UPDATE $m SET version=2 WHERE id=1 AND version<2");

}
