#!/usr/bin/php

<?php

use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;

use BrunoNatali\SystemInteraction\ClientConnection;

require __DIR__ . '/../../autoload.php';

$loop = Factory::create();
$myConn = null;

ClientConnection::connect('www', $loop, $myConn)->then(function (ConnectionInterface $serverConn) {
    $serverConn->write("HELLO");
});

$loop->run();