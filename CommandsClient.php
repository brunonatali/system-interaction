#!/usr/bin/php

<?php

use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;

use BrunoNatali\SystemInteraction\ClientConnection;
use BrunoNatali\Tools\Queue;
use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\FunctionMultiFunction;


require __DIR__ . '/../../autoload.php';

class CommandsClient 
{
    public $queue;

    private $loop;
    private $myName;
    private $connected = false;
    private $myConn = null;
    private $serverConn = null;
    private $answerParse = null;

    Protected $outSystem;

    function __construct(&$loop, $name = 'cli', $config = [])
    {
        $this->loop = &$loop;
        $this->myName = $name;
        $this->queue = new Queue($this->loop);

        $config += [
            "outSystemName" => "CmdsClient"
        ];
        $this->outSystem = new OutSystem($config);
    }

    public function connect($procQueue = true)
    {
        $me = &$this;
        ClientConnection::connect($this->myName, $this->loop, $this->myConn)->then(
            function (ConnectionInterface $serverConn) use ($me, $procQueue) {
                $me->serverConn = &$serverConn;
                $me->connected = true;

                if ($procQueue) 
                    $me->queue->resume();

                $me->serverConn->on('data', function ($data) use ($me) {
                    $me->parse($data);
                });
            }, 
            function ($reason) use ($me, $procQueue) {
                $this->outSystem->stdout('Server is not running: ' . $reason, OutSystem::LEVEL_IMPORTANT);
                $this->outSystem->stdout('Scheduling connection to 5s', OutSystem::LEVEL_NOTICE);

                $me->scheduleConnect(5.0, $procQueue);
            }
        );

    }

    public function scheduleConnect($time = 1.0, $procQueue = true)
    {
        $me = &$this;
        $this->loop->addTimer($time, function () use ($me, $procQueue) {
            $me->connect($procQueue);
        });
    }

    public function close()
    {
        $this->outSystem->stdout('Good Bye!', OutSystem::LEVEL_NOTICE);
        $this->serverConn->close();
        $this->loop->stop();
    }

    public function write($command): bool
    {
        if (!$this->connected) return false;

        $this->outSystem->stdout('Exec CMD: ' . $command, OutSystem::LEVEL_NOTICE);
        $this->serverConn->write($command);
        $this->queue->pause();

        return true;
    }

    public function parse($val)
    {
        // Set function to process  
        if (is_callable($val)) {
            $this->answerParse = $val;
            return;
        }

        var_dump($val);

        if (!is_array($val = json_decode($val))) return;

        if (isset($val['data'])) {
            if ($this->answerParse !== null) {
                $this->answerParse($val['data']);
                $this->answerParse = null;
            } else {
                $this->outSystem->stdout('Answer CMD: ' . $val['data'], OutSystem::LEVEL_NOTICE);
            }
        } else if (isset($val['result'])) {
            if ($this->answerParse !== null) {
                $this->answerParse($val['result']);
                $this->answerParse = null;
            } else {
                $this->outSystem->stdout('Result CMD: ' . $val['result'], OutSystem::LEVEL_NOTICE);
            }

            $this->queue->resume(); // Just resume after command result
        }
    }
}


// Run when called from cli
if(count($argv) > 1) {
    $loop = Factory::create();

    $mine = new CommandsClient($loop, 'cli', ["outSystemEnabled" => true]);

    unset($argv[0]);
    foreach ($argv as $key => $value) {
        $mine->queue->push( function () use ($mine, $value) {
            if (!$mine->write($value)) {
                $mine->scheduleConnect(5.0);
            }
        });
    }

    // End app
    $mine->queue->push( function () use ($mine) {
        $mine->close();
    });

    $mine->connect();

    $loop->run();
}