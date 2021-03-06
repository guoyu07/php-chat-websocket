<?php
require_once './vendor/autoload.php';

use Chat\WebSocket;

class Chat extends WebSocket
{
    /**
     * 组合websocket方法
     * @param string $host
     * @param int $port
     */
    public static function run(string $host = '0.0.0.0', int $port = 9501)
    {
        $socket = new WebSocket($host, $port);
        $socket->open();
        $socket->message();
        $socket->close();
        $socket->start();
    }
}
