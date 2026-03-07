<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Http.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/SqlGuard.php';
require_once __DIR__ . '/../src/JsonRpc.php';
require_once __DIR__ . '/../src/Tools.php';
require_once __DIR__ . '/../src/App.php';

App::run();
