#!/usr/bin/php

<?php

require __DIR__ . '/../dep/vendor/autoload.php';

require __DIR__ . '/src/RunasRootService.php';

$service = new RunasRootService();

$service->start();