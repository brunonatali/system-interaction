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
    private $queue;
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
            //$remoteAddr = $connection->getRemoteAddress();
            //var_dump($connection->stream);
            
            $this->outSystem->stdout('New client connection', OutSystem::LEVEL_NOTICE);
        
        
            $connection->on('data', function ($data) use ($connection){
                $this->outSystem->stdout('Command received: ' . $data, OutSystem::LEVEL_NOTICE);

                $ret = $this->onData($data, $connection);
                
                // Prevent result to be sent within main data;
                $this->loop->futureTick(function () use ($connection, $ret) {
                    $connection->write(json_encode(['result' => $ret]));
                    $this->outSystem->stdout('Final result: ' . $ret, OutSystem::LEVEL_NOTICE);
                });
            });
        
            $connection->on('end', function () {
                $this->outSystem->stdout('Client connection ended', OutSystem::LEVEL_NOTICE);
            });
        
            $connection->on('error', function ($e) {
                $this->outSystem->stdout('Client connection Error: ' . $e->getMessage(), OutSystem::LEVEL_NOTICE);
            });
        
            $connection->on('close', function () {
                $this->outSystem->stdout('Client connection closed', OutSystem::LEVEL_NOTICE);
            });
        
            //$connection->pipe($connection);
        });
        
        $this->server->on('error', function (Exception $e) {
            $this->outSystem->stdout('Main connection Error: ' . $e->getMessage(), OutSystem::LEVEL_NOTICE);
        });
        
        $this->outSystem->stdout('Listening on: ' . $this->server->getAddress(), OutSystem::LEVEL_NOTICE);

        if ($this->autoStart) $this->loop->run();
    }

    private function onData($data, $dest)
    {
        $proc = proc_open(
            $data, 
            [ ["pipe","r"], ["pipe","w"], ["pipe","w"] ],
            $pipes
        );

        $readed = stream_get_contents($pipes[1]);

        // Prevent result to be sent within main data;
        $this->loop->futureTick(function () use ($dest, $readed) {
            $this->outSystem->stdout('SysReaded: ' . $readed, OutSystem::LEVEL_NOTICE);
            $dest->write(json_encode(['data' => $readed]));
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
/*
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
*/
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
}







