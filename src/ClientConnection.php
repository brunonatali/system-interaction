<?php declare(strict_types=1);

namespace BrunoNatali\SystemInteraction;

use React\Socket\UnixConnector as Client;
use React\Socket\ConnectionInterface;
use React\Socket\FixedUriConnector;

class ClientConnection
{
    public static function connect(string $name, &$loop, &$connector = null)
    {
        Tools::checkSocketFolder();
        $path = Tools::checkSocket($name . '.sock');

        $connector = new FixedUriConnector ($path, new \React\Socket\UnixConnector($loop));
        
        // destination will be ignored, actually connects to Unix domain socket
        return $connector->connect(self::SOCK_FOLDER . Tools::getSocketName('runas-root'));
    }

}