<?php declare(strict_types=1);

namespace BrunoNatali\SystemInteraction;

use React\EventLoop\Factory;
use React\Socket\UnixServer as Server;
use React\Socket\ConnectionInterface;
use React\Stream\ReadableResourceStream;

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
        $this->server = new Server($this->socketPath, $this->loop);
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

                    $ret = $this->onData($pData['cmd'], $myId);
                    
                    $this->clientConn[$myId]['queue']->push(function () use ($connection, $ret) {
                        $connection->write(json_encode(['result' => $ret]));
                        $this->outSystem->stdout('Final result: ' . $ret, OutSystem::LEVEL_NOTICE);
                    });

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
        $proc = proc_open(
            $data, 
            [ ["pipe","r"], ["pipe","w"], ["pipe","w"] ],
            $pipes
        );

        $readed = stream_get_contents($pipes[1]);

        $this->clientConn[$clientId]['queue']->push(function () use ($clientId, $readed) {
            $this->outSystem->stdout("SysReaded ($clientId): " . $readed, OutSystem::LEVEL_NOTICE);
            $this->clientConn[$clientId]['conn']->write(json_encode(['data' => $readed]));
        });
        fclose($pipes[1]);

        return proc_close($proc);




        var_dump($proc);

        fwrite($pipes[0], "\n");
        fclose($pipes[0]);

        $stream = [];
        foreach ($pipes as $key => $value) {
            var_dump($value);
            if (stream_get_meta_data($value)['mode'] === 'w') continue;

            var_dump(stream_set_blocking($pipes[$key], false));

            $me = &$this;
            $this->loop->addReadStream($pipes[$key], function ($stream) use ($me, $key, $dest) {
                var_dump($stream);
                //$chunk = stream_get_contents($stream);
                $chunk = fread($stream, 65535);

                // reading nothing means we reached EOF
                if ($chunk === '') {
                    $me->loop->removeReadStream($stream);
                    fclose($stream);
                    return;
                }
            
                echo "$key - $chunk" . PHP_EOL;
                $dest->write($chunk);
            });

            continue;

            $stream[$key] = new ReadableResourceStream($pipes[$key], $this->loop);
            $stream[$key]->on('data', function ($chunk) use ($key, $dest) {
                echo "$key - $chunk";
                $dest->write($chunk);
            });
            $stream[$key]->on('close', function () use ($key) {
                echo "$key [CLOSED]" . PHP_EOL;
            });
        }
    }

    private function removeClient($id)
    {
        if (isset($this->clientConn[$id]))
            unset($this->clientConn[$id]);
    }
}