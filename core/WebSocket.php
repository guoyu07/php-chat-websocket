<?php

namespace Chat;

use Chat\Lib\Log;
use swoole_websocket_server;

class WebSocket
{
    public $host, $port;
    private $serve;

    /**
     * WebSocket constructor.
     * @param $host
     * @param $port
     */
    function __construct($host, $port)
    {
        ini_set('date.timezone', 'PRC');  // 定义时区为中华人民共和国🇨🇳
        $this->host = $host;
        $this->port = $port;
        $this->serve = new swoole_websocket_server($this->host, $this->port);
    }

    /**
     * 打开websocket链接
     */
    protected function open()
    {
        $this->serve->on('open', function (swoole_websocket_server $server, $request) {
            $fd = $request->fd;
            $server->users[$fd] = ['fd' => $fd];  //获取客户端id插入table

            // 记录创建链接数
            Log::info('user', "第{$fd}个连接, 创建成功");
        });
    }

    /**
     * 接受发送消息websocket
     */
    protected function message()
    {
        $this->serve->on('message', function (swoole_websocket_server $server, $frame) {
            $fd = $frame->fd;
            $state = $frame->opcode;
            $data = json_decode($frame->data);


            $id = isset($data->id) ? $data->id : '';
            $name = isset($data->name) ? $data->name : '';
            $sex = isset($data->sex) ? $data->sex : '';
            $icon = isset($data->icon) ? $data->icon : '';
            $message = isset($data->message) ? $data->message : '';
            $result = [];

            if ($data->type == 'init') {
                $type = 'tips';
                $result['message'] = "欢迎{$name}进入";
            } else if ($data->type == 'message') {
                $type = 'message';
                $user = $server->users[$fd]['info'];
                $result['id'] = $user['id'];
                $result['name'] = $user['name'];
                $result['sex'] = $user['sex'];
                $result['icon'] = $user['icon'];
                $result['message'] = $message;

                // 记录用户发送消息
                $message = "{$user['name']}: {$message}";
                Log::notice('user', $message);
            } else {
                $type = 'other';
            }
            $result['type'] = $type;

            // 保留用户信息
            foreach ($server->users as $u) {
                if (!isset($server->users[$u['fd']]['info'])) {
                    $server->users[$u['fd']]['info'] = [
                        'id' => $id,
                        'name' => $name,
                        'sex' => $sex,
                        'icon' => $icon,
                    ];
                }
            }

            foreach ($server->users as $u) {
                $server->push($u['fd'], json_encode($result));//消息广播给所有客户端
            }
        });
    }

    /**
     * 退出websocket
     */
    protected function close()
    {
        $this->serve->on('close', function (swoole_websocket_server $server, $fd) {
            $users = $server->users;
            foreach($users as $user) {
                if(isset($user['fd'])) {
                    $userInfo = $users[$fd]['info'];
                    $server->push($user['fd'], json_encode([
                        'type' => 'tips',
                        'name' => $userInfo['name'],
                        'message' => "用户{$userInfo['name']}退出",
                    ]));//消息广播给所有客户端(除自己)
                }
            }

            unset($server->users[$fd]);  // 清除用户信息

            // 记录用户退出
            $message = "用户{$server->users['id']}退出";
            Log::notice('user', $message);
        });
    }

    /**
     * 调用websocket
     */
    protected function start() {
        // 记录系统
        $message = "websocket服务器运行在";
        Log::info('system', $message, ['host' => $this->host, 'port' => $this->port]);
        $this->serve->start();
    }
}