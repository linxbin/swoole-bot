<?php

/*
 * This file is part of PHP CS Fixer.
 * (c) kcloze <pei.greet@qq.com>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Kcloze\Bot;

class Process
{
    const PROCESS_NAME_LOG = ' php: swoole-bot'; //shell脚本管理标示
    const PID_FILE = 'master.pid';
    private $workers;
    private $config = [];
    private $server;
    private $i = 1000;

    public function __construct($config)
    {
        $this->config = $config;
        $this->logger = new Logs($config['path']);
        $this->server = new \swoole_server("0.0.0.0", 9501);
        $this->server->set(array(
            'worker_num' => 1,      //一般设置为服务器CPU数的1-4倍
            'daemonize' => 1,       //以守护进程执行
            'max_request' => 10000,
            'dispatch_mode' => 2,
            'task_worker_num' => 1, //task进程的数量
            "task_ipc_mode " => 1,
        ));
        $this->server->on('Receive', array($this, 'onReceive'));
        $this->server->on('Task', array($this, 'onTask'));
        $this->server->on('Finish', array($this, 'onFinish'));
        $this->server->start();
    }

    public function onReceive(\swoole_server $server, $fd, $from_id, $data)
    {
        $this->reserveBot($this->i++);
    }

    public function onFinish($serv, $task_id, $data)
    {

    }

    public function onTask($serv, $task_id, $from_id, $data)
    {

    }

    public function start()
    {
        \Swoole\Process::daemon(true, true);

        //设置主进程
        $ppid = getmypid();
        $pid_file = $this->config['path'] . self::PID_FILE;
        if (file_exists($pid_file)) {
            echo "已有进程运行中,请先结束或重启\n";
            die();
        }
        file_put_contents($pid_file, $ppid);
        $this->setProcessName('job master ' . $ppid . self::PROCESS_NAME_LOG);

        $this->registSignal($this->workers);
    }

    //独立进程
    public function reserveBot($workNum)
    {
        $self = $this;
        $reserveProcess = new \Swoole\Process(function () use ($self, $workNum) {
            //设置进程名字
            $this->setProcessName('job ' . $workNum . self::PROCESS_NAME_LOG);
            try {
                $self->config['session']='swoole-bot' . $workNum;
                $job = new Robots($self->config);
                $job->run();
            } catch (\Exception $e) {
                echo $e->getMessage();
            }
            echo 'reserve process ' . $workNum . " is working ...\n";
        });
        $pid                 = $reserveProcess->start();
        $this->workers[$pid] = $reserveProcess;
        echo "reserve start...\n";
    }

    //监控子进程
    public function registSignal(&$workers)
    {
        \Swoole\Process::signal(SIGTERM, function ($signo) {
            $this->setExit();
        });
        \Swoole\Process::signal(SIGCHLD, function ($signo) use (&$workers) {
            while (true) {
                $ret = \Swoole\Process::wait(false);
                if ($ret) {
                    $pid = $ret['pid'];
                    $child_process = $workers[$pid];
                    //unset($workers[$pid]);
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

    private function setExit()
    {
        @unlink($this->config['path'] . self::PID_FILE);
        $this->logger->log('Time: ' . microtime(true) . '主进程退出' . "\n");
        foreach ($this->workers as $pid => $worker) {
            //平滑退出，用exit；强制退出用kill
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
    private function setProcessName($name)
    {
        //mac os不支持进程重命名
        if (function_exists('swoole_set_process_name') && PHP_OS !== 'Darwin') {
            swoole_set_process_name($name);
        }
    }
}
