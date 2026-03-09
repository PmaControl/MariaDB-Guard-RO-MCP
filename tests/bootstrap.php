<?php

declare(strict_types=1);

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    require_once __DIR__ . '/../src/Env.php';
    require_once __DIR__ . '/../src/Http.php';
    require_once __DIR__ . '/../src/Db.php';
    require_once __DIR__ . '/../src/AccountSecurity.php';
    require_once __DIR__ . '/../src/SqlGuard.php';
    require_once __DIR__ . '/../src/JsonRpc.php';
    require_once __DIR__ . '/../src/QueryLogger.php';
    require_once __DIR__ . '/../src/Tools.php';
    require_once __DIR__ . '/../src/App.php';
}
