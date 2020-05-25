<?php declare(strict_types=1);

namespace BrunoNatali\SystemInteraction;

use React\Socket\UnixConnector as Client;
use React\Socket\ConnectionInterface;
use React\Socket\FixedUriConnector;
use React\Promise\Deferred;

class ClientConnection implements MainInterface
{
    public static function connect(string $name, &$loop, &$connector = null)
    {
        //Tools::checkSocketFolder();
        
        if (!\file_exists(self::SOCK_FOLDER . Tools::getSocketName('runas-root'))) {
            $deferred = new Deferred();

            $loop->futureTick(function () use ($deferred) {
                $deferred->reject('Server sock not exist');
            });

            return $deferred->promise();
        }

        //$connector = new FixedUriConnector ($path, new \React\Socket\UnixConnector($loop));
        $connector = new \React\Socket\UnixConnector($loop);
        
        return $connector->connect(self::SOCK_FOLDER . Tools::getSocketName('runas-root'));
    }

}