#!/usr/bin/php

<?php

require __DIR__ . '/../../autoload.php';

require __DIR__ . '/src/RunasRootService.php';

use BrunoNatali\SystemInteraction\RunasRootService;

$service = new RunasRootService();

$service->start();
