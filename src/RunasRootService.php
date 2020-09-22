<?php declare(strict_types=1);

namespace BrunoNatali\SystemInteraction;

use React\EventLoop\Factory;
use React\Socket\UnixServer as Server;
use React\Socket\ConnectionInterface;
use React\Stream\ReadableResourceStream;
use React\ChildProcess\Process as Proc; 

use BrunoNatali\SystemInteraction\RunasRootServiceInterface;
use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\Queue;

class RunasRootService implements RunasRootServiceInterface
{
    private $loop;
    private $server;
    private $clientConn = []; // Handle all clients info
    Protected $outSystem;

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

        $config = [
            "outSystemName" => "CmdsClient",
            "outSystemEnabled" => true
        ];
        $this->outSystem = new OutSystem($config);
    }

    public function start()
    {
        try {
            $this->server = new Server($this->socketPath, $this->loop);
        } catch (\RuntimeException $e) {
            $this->outSystem->stdout('Main Runtime ERROR: ' . $e->getMessage(), OutSystem::LEVEL_NOTICE);
            exit(1);
        } catch (\Exception $e) {
            $this->outSystem->stdout('Main ERROR: ' . $e->getMessage(), OutSystem::LEVEL_NOTICE);
            exit(1);
        }
        
        \chmod($this->socketPath, 0777);

        $this->server->on('connection', function (ConnectionInterface $connection) {
            $myId = (int) $connection->stream;
            $this->clientConn[$myId] = [
                'conn' => &$connection,
                'queue' => new Queue($this->loop)
            ];
            $this->outSystem->stdout("New client connection ($myId)", OutSystem::LEVEL_NOTICE);
                
            $connection->on('data', function ($data) use ($myId, $connection) {
                if (!is_array($pData = json_decode($data, true))) {
                    $this->outSystem->stdout("Wrong data from $myId: '$data'", OutSystem::LEVEL_IMPORTANT);
                    return;
                }

                if (isset($pData['cmd'])) {
                    $this->outSystem->stdout("Command received ($myId): " . $pData['cmd'], OutSystem::LEVEL_NOTICE);

                    $this->clientConn[$myId]['queue']->resume(); // Enable queue before start sending

                    $this->onData($pData['cmd'], $myId);

                } else if (isset($pData['ack'])) {
                    $this->outSystem->stdout("ACK received ($myId)", OutSystem::LEVEL_NOTICE);
                    $this->clientConn[$myId]['queue']->resume();
                }
            });
        
            $connection->on('end', function () use ($myId) {
                $this->outSystem->stdout('Client connection ended', OutSystem::LEVEL_NOTICE);
                $this->removeClient($myId);
            });
        
            $connection->on('error', function ($e) use ($myId) {
                $this->outSystem->stdout('Client connection Error: ' . $e->getMessage(), OutSystem::LEVEL_NOTICE);
                $this->removeClient($myId);
            });
        
            $connection->on('close', function () use ($myId) {
                $this->outSystem->stdout('Client connection closed', OutSystem::LEVEL_NOTICE);
                $this->removeClient($myId);
            });
        
            //$connection->pipe($connection);
        });
        
        $this->server->on('error', function (Exception $e) {
            $this->outSystem->stdout('Main connection Error: ' . $e->getMessage(), OutSystem::LEVEL_NOTICE);
        });
        
        $this->outSystem->stdout('Listening on: ' . $this->server->getAddress(), OutSystem::LEVEL_NOTICE);

        if ($this->autoStart) $this->loop->run();
    }

    private function onData($data, $clientId)
    {
        $process = new Proc($data);
        $process->start($this->loop);

        $process->stdout->on('data', function ($readed) use ($clientId) {
            if (isset($this->clientConn[$clientId]))
                $this->clientConn[$clientId]['queue']->push(function () use ($clientId, $readed) {
                    $this->clientConn[$clientId]['queue']->pause(); // Pause before write
                    $this->outSystem->stdout("SysReaded ($clientId): " . $readed, OutSystem::LEVEL_NOTICE);
                    $this->clientConn[$clientId]['conn']->write(json_encode(['data' => $readed]));
                });
        });

        $process->on('exit', function($exitCode, $termSignal) use ($clientId) {
            if (isset($this->clientConn[$clientId]))
                $this->clientConn[$clientId]['queue']->push(function () use ($clientId, $exitCode) {
                    $this->clientConn[$clientId]['queue']->pause(); // Pause before write
                    $this->outSystem->stdout('Final result: ' . $exitCode, OutSystem::LEVEL_NOTICE);
                    $this->clientConn[$clientId]['conn']->write(json_encode(['result' => $exitCode]));
                });
        });
    }

    private function removeClient($id)
    {
        if (isset($this->clientConn[$id]))
            unset($this->clientConn[$id]);
    }
}