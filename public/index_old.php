<?php

declare(strict_types=1);

use App\Config;
use App\Router;

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Http.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/SqlGuard.php';
require_once __DIR__ . '/../src/Router.php';

Config::load(__DIR__ . '/../.env');

Router::dispatch();
