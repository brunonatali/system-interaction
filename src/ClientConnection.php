<?php declare(strict_types=1);

namespace BrunoNatali\SystemInteraction;

use React\Socket\UnixConnector as Client;
use React\Socket\ConnectionInterface;
use React\Socket\FixedUriConnector;

class ClientConnection implements MainInterface
{

    public static function connect(name $name, $loop, &$connector = null)
    {
        $connector = new FixedUriConnector (
            SOCK_FOLDER . $name . '.sock',
            Client($loop)
        );
        
        // destination will be ignored, actually connects to Unix domain socket
        return $connector->connect('localhost:80');
    }

}