<?php declare(strict_types=1);

namespace BrunoNatali\SystemInteraction;

use React\EventLoop\Factory;
use React\Socket\UnixServer as Server;
use React\Socket\ConnectionInterface;

use BrunoNatali\SystemInteraction\RunasRootServiceInterface;

class RunasRootService implements RunasRootServiceInterface
{
    private $loop;
    private $server;

    private $socketPath;

    private $autoStart = false;

    function __construct(&$loop = null)
    {
        Tools::checkSocketFolder();
        $this->socketPath = Tools::checkSocket(self::R_AS_SOCKET);

        if ($loop === null) {
            $this->loop = Factory::create();
            $this->autoStart = true;
        } else {
            $this->loop = &$loop;
        }
    }

    public function start()
    {
        $this->server = new Server($this->socketPath, $this->loop);

        $this->server->on('connection', function (ConnectionInterface $connection) {
            $remoteAddr = $connection->getRemoteAddress();
            echo '[' . $remoteAddr . ' connected]' . PHP_EOL;
        
            var_dump($connection->stream);
        
            $connection->on('data', function ($data) use ($connection, $remoteAddr){
                echo 'Data received (' . $remoteAddr . ') : ' . $data;
            });
        
            $connection->on('end', function () use ($remoteAddr) {
                echo $remoteAddr . 'ended connection';
            });
        
            $connection->on('error', function (Exception $e) use ($remoteAddr) {
                echo $remoteAddr . ' got error: ' . $e->getMessage();
            });
        
            $connection->on('close', function () use ($remoteAddr) {
                echo $remoteAddr . 'closed connection';
            });
        
            //$connection->pipe($connection);
        });
        
        $this->server->on('error', function (Exception $e) {
            echo 'error: ' . $e->getMessage() . PHP_EOL;
        });
        
        echo 'Listening on ' . $this->server->getAddress() . PHP_EOL;

        if ($this->autoStart) $this->loop->run();
    }
}







