<?php declare(strict_types=1);

namespace BrunoNatali\SystemInteraction;

use React\Socket\ConnectionInterface;

use BrunoNatali\SystemInteraction\ClientConnection;
use BrunoNatali\Tools\Queue;
use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\FunctionMultiFunction;

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
                    // Using futureTick to try to prevent data to be concatenated when more than 1 CMD was scheduled
                    $me->loop->futureTick(function () use ($me) {
                        $me->serverConn->write(json_encode(['ack' => true]));
                    });
                    $me->loop->futureTick(function () use ($me, $data) {
                        $me->parse($data);
                    });
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

    public function sendCmd($command, $redirectStream = true): bool
    {
        if (!$this->connected) return false;

        if ($redirectStream) $command .= ' 2>&1';

        $this->outSystem->stdout('Exec CMD: ' . $command, OutSystem::LEVEL_NOTICE);
        $this->serverConn->write(json_encode(['cmd' => $command]));
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

        if (!is_array($pVal = json_decode($val, true))) {
            $this->outSystem->stdout("Wrong data: '$val'", OutSystem::LEVEL_IMPORTANT);
            return;
        }

        if (isset($pVal['data'])) {
            if ($this->answerParse !== null) {
                $this->answerParse($pVal['data']);
                $this->answerParse = null;
            } else {
                $this->outSystem->stdout('Answer CMD: ' . $pVal['data'], OutSystem::LEVEL_NOTICE);
            }
        } else if (isset($pVal['result'])) {
            if ($this->answerParse !== null) {
                $this->answerParse($pVal['result']);
                $this->answerParse = null;
            } else {
                $this->outSystem->stdout('Result CMD: ' . $pVal['result'], OutSystem::LEVEL_NOTICE);
            }

            //$this->queue->resume(); // Just resume after command result
        }
    }
}