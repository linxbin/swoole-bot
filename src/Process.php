<?php

/*
 * This file is part of PHP CS Fixer.
 * (c) kcloze <pei.greet@qq.com>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Kcloze\Bot;

use Hanson\Vbot\Exceptions\FetchUuidException;
use Hanson\Vbot\Foundation\Vbot;
use Symfony\Component\Debug\Exception\FatalErrorException;

class Process
{
    const PROCESS_NAME_LOG = ' php: swoole-bot'; //shell脚本管理标示
    const PID_FILE = 'master.pid';
    private $workers;
    private $config = [];
    private $server;
    private $i = 1000;
    private $vbot;
    protected $qrCodeUrl;
    private $fd;

    public function __construct($config) {
        $this->config = $config;
        $this->logger = new Logs($config['path']);
        $this->vbot  = new Vbot($this->config);
        $this->server = new \swoole_websocket_server("0.0.0.0", 9501);
    }

    public function onMessage(\swoole_server $server, \swoole_websocket_frame  $frame) {
        $this->fd = $frame->fd;
        $this->reserveBot($this->i++);
    }


    public function close($serv, $task_id, $from_id, $data) {

    }

    public function onOpen(\swoole_websocket_server $server, $request) {

    }

    public function onTask(\swoole_server $serv, int $task_id, int $src_worker_id, mixed $data) {

    }

    public function onFinish(\swoole_server $serv, int $task_id, string $data) {

    }


    //启动tcp server服务
    public function start() {
        $this->server->set(array(
            'worker_num' => 1,      // 一般设置为服务器CPU数的1-4倍
            'daemonize' => 1,       // 以守护进程执行
            'max_request' => 10000,
            'dispatch_mode' => 2,
            'task_worker_num' => 1, // task进程的数量
            "task_ipc_mode " => 1,
            'heartbeat_check_interval ' => 60, // 心跳检测，超过60秒没有发送数据请求将会被强制关闭连接
        ));
        $this->setProcessName('job server ' . self::PROCESS_NAME_LOG);
        $this->server->on('open', array($this, 'onOpen'));
        $this->server->on('message', array($this, 'onMessage'));
        $this->server->on('close', array($this, 'close'));
        $this->server->on('task', array($this, 'onTask'));
        $this->server->on('finish', array($this, 'onFinish'));
        $this->server->start();
        $this->server->heartbeat(true);
    }

    /**
     * 创建机器人
     * @param $workNum
     * @param $server
     * @param $fd
     */
    //独立进程
    public function reserveBot($workNum) {
        $self = $this;
        $reserveProcess = new \Swoole\Process(function () use ($self, $workNum) {
            $uuid = $this->getUuid();
            $url = 'https://login.weixin.qq.com/l/'. $uuid;
            $this->send($url, 'url');
            $this->waitForLogin();
            $this->getLogin();
            $this->vbot->messageHandler->setHandler(function ($message) {
                $reply=new Reply($message, $this->config);
                $reply->send();
            });
            $this->vbot->server->init();
            $this->vbot->messageHandler->listen();
        });
        $reserveProcess->name('job-slave ' . $workNum . self::PROCESS_NAME_LOG);
        $pid = $reserveProcess->start();
        $this->workers[$pid] = $reserveProcess;
    }

    // 监控子进程
    public function registSignal(&$workers) {
        \Swoole\Process::signal(SIGTERM, function ($signo) {
            $this->setExit();
        });
        \Swoole\Process::signal(SIGCHLD, function ($signo) use (&$workers) {
            while (true) {
                $ret = \Swoole\Process::wait(false);
                if ($ret) {
                    $pid = $ret['pid'];
                    $child_process = $workers[$pid];
                    // unset($workers[$pid]);
                    echo "Worker Exit, kill_signal={$ret['signal']} PID=" . $pid . PHP_EOL;
                    $new_pid = $child_process->start();
                    $workers[$new_pid] = $child_process;
                    unset($workers[$pid]);
                } else {
                    break;
                }
            }
        });
    }

    private function setExit() {
        @unlink($this->config['path'] . self::PID_FILE);
        $this->logger->log('Time: ' . microtime(true) . '主进程退出' . "\n");
        foreach ($this->workers as $pid => $worker) {
            // 平滑退出，用exit；强制退出用kill
            \Swoole\Process::kill($pid);
            unset($this->workers[$pid]);
            $this->logger->log('主进程收到退出信号,[' . $pid . ']子进程跟着退出');
            $this->logger->log('Worker count: ' . count($this->workers));
        }
        exit();
    }

    /**
     * 设置进程名.
     *
     * @param mixed $name
     */
    private function setProcessName($name) {
        // mac os不支持进程重命名
        if (function_exists('swoole_set_process_name') && PHP_OS !== 'Darwin') {
            swoole_set_process_name($name);
        }
    }

    /**
     * 获取微信登录uuid
     */
    public function getUuid() {
        $content = $this->vbot->http->get('https://login.weixin.qq.com/jslogin', ['query' => [
            'appid' => 'wx782c26e4c19acffb',
            'fun'   => 'new',
            'lang'  => 'zh_CN',
            '_'     => time(),
        ]]);

        preg_match('/window.QRLogin.code = (\d+); window.QRLogin.uuid = \"(\S+?)\"/', $content, $matches);

        if (!$matches) {
            $this->send( '打开微信二维码失败', 'msg');
            return false;
        }

        return $this->vbot->config['server.uuid'] = $matches[2];
    }

    /**
     *
     */
    protected function waitForLogin() {
        $retryTime = 60;
        $tip = 1;

        $this->send('please scan the qrCode with wechat.', 'msg');
        while ($retryTime > 0) {
            $url = sprintf('https://login.weixin.qq.com/cgi-bin/mmwebwx-bin/login?tip=%s&uuid=%s&_=%s', $tip, $this->vbot->config['server.uuid'], time());

            $content = $this->vbot->http->get($url, ['timeout' => 35]);

            preg_match('/window.code=(\d+);/', $content, $matches);

            $code = $matches[1];
            switch ($code) {
                case '201':
                    $this->send('please confirm login in wechat.', 'msg');
                    $tip = 0;
                    break;
                case '200':
                    preg_match('/window.redirect_uri="(https:\/\/(\S+?)\/\S+?)";/', $content, $matches);

                    $this->vbot->config['server.uri.redirect'] = $matches[1].'&fun=new';
                    $url = 'https://%s/cgi-bin/mmwebwx-bin';
                    $this->vbot->config['server.uri.file'] = sprintf($url, 'file.'.$matches[2]);
                    $this->vbot->config['server.uri.push'] = sprintf($url, 'webpush.'.$matches[2]);
                    $this->vbot->config['server.uri.base'] = sprintf($url, $matches[2]);

                    return;
                case '408':
                    $tip = 1;
                    $retryTime -= 1;
                    sleep(1);
                    break;
                default:
                    $tip = 1;
                    $retryTime -= 1;
                    sleep(1);
                    break;
            }
        }
        $this->send('login time out!', 'msg');
    }

    /*
     * 获取登录信息
     */
    private function getLogin() {
        $content = $this->vbot->http->get($this->vbot->config['server.uri.redirect']);
        $data = (array) simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
        if($data['ret']) {
            $this->send($data['message'], 'msg');
            return ;
        }
        $this->send($data['message'], 'msg');
        $this->vbot->config['server.skey'] = $data['skey'];
        $this->vbot->config['server.sid'] = $data['wxsid'];
        $this->vbot->config['server.uin'] = $data['wxuin'];
        $this->vbot->config['server.passTicket'] = $data['pass_ticket'];

        if (in_array('', [$data['wxsid'], $data['wxuin'], $data['pass_ticket']])) {
            $this->send('login fail ', 'msg');
        }

        $this->vbot->config['server.deviceId'] = 'e'.substr(mt_rand().mt_rand(), 1, 15);

        $this->vbot->config['server.baseRequest'] = [
            'Uin'      => $data['wxuin'],
            'Sid'      => $data['wxsid'],
            'Skey'     => $data['skey'],
            'DeviceID' => $this->vbot->config['server.deviceId'],
        ];

        $this->saveServer();
    }

    /**
     * 保存登录信息
     */
    private function saveServer() {
        $this->logger->log("userinfo:". json_encode($this->vbot->config['server']), 'info');
    }

    /**
     * 推送消息
     * @param $data
     * @param $type
     */
    public function send($data, $type) {
        $data = [
            'type' => $type,
            'data' => $data
        ];

        $this->server->push($this->fd, json_encode($data));
    }
}
