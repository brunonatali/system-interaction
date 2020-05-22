#!/usr/bin/php

<?php

use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;

require __DIR__ . '/../dep/vendor/autoload.php';

require __DIR__ . '/src/ClientConnection.php';

$loop = Factory::create();
$myConn = null;

ClientConnection::connect($loop, 'www', $myConn)->then(function (ConnectionInterface $serverConn) {
    $serverConn->write("HELLO");
});

$loop->run();